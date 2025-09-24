<?php
// orders.php: Order management dashboard with stock warnings and value insights
session_start();
// Require login for employee-only access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php?error=" . urlencode("Please log in to access the orders page"));
    exit();
}

$servername = "mysql-db";
$username = "alexljn5";
$password = "password";
$dbname = "tools4ever_db";

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_order'])) {
    $order_date = $_POST['order_date'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $order_notes = $_POST['order_notes'];
    $city = trim($_POST['city']);
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    // Validate inputs
    if (!empty($order_date) && !empty($city) && !empty($products) && !empty($quantities) && count($products) == count($quantities)) {
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

            // Insert into orders_has_products and update stock
            for ($i = 0; $i < count($products); $i++) {
                $product_id = $products[$i];
                $quantity = $quantities[$i];
                if (!empty($product_id) && is_numeric($quantity) && $quantity > 0) {
                    // Insert into orders_has_products
                    $sql = "INSERT INTO orders_has_products (orders_idorders, products_productid, order_quantity) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isi", $order_id, $product_id, $quantity);
                    $stmt->execute();
                    $stmt->close();

                    // Update product stock (increase for new order)
                    $sql = "UPDATE products SET amount_in_stock = amount_in_stock + ? WHERE productid = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $quantity, $product_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    throw new Exception("Invalid product or quantity");
                }
            }

            $conn->commit();
            header("Location: orders.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color: red;'>Error adding order: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>All required fields must be filled, and at least one valid product with quantity is needed!</p>";
    }
}

// Query to fetch all orders with location, products, and stock status
$sql = "SELECT o.idorders, o.order_date, o.delivery_date, o.order_notes, o.order_quantity, l.city,
               GROUP_CONCAT(CONCAT(p.type, ' (', ohp.order_quantity, ', Stock: ', p.amount_in_stock, ')') SEPARATOR ', ') as products,
               SUM(ohp.order_quantity * lhp.sale_price) as order_value
        FROM orders o
        LEFT JOIN location l ON o.location_idlocation = l.idlocation
        LEFT JOIN orders_has_products ohp ON o.idorders = ohp.orders_idorders
        LEFT JOIN products p ON ohp.products_productid = p.productid
        LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid AND l.idlocation = lhp.location_idlocation
        GROUP BY o.idorders";
$result = $conn->query($sql);

// Store orders in an array
$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Fetch products with stock status for form and warnings
$products_sql = "SELECT p.productid, p.type, p.amount_in_stock, lhp.sale_price
                 FROM products p
                 LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid
                 LEFT JOIN location l ON lhp.location_idlocation = l.idlocation
                 WHERE l.city IN ('Rotterdam', 'Almere', 'Eindhoven')";
$products_result = $conn->query($products_sql);
$products_list = [];
$low_stock_warnings = [];
$min_stock_threshold = 10; // Assumed from Data Tools for ever.pdf
if ($products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products_list[] = $row;
        if ($row['amount_in_stock'] <= $min_stock_threshold) {
            $low_stock_warnings[] = $row['type'] . " (Stock: " . $row['amount_in_stock'] . ")";
        }
    }
}

// Calculate total stock value for management
$total_stock_value_sql = "SELECT SUM(p.amount_in_stock * lhp.sale_price) as total_value
                         FROM products p
                         LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid
                         LEFT JOIN location l ON lhp.location_idlocation = l.idlocation
                         WHERE l.city IN ('Rotterdam', 'Almere', 'Eindhoven')";
$total_value_result = $conn->query($total_stock_value_sql);
$total_stock_value = $total_value_result->fetch_assoc()['total_value'] ?? 0;

$conn->close();
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
            <input type="text" id="searchbar" placeholder="Search by order ID, date, or location">
        </form>
    </div>
    <div class="addproductbutton">
        <button type="button" onclick="toggleForm()">Add Order</button>
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
                <label>Products:</label>
                <div id="product-list">
                    <div class="product-entry">
                        <select name="products[]" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products_list as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['productid']); ?>">
                                    <?php echo htmlspecialchars($product['type'] . " (Stock: " . $product['amount_in_stock'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantities[]" min="1" placeholder="Quantity" required>
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
        <?php if (!empty($low_stock_warnings)): ?>
            <div class="low-stock-warning" style="color: red; margin-bottom: 20px;">
                <h3>Low Stock Warning</h3>
                <p>Products below minimum stock (<?php echo $min_stock_threshold; ?>):
                    <?php echo htmlspecialchars(implode(", ", $low_stock_warnings)); ?>
                </p>
            </div>
        <?php endif; ?>
        <div class="product-overview">
            <h2>Order Overview</h2>
            <?php if (!empty($orders)): ?>
                <div class="product-grid">
                    <?php foreach ($orders as $order): ?>
                        <div class="product-card">
                            <h3>Order #<?php echo htmlspecialchars($order['idorders']); ?></h3>
                            <p><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                            <p><strong>Delivery Date:</strong>
                                <?php echo htmlspecialchars($order['delivery_date'] ?? 'Not set'); ?></p>
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
    <?php include 'php/footer.php'; ?>
</body>

</html>