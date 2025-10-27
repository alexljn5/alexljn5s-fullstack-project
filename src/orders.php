<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php?error=" . urlencode("Please log in"));
    exit();
}

$conn = new mysqli("mysql-db", "alexljn5", "password", "tools4ever_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Hardcoded cities
$hardcoded_cities = ['Almere', 'Amsterdam', 'Utrecht'];

$errors = [];

// Handle delivery confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delivery'])) {
    $order_id = $_POST['order_id'];
    $conn->begin_transaction();
    try {
        $check_sql = "SELECT delivery_status FROM orders WHERE idorders = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_row = $check_result->fetch_assoc()) {
            if ($check_row['delivery_status'] == 1) {
                throw new Exception("Order #$order_id already commenced");
            }
        } else {
            throw new Exception("Order #$order_id not found");
        }
        $check_stmt->close();

        $products_sql = "SELECT products_productid, order_quantity FROM orders_has_products WHERE orders_idorders = ?";
        $products_stmt = $conn->prepare($products_sql);
        $products_stmt->bind_param("i", $order_id);
        $products_stmt->execute();
        $products_result = $products_stmt->get_result();
        $order_products = $products_result->fetch_all(MYSQLI_ASSOC);
        $products_stmt->close();

        foreach ($order_products as $product) {
            $product_id = $product['products_productid'];
            $quantity = $product['order_quantity'];
            $sql = "UPDATE products SET amount_in_stock = amount_in_stock + ? WHERE productid = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $quantity, $product_id);
            $stmt->execute();
            $stmt->close();

            $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) 
                    SELECT o.location_idlocation, ?, ?, 0, 0 FROM orders o WHERE o.idorders = ?
                    ON DUPLICATE KEY UPDATE quantity = quantity + ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siii", $product_id, $quantity, $order_id, $quantity);
            $stmt->execute();
            $stmt->close();
        }

        $sql = "UPDATE orders SET delivery_status = 1 WHERE idorders = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Error confirming delivery: {$e->getMessage()}";
    }
}

// Handle removing orders
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_order'])) {
    $order_id = $_POST['order_id'];
    $conn->begin_transaction();
    try {
        // Verify order exists and is not delivered
        $check_sql = "SELECT delivery_status FROM orders WHERE idorders = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows == 0) {
            throw new Exception("Order #$order_id not found");
        }
        $order_row = $check_result->fetch_assoc();
        if ($order_row['delivery_status'] == 1) {
            throw new Exception("Cannot delete delivered order #$order_id");
        }
        $check_stmt->close();

        // Delete from orders_has_products
        $sql = "DELETE FROM orders_has_products WHERE orders_idorders = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // Delete from location_has_products
        $sql = "DELETE FROM location_has_products WHERE location_idlocation = (SELECT location_idlocation FROM orders WHERE idorders = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // Delete from orders
        $sql = "DELETE FROM orders WHERE idorders = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Error deleting order: {$e->getMessage()}";
    }
}

// Handle adding or updating orders
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_order'])) {
    $form_mode = $_POST['form_mode'] ?? 'add';
    $order_date = $_POST['order_date'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $order_notes = $_POST['order_notes'];
    $city = trim(strtolower($_POST['city']));
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $purchase_prices = $_POST['purchase_prices'] ?? [];
    $sale_prices = $_POST['sale_prices'] ?? [];
    $existing_order_id = $form_mode === 'update' ? ($_POST['existing_order_id'] ?? '') : '';

    // Validate inputs
    if (
        empty($order_date) || empty($city) || empty($products) || count($products) != count($quantities) ||
        count($products) != count($purchase_prices) || count($products) != count($sale_prices)
    ) {
        $errors[] = "All fields required; products, quantities, and prices must match";
    }
    if ($form_mode === 'update' && empty($existing_order_id)) {
        $errors[] = "Please select an order to update";
    }
    for ($i = 0; $i < count($products); $i++) {
        if (!is_numeric($quantities[$i]) || $quantities[$i] <= 0) {
            $errors[] = "Quantity for {$products[$i]} must be positive";
        }
        if (!is_numeric($purchase_prices[$i]) || $purchase_prices[$i] <= 0 || $purchase_prices[$i] > 9999.99) {
            $errors[] = "Purchase price for {$products[$i]} must be positive and not exceed 9999.99";
        }
        if (!is_numeric($sale_prices[$i]) || $sale_prices[$i] <= $purchase_prices[$i] || $sale_prices[$i] > 9999.99) {
            $errors[] = "Sale price for {$products[$i]} must exceed purchase price and not exceed 9999.99";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Get or insert location
            $sql = "INSERT INTO location (city, zipcode) VALUES (?, '') ON DUPLICATE KEY UPDATE idlocation = LAST_INSERT_ID(idlocation)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $city);
            $stmt->execute();
            $location_id = $conn->insert_id;
            $stmt->close();

            if ($form_mode === 'add') {
                // Add new order
                $total_quantity = array_sum($quantities);
                $sql = "INSERT INTO orders (order_date, delivery_date, order_notes, order_quantity, location_idlocation, delivery_status) 
                        VALUES (?, ?, ?, ?, ?, 0)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $order_date, $delivery_date, $order_notes, $total_quantity, $location_id);
                $stmt->execute();
                $order_id = $conn->insert_id;
                $stmt->close();
            } else {
                // Update existing order
                $order_id = $existing_order_id;
                // Verify order exists and is not delivered
                $check_sql = "SELECT delivery_status, order_quantity FROM orders WHERE idorders = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $order_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows == 0) {
                    throw new Exception("Order #$order_id not found");
                }
                $order_row = $check_result->fetch_assoc();
                if ($order_row['delivery_status'] == 1) {
                    throw new Exception("Cannot update delivered order #$order_id");
                }
                $current_quantity = $order_row['order_quantity'];
                $check_stmt->close();

                // Update order details
                $total_quantity = $current_quantity + array_sum($quantities);
                $sql = "UPDATE orders SET order_date = ?, delivery_date = ?, order_notes = ?, order_quantity = ?, location_idlocation = ? 
                        WHERE idorders = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiii", $order_date, $delivery_date, $order_notes, $total_quantity, $location_id, $order_id);
                $stmt->execute();
                $stmt->close();
            }

            // Insert or update products
            for ($i = 0; $i < count($products); $i++) {
                $product_id = $products[$i];
                $quantity = (int) $quantities[$i];
                $purchase_price = number_format((float) $purchase_prices[$i], 2, '.', '');
                $sale_price = number_format((float) $sale_prices[$i], 2, '.', '');

                // Update or insert into location_has_products
                $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) 
                        VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?, purchase_price = ?, sale_price = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isiddidd", $location_id, $product_id, $quantity, $purchase_price, $sale_price, $quantity, $purchase_price, $sale_price);
                $stmt->execute();
                $stmt->close();

                // Update or insert into orders_has_products
                $sql = "INSERT INTO orders_has_products (orders_idorders, products_productid, order_quantity) 
                        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE order_quantity = order_quantity + ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isii", $order_id, $product_id, $quantity, $quantity);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            header("Location: orders.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing order: {$e->getMessage()}";
        }
    }
}

// Fetch orders and total value
$search_query = $_POST['searchbar'] ?? '';
$sql = "SELECT o.idorders, o.order_date, o.delivery_date, o.order_notes, o.order_quantity, l.city, o.delivery_status,
        GROUP_CONCAT(CONCAT(p.type, ' (Qty: ', ohp.order_quantity, ', Purchase: $', lhp.purchase_price, ', Sale: $', lhp.sale_price, ')') SEPARATOR '; ') as products,
        SUM(ohp.order_quantity * lhp.sale_price) as order_value
        FROM orders o
        LEFT JOIN location l ON o.location_idlocation = l.idlocation
        LEFT JOIN orders_has_products ohp ON o.idorders = ohp.orders_idorders
        LEFT JOIN products p ON ohp.products_productid = p.productid
        LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid AND l.idlocation = lhp.location_idlocation
        WHERE o.idorders LIKE ? OR o.order_date LIKE ? OR l.city LIKE ?
        GROUP BY o.idorders";
$stmt = $conn->prepare($sql);
$search_term = "%$search_query%";
$stmt->bind_param("sss", $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Preload order details for JavaScript
$order_details = [];
foreach ($orders as $order) {
    $order_details[$order['idorders']] = [
        'order_date' => $order['order_date'],
        'delivery_date' => $order['delivery_date'] ?? 'Not set',
        'order_notes' => $order['order_notes'] ?? 'None',
        'city' => $order['city'] ?? 'Unknown',
        'order_quantity' => $order['order_quantity'],
        'products' => $order['products'] ?? 'None',
        'order_value' => number_format($order['order_value'] ?? 0, 2),
        'delivery_status' => $order['delivery_status'] == 1 ? 'Delivered' : 'Pending'
    ];
}

// Fetch products and calculate low stock
$products_sql = "SELECT p.productid, p.type, p.amount_in_stock, p.price, 
                 COALESCE(lhp.purchase_price, p.price) as purchase_price, 
                 COALESCE(lhp.sale_price, p.price * 1.2) as sale_price 
                 FROM products p LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid";
$products_result = $conn->query($products_sql);
$products_list = [];
$low_stock_warnings = [];
$min_stock_threshold = 10;
$total_order_value = 0;
if ($products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products_list[] = $row;
        if ($row['amount_in_stock'] <= $min_stock_threshold) {
            $low_stock_warnings[] = "{$row['type']} (Stock: {$row['amount_in_stock']})";
        }
    }
}

// Calculate total order value
foreach ($orders as $order) {
    $total_order_value += $order['order_value'] ?? 0;
}

// Fetch cities for datalist
$cities_query = "SELECT DISTINCT city FROM location WHERE city != '' ORDER BY city ASC";
$cities_result = $conn->query($cities_query);
$cities = [];
if ($cities_result->num_rows > 0) {
    while ($city_row = $cities_result->fetch_assoc()) {
        $cities[] = $city_row['city'];
    }
}

// Check if pending orders exist for debugging
$order_query = "SELECT idorders, COALESCE(l.city, 'Unknown') as city FROM orders o LEFT JOIN location l ON o.location_idlocation = l.idlocation WHERE o.delivery_status = 0";
$order_result = $conn->query($order_query);
$pending_orders = $order_result->num_rows;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools4Ever Orders</title>
    <link href="../styles.css" rel="stylesheet">
    <script src="../javascript/orders.js"></script>
    <script src="../javascript/searchbar.js"></script>
    <script src="../javascript/headerui.js"></script>
</head>

<body>
    <?php include 'php/header.php'; ?>
    <div class="search-bar-container">
        <form method="POST" action="">
            <input type="text" id="searchbar" name="searchbar" placeholder="Search by ID, date, or city"
                value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
    </div>
    <div class="addproductbutton">
        <button type="button" onclick="toggleForm()">Add Order</button>
        <button type="button" onclick="window.location.href='dashboard.php'">View Products</button>
        <button type="button" onclick="window.location.href='dashboard.php'" class="back-button">Back to the
            Dashboard</button>
        <div class="add-product-form" id="addOrderForm" style="display: none;">
            <?php if ($pending_orders == 0): ?>
                <div class="warning-box" style="color: red;">
                    <p>No pending orders available to update. Create a new order or check the orders table.</p>
                </div>
            <?php endif; ?>
            <form method="POST" action="orders.php" onsubmit="return confirmOrderSubmission()">
                <input type="hidden" name="add_order" value="1">
                <label for="form_mode">Action:</label>
                <select id="form_mode" name="form_mode" onchange="toggleFormFields()">
                    <option value="add">Add New Order</option>
                    <option value="update" <?php echo $pending_orders == 0 ? 'disabled' : ''; ?>>Update Existing Order
                    </option>
                </select>

                <!-- Fields for updating order -->
                <div id="update_order_fields" style="display: none;">
                    <label for="existing_order_id">Select Order:</label>
                    <select id="existing_order_id" name="existing_order_id" onchange="displayOrderDetails()">
                        <option value="">Select an order</option>
                        <?php
                        $order_result->data_seek(0); // Reset result pointer
                        if ($pending_orders > 0) {
                            while ($row = $order_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['idorders']) . '">' .
                                    htmlspecialchars('Order #' . $row['idorders'] . ' (' . $row['city'] . ')') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <!-- Shared fields for both modes -->
                <div id="order_fields">
                    <label>Order Date:</label>
                    <input type="date" name="order_date" id="order_date" required>
                    <label>Delivery Date (Optional):</label>
                    <input type="date" name="delivery_date" id="delivery_date">
                    <label>Order Notes:</label>
                    <textarea name="order_notes" id="order_notes" rows="4"></textarea>
                    <label for="city_select">Select City:</label>
                    <select id="city_select" onchange="document.getElementById('city').value = this.value;">
                        <option value="">Select a city</option>
                        <?php foreach ($hardcoded_cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Location (City):</label>
                    <input type="text" name="city" id="city" list="cityList" required
                        placeholder="Select or enter new city">
                    <datalist id="cityList">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                    </datalist>
                    <label>Products:</label>
                    <div id="product-list">
                        <div class="product-entry">
                            <select name="products[]" required onchange="updatePriceFields(this)">
                                <option value="">Select Product</option>
                                <?php foreach ($products_list as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['productid']); ?>"
                                        data-purchase-price="<?php echo number_format($product['purchase_price'], 2); ?>"
                                        data-sale-price="<?php echo number_format($product['sale_price'], 2); ?>">
                                        <?php echo htmlspecialchars($product['type'] . " (Stock: " . $product['amount_in_stock'] . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="quantities[]" min="1" placeholder="Quantity" required>
                            <input type="number" name="purchase_prices[]" step="0.01" min="0.01" max="9999.99"
                                placeholder="Purchase Price" required>
                            <input type="number" name="sale_prices[]" step="0.01" min="0.01" max="9999.99"
                                placeholder="Sale Price" required>
                            <button type="button" onclick="removeProduct(this)">Remove</button>
                        </div>
                    </div>
                    <button type="button" onclick="addProduct()">Add Product</button>
                </div>
                <div id="order_details" style="display: none; margin-top: 1rem;"></div>
                <button type="submit">Save Order</button>
                <button type="button" onclick="toggleForm()">Cancel</button>
            </form>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="warning-box" style="color: red;">
            <h3>Errors</h3>
            <ul><?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="input-container">
        <div class="stock-value">
            <h3>Total Order Value</h3>
            <p>$<?php echo number_format($total_order_value, 2); ?></p>
        </div>
        <div class="product-overview">
            <h2>Order Overview</h2>
            <?php if ($orders): ?>
                <div class="product-grid">
                    <?php foreach ($orders as $order): ?>
                        <div class="product-card">
                            <h3>Order #<?php echo htmlspecialchars($order['idorders']); ?></h3>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                            <p><strong>Delivery:</strong> <?php echo htmlspecialchars($order['delivery_date'] ?? 'Not set'); ?>
                            </p>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($order['order_notes'] ?? 'None'); ?></p>
                            <p><strong>Quantity:</strong> <?php echo htmlspecialchars($order['order_quantity']); ?></p>
                            <p><strong>City:</strong> <?php echo htmlspecialchars($order['city'] ?? 'Unknown'); ?></p>
                            <p><strong>Products:</strong> <?php echo htmlspecialchars($order['products'] ?? 'None'); ?></p>
                            <p><strong>Value:</strong> $<?php echo number_format($order['order_value'] ?? 0, 2); ?></p>
                            <p><strong>Status:</strong> <?php echo $order['delivery_status'] == 1 ? 'Delivered' : 'Pending'; ?>
                            </p>
                            <?php if ($order['delivery_status'] != 1): ?>
                                <form method="POST" action="orders.php"
                                    onsubmit="return confirmDelivery(<?php echo $order['idorders']; ?>)">
                                    <input type="hidden" name="confirm_delivery" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['idorders']; ?>">
                                    <button type="submit">Confirm Delivery</button>
                                </form>
                                <form method="POST" action="orders.php"
                                    onsubmit="return confirmRemoveOrder(<?php echo $order['idorders']; ?>)">
                                    <input type="hidden" name="remove_order" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['idorders']; ?>">
                                    <button type="submit" style="background-color: #ff4444; color: white;">Remove Order</button>
                                </form>
                            <?php else: ?>
                                <button type="button" disabled style="background-color: #ccc;">Delivered</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No orders found.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($low_stock_warnings): ?>
        <div class="sidebar">
            <div class="warning-box">
                <h3>Low Stock Alert</h3>
                <p>Below threshold (<?php echo $min_stock_threshold; ?>):</p>
                <ul><?php foreach ($low_stock_warnings as $warning): ?>
                        <li><?php echo htmlspecialchars($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
    <?php include 'php/footer.php'; ?>
    <script>
        // Preloaded order details
        const orderDetails = <?php echo json_encode($order_details); ?>;

        function toggleFormFields() {
            const formMode = document.getElementById('form_mode').value;
            const updateFields = document.getElementById('update_order_fields');
            const orderFields = document.getElementById('order_fields');
            const orderDetailsDiv = document.getElementById('order_details');

            if (formMode === 'add') {
                updateFields.style.display = 'none';
                orderFields.style.display = 'block';
                orderDetailsDiv.style.display = 'none';
                document.getElementById('existing_order_id').required = false;
                document.getElementById('order_date').required = true;
                document.getElementById('city').required = true;
                document.getElementById('existing_order_id').value = '';
                document.getElementById('order_date').value = '';
                document.getElementById('delivery_date').value = '';
                document.getElementById('order_notes').value = '';
                document.getElementById('city').value = '';
            } else {
                updateFields.style.display = 'block';
                orderFields.style.display = 'block';
                document.getElementById('existing_order_id').required = true;
                document.getElementById('order_date').required = true;
                document.getElementById('city').required = true;
                displayOrderDetails();
            }
        }

        function displayOrderDetails() {
            const orderId = document.getElementById('existing_order_id').value;
            const orderDetailsDiv = document.getElementById('order_details');
            const orderDateInput = document.getElementById('order_date');
            const deliveryDateInput = document.getElementById('delivery_date');
            const orderNotesInput = document.getElementById('order_notes');
            const cityInput = document.getElementById('city');

            if (orderId && orderDetails[orderId]) {
                const details = orderDetails[orderId];
                orderDetailsDiv.style.display = 'block';
                orderDetailsDiv.innerHTML = `
                    <div class="product-card">
                        <h3>Order #${orderId}</h3>
                        <p><strong>Date:</strong> ${details.order_date}</p>
                        <p><strong>Delivery:</strong> ${details.delivery_date}</p>
                        <p><strong>Notes:</strong> ${details.order_notes}</p>
                        <p><strong>Quantity:</strong> ${details.order_quantity}</p>
                        <p><strong>City:</strong> ${details.city}</p>
                        <p><strong>Products:</strong> ${details.products}</p>
                        <p><strong>Value:</strong> $${details.order_value}</p>
                        <p><strong>Status:</strong> ${details.delivery_status}</p>
                    </div>
                `;
                orderDateInput.value = details.order_date;
                deliveryDateInput.value = details.delivery_date === 'Not set' ? '' : details.delivery_date;
                orderNotesInput.value = details.order_notes === 'None' ? '' : details.order_notes;
                cityInput.value = details.city === 'Unknown' ? '' : details.city;
            } else {
                orderDetailsDiv.style.display = 'none';
                orderDetailsDiv.innerHTML = '';
                orderDateInput.value = '';
                deliveryDateInput.value = '';
                orderNotesInput.value = '';
                cityInput.value = '';
            }
        }

        function confirmOrderSubmission() {
            const formMode = document.getElementById('form_mode').value;
            const city = document.getElementById('city').value;
            if (formMode === 'add') {
                return confirm(`Are you sure you want to add this order for ${city}?`);
            } else {
                const orderId = document.getElementById('existing_order_id').value;
                return confirm(`Are you sure you want to update order #${orderId} for ${city}?`);
            }
        }

        function confirmDelivery(orderId) {
            return confirm(`Are you sure you want to confirm delivery for order #${orderId}?`);
        }

        function confirmRemoveOrder(orderId) {
            return confirm(`Are you sure you want to delete order #${orderId}? This action cannot be undone.`);
        }
    </script>
    <?php $conn->close(); ?>
</body>

</html>