<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php?error=" . urlencode("Please log in"));
    exit();
}

$conn = new mysqli("mysql-db", "alexljn5", "password", "tools4ever_db");
if ($conn->connect_error) {
    die("Connection failed");
}

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

// Handle adding orders
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_order'])) {
    $order_date = $_POST['order_date'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $order_notes = $_POST['order_notes'];
    $city = trim($_POST['city']);
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $purchase_prices = $_POST['purchase_prices'] ?? [];
    $sale_prices = $_POST['sale_prices'] ?? [];

    if (
        empty($order_date) || empty($city) || empty($products) || count($products) != count($quantities) ||
        count($products) != count($purchase_prices) || count($products) != count($sale_prices)
    ) {
        $errors[] = "All fields required; products, quantities, and prices must match";
    }
    for ($i = 0; $i < count($products); $i++) {
        if (!is_numeric($quantities[$i]) || $quantities[$i] <= 0) {
            $errors[] = "Quantity for {$products[$i]} must be positive";
        }
        if (!is_numeric($purchase_prices[$i]) || $purchase_prices[$i] <= 0) {
            $errors[] = "Purchase price for {$products[$i]} must be positive";
        }
        if (!is_numeric($sale_prices[$i]) || $sale_prices[$i] <= $purchase_prices[$i]) {
            $errors[] = "Sale price for {$products[$i]} must exceed purchase price";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO location (city, zipcode) VALUES (?, '') ON DUPLICATE KEY UPDATE idlocation = LAST_INSERT_ID(idlocation)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $city);
            $stmt->execute();
            $location_id = $conn->insert_id;
            $stmt->close();

            $total_quantity = array_sum($quantities);
            $sql = "INSERT INTO orders (order_date, delivery_date, order_notes, order_quantity, location_idlocation, delivery_status) 
                    VALUES (?, ?, ?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $order_date, $delivery_date, $order_notes, $total_quantity, $location_id);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();

            for ($i = 0; $i < count($products); $i++) {
                $product_id = $products[$i];
                $quantity = $quantities[$i];
                $purchase_price = $purchase_prices[$i];
                $sale_price = $sale_prices[$i];

                $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) 
                        VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE purchase_price = ?, sale_price = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isidddd", $location_id, $product_id, $quantity, $purchase_price, $sale_price, $purchase_price, $sale_price);
                $stmt->execute();
                $stmt->close();

                $sql = "INSERT INTO orders_has_products (orders_idorders, products_productid, order_quantity) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $order_id, $product_id, $quantity);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            header("Location: orders.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error adding order: {$e->getMessage()}";
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

// Calculate total order value in main query
foreach ($orders as $order) {
    $total_order_value += $order['order_value'] ?? 0;
}

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
            <form method="POST" action="orders.php">
                <input type="hidden" name="add_order" value="1">
                <label>Order Date:</label>
                <input type="date" name="order_date" required>
                <label>Delivery Date (Optional):</label>
                <input type="date" name="delivery_date">
                <label>Order Notes:</label>
                <textarea name="order_notes" rows="4"></textarea>
                <label>Location (City):</label>
                <?php
                $cities_query = "SELECT DISTINCT city FROM location WHERE city != '' ORDER BY city ASC";
                $cities_result = $conn->query($cities_query);
                $cities = [];
                if ($cities_result->num_rows > 0) {
                    while ($city_row = $cities_result->fetch_assoc()) {
                        $cities[] = $city_row['city'];
                    }
                }
                ?>
                <input type="text" name="city" list="cityList" required placeholder="Select or enter new city">
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
                        <input type="number" name="purchase_prices[]" step="0.01" min="0.01"
                            placeholder="Purchase Price" required>
                        <input type="number" name="sale_prices[]" step="0.01" min="0.01" placeholder="Sale Price"
                            required>
                        <button type="button" onclick="removeProduct(this)">Remove</button>
                    </div>
                </div>
                <button type="button" onclick="addProduct()">Add Product</button>
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
                            <p><strong>City:</strong> <?php echo htmlspecialchars($order['city'] ?? 'Not assigned'); ?></p>
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
    <?php $conn->close(); ?>
</body>

</html>