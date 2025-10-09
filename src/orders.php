<?php
session_start();
// Require login for employee-only access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php?error=" . urlencode("Please log in to access the orders page"));
    exit();
}

$servername = "mysql-db";
$username = "alexljn5";
$password = "password"; // Update to your actual password
$dbname = "tools4ever_db";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_order'])) {
    error_log("POST data: " . print_r($_POST, true));
    $order_date = $_POST['order_date'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $order_notes = $_POST['order_notes'];
    $city = trim($_POST['city']);
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $purchase_prices = $_POST['purchase_prices'] ?? [];
    $sale_prices = $_POST['sale_prices'] ?? [];
    $update_stock = isset($_POST['update_stock']) && $_POST['update_stock'] === '1'; // Optional stock update

    // Validate inputs
    if (empty($order_date) || empty($city) || empty($products) || empty($quantities) || count($products) != count($quantities) || count($products) != count($purchase_prices) || count($products) != count($sale_prices)) {
        $errors[] = "All required fields must be filled, and products, quantities, purchase prices, and sale prices must match.";
    }
    for ($i = 0; $i < count($products); $i++) {
        if (!is_numeric($quantities[$i]) || $quantities[$i] <= 0) {
            $errors[] = "Quantity for product {$products[$i]} must be a positive number.";
        }
        if (!is_numeric($purchase_prices[$i]) || $purchase_prices[$i] <= 0) {
            $errors[] = "Purchase price for product {$products[$i]} must be a positive number.";
        }
        if (!is_numeric($sale_prices[$i]) || $sale_prices[$i] <= $purchase_prices[$i]) {
            $errors[] = "Sale price for product {$products[$i]} must be greater than its purchase price.";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Check if city exists; if not, insert it
            $location_id = null;
            $check_sql = "SELECT idlocation FROM location WHERE city = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $city);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_row = $check_result->fetch_assoc()) {
                $location_id = $check_row['idlocation'];
            } else {
                $zipcode = '';
                $insert_location_sql = "INSERT INTO location (city, zipcode) VALUES (?, ?)";
                $insert_location_stmt = $conn->prepare($insert_location_sql);
                $insert_location_stmt->bind_param("ss", $city, $zipcode);
                $insert_location_stmt->execute();
                $location_id = $conn->insert_id;
                $insert_location_stmt->close();
            }
            $check_stmt->close();

            // Calculate total order quantity
            $total_quantity = array_sum($quantities);

            // Insert into orders table
            $sql = "INSERT INTO orders (order_date, delivery_date, order_notes, order_quantity, location_idlocation) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $order_date, $delivery_date, $order_notes, $total_quantity, $location_id);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();

            // Insert into orders_has_products and optionally update stock
            for ($i = 0; $i < count($products); $i++) {
                $product_id = $products[$i];
                $quantity = $quantities[$i];
                $purchase_price = $purchase_prices[$i];
                $sale_price = $sale_prices[$i];

                // Optional stock check and update
                if ($update_stock) {
                    $stock_sql = "SELECT amount_in_stock FROM products WHERE productid = ?";
                    $stock_stmt = $conn->prepare($stock_sql);
                    $stock_stmt->bind_param("s", $product_id);
                    $stock_stmt->execute();
                    $stock_result = $stock_stmt->get_result();
                    $stock_row = $stock_result->fetch_assoc();
                    if ($stock_row['amount_in_stock'] < $quantity) {
                        throw new Exception("Insufficient stock for product {$product_id}. Available: {$stock_row['amount_in_stock']}, Requested: {$quantity}");
                    }
                    $stock_stmt->close();

                    // Update product stock (decrease for order)
                    $sql = "UPDATE products SET amount_in_stock = amount_in_stock - ? WHERE productid = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $quantity, $product_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update location_has_products
                    $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE quantity = quantity - ?, purchase_price = ?, sale_price = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isidddidd", $location_id, $product_id, $quantity, $purchase_price, $sale_price, $quantity, $purchase_price, $sale_price);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Update location_has_products without stock check
                    $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE purchase_price = ?, sale_price = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isidddd", $location_id, $product_id, $quantity, $purchase_price, $sale_price, $purchase_price, $sale_price);
                    $stmt->execute();
                    $stmt->close();
                }

                // Insert into orders_has_products
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
            $errors[] = "Error adding order: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle search
$search_query = $_POST['searchbar'] ?? '';
$orders = [];
$sql = "SELECT o.idorders, o.order_date, o.delivery_date, o.order_notes, o.order_quantity, l.city,
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
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Fetch products for form
$products_sql = "SELECT p.productid, p.type, p.amount_in_stock, p.price,
                 COALESCE(lhp.purchase_price, p.price) as purchase_price,
                 COALESCE(lhp.sale_price, p.price * 1.2) as sale_price
                 FROM products p
                 LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid
                 LEFT JOIN location l ON lhp.location_idlocation = l.idlocation";
$products_result = $conn->query($products_sql);
$products_list = [];
$low_stock_warnings = [];
$min_stock_threshold = 10;
if ($products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products_list[] = $row;
        if ($row['amount_in_stock'] <= $min_stock_threshold) {
            $low_stock_warnings[] = $row['type'] . " (Stock: " . $row['amount_in_stock'] . ")";
        }
    }
}

// Calculate total order value
$total_order_value_sql = "SELECT SUM(ohp.order_quantity * lhp.sale_price) as total_value
                         FROM orders_has_products ohp
                         LEFT JOIN location_has_products lhp ON ohp.products_productid = lhp.products_productid
                         LEFT JOIN orders o ON ohp.orders_idorders = o.idorders
                         LEFT JOIN location l ON o.location_idlocation = l.idlocation";
$total_value_result = $conn->query($total_order_value_sql);
$total_order_value = $total_value_result->fetch_assoc()['total_value'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools4Ever Orders</title>
    <link href="/styles.css" rel="stylesheet">
    <script src="/javascript/orders.js"></script>
    <script src="/javascript/searchbar.js"></script>
    <script src="/javascript/headerui.js"></script>
</head>

<body>
    <?php include 'php/header.php'; ?>
    <div class="search-bar-container">
        <form method="POST" action="">
            <input type="text" id="searchbar" name="searchbar" placeholder="Search by order ID, date, or location"
                value="<?php echo htmlspecialchars($search_query); ?>">
        </form>
    </div>
    <div class="addproductbutton">
        <button type="button" onclick="toggleForm()">Add Order</button>
        <button type="button" onclick="window.location.href='dashboard.php'">View Products</button>
        <div class="add-product-form" id="addOrderForm" style="display: none;">
            <form method="POST" action="orders.php">
                <input type="hidden" name="add_order" value="1">
                <label for="order_date">Order Date:</label>
                <input type="date" id="order_date" name="order_date" required>
                <label for="delivery_date">Delivery Date (Optional):</label>
                <input type="date" id="delivery_date" name="delivery_date">
                <label for="order_notes">Order Notes:</label>
                <textarea id="order_notes" name="order_notes" rows="4"></textarea>
                <label for="city">Location (City):</label>
                <select name="city" id="city" required>
                    <option value="">Select City</option>
                    <option value="Rotterdam">Rotterdam</option>
                    <option value="Almere">Almere</option>
                    <option value="Eindhoven">Eindhoven</option>
                </select>
                <label><input type="checkbox" name="update_stock" value="1"> Update Stock (Decrease on Order)</label>
                <label>Products:</label>
                <div id="product-list">
                    <div class="product-entry">
                        <select name="products[]" required onchange="updatePriceFields(this)">
                            <option value="">Select Product</option>
                            <?php foreach ($products_list as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['productid']); ?>" 
                                            data-purchase-price="<?php echo htmlspecialchars(number_format($product['purchase_price'], 2)); ?>"
                                            data-sale-price="<?php echo htmlspecialchars(number_format($product['sale_price'], 2)); ?>">
                                        <?php echo htmlspecialchars($product['type'] . " (Stock: " . $product['amount_in_stock'] . ")"); ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantities[]" min="1" placeholder="Quantity" required>
                        <input type="number" name="purchase_prices[]" step="0.01" min="0.01" placeholder="Purchase Price" required>
                        <input type="number" name="sale_prices[]" step="0.01" min="0.01" placeholder="Sale Price" required>
                        <button type="button" onclick="removeProduct(this)">Remove</button>
                    </div>
                </div>
                <button type="button" onclick="addProduct()">Add Another Product</button>
                <button type="submit">Save Order</button>
                <button type="button" onclick="toggleForm()">Cancel</button>
            </form>
        </div>
    </div>
    <div class="input-container">
        <div class="stock-value" style="margin-bottom: 20px;">
            <h3>Total Order Value</h3>
            <p>$<?php echo number_format($total_order_value, 2); ?></p>
        </div>
        <div class="product-overview">
            <h2>Order Overview</h2>
            <?php if (!empty($orders)): ?>
                    <div class="product-grid">
                        <?php foreach ($orders as $order): ?>
                                <div class="product-card">
                                    <h3>Order #<?php echo htmlspecialchars($order['idorders']); ?></h3>
                                    <p><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                                    <p><strong>Delivery Date:</strong> <?php echo htmlspecialchars($order['delivery_date'] ?? 'Not set'); ?></p>
                                    <p><strong>Notes:</strong> <?php echo htmlspecialchars($order['order_notes'] ?? 'None'); ?></p>
                                    <p><strong>Total Quantity:</strong> <?php echo htmlspecialchars($order['order_quantity']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($order['city'] ?? 'Not assigned'); ?></p>
                                    <p><strong>Products:</strong> <?php echo htmlspecialchars($order['products'] ?? 'None'); ?></p>
                                    <p><strong>Order Value:</strong> $<?php echo number_format($order['order_value'] ?? 0, 2); ?></p>
                                </div>
                        <?php endforeach; ?>
                    </div>
            <?php else: ?>
                    <p>No orders found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($low_stock_warnings) || !empty($errors)): ?>
            <div class="sidebar">
                <?php if (!empty($low_stock_warnings)): ?>
                        <div class="warning-box">
                            <h3>Low Stock Alert</h3>
                            <p>The following products are below the minimum stock threshold (<?php echo $min_stock_threshold; ?>):</p>
                            <ul>
                                <?php foreach ($low_stock_warnings as $warning): ?>
                                        <li><?php echo htmlspecialchars($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                        <div class="warning-box" style="color: red;">
                            <h3>Errors</h3>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                <?php endif; ?>
            </div>
    <?php endif; ?>
    <?php include 'php/footer.php'; ?>
</body>
</html>