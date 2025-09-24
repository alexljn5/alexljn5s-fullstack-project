<?php
// dashboard.php: Product management dashboard with location support, stock warnings, and value insights
session_start();
// Require login for employee-only access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php?error=" . urlencode("Please log in to access the dashboard"));
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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $productid = $_POST['productid'];
    $type = $_POST['type'];
    $manufacturer = $_POST['manufacturer'];
    $price = $_POST['price'];
    $amount_in_stock = $_POST['amount_in_stock'];
    $city = trim($_POST['city']);

    // Basic validation
    if (!empty($productid) && !empty($type) && !empty($manufacturer) && !empty($price) && is_numeric($amount_in_stock) && $amount_in_stock >= 0 && !empty($city)) {
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
                $zipcode = ''; // Empty zipcode as in your script
                $insert_location_sql = "INSERT INTO location (city, zipcode) VALUES (?, ?)";
                $insert_location_stmt = $conn->prepare($insert_location_sql);
                $insert_location_stmt->bind_param("ss", $city, $zipcode);
                $insert_location_stmt->execute();
                $location_id = $conn->insert_id;
                $insert_location_stmt->close();
            }
            $check_stmt->close();

            // Insert into products table
            $sql = "INSERT INTO products (productid, type, manufacturer, price, amount_in_stock) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $productid, $type, $manufacturer, $price, $amount_in_stock);
            $stmt->execute();
            $stmt->close();

            // Insert into location_has_products
            $sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isidd", $location_id, $productid, $amount_in_stock, $price, $price);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color: red;'>Error adding product: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>All fields are required, stock must be a non-negative number, and location must be entered!</p>";
    }
}

// Query to fetch all products with location and sale price
$sql = "SELECT p.productid, p.type, p.manufacturer, p.price, p.amount_in_stock, l.city, lhp.sale_price
        FROM products p 
        LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid 
        LEFT JOIN location l ON lhp.location_idlocation = l.idlocation";
$result = $conn->query($sql);

// Store products in an array and build low-stock warnings
$products = [];
$low_stock_warnings = [];
$min_stock_threshold = 10; // Assumed from Data Tools for ever.pdf
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
        if ($row['amount_in_stock'] <= $min_stock_threshold) {
            $city = $row['city'] ?? 'Unknown';
            $key = $row['type'] . "_" . $city; // Deduplicate by type and city
            $low_stock_warnings[$key] = $row['type'] . " (Stock: " . $row['amount_in_stock'] . ", " . $city . ")";
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
    <title>Tools4Ever Dashboard</title>
    <link href="../styles.css" rel="stylesheet">
    <script src="../javascript/dashboard.js"></script>
    <script src="../javascript/searchbar.js"></script>
    <script src="../javascript/headerui.js"></script>
</head>

<body>
    <?php include 'php/header.php'; ?>
    <div class="search-bar-container">
        <form method="POST" action="">
            <input type="text" id="searchbar" placeholder="Search by type, ID, stock, or location">
        </form>
    </div>
    <div class="addproductbutton">
        <button type="button" onclick="toggleForm()">Add Product</button>
        <button type="button" onclick="window.location.href='orders.php'">View Orders</button>
        <div class="add-product-form" id="addProductForm" style="display: none;">
            <form method="POST" action="dashboard.php">
                <input type="hidden" name="add_product" value="1">
                <label for="productid">Product ID:</label>
                <input type="text" id="productid" name="productid" required>
                <label for="type">Type:</label>
                <input type="text" id="type" name="type" required>
                <label for="manufacturer">Manufacturer:</label>
                <input type="text" id="manufacturer" name="manufacturer" required>
                <label for="price">Price:</label>
                <input type="text" id="price" name="price" required>
                <label for="amount_in_stock">Stock:</label>
                <input type="number" id="amount_in_stock" name="amount_in_stock" min="0" required>
                <label for="city">Location (City):</label>
                <input type="text" id="city" name="city" placeholder="Enter city name" required>
                <button type="submit">Save Product</button>
                <button type="button" onclick="toggleForm()">Cancel</button>
            </form>
        </div>
    </div>
    <div class="dashboard-container" style="display: flex; gap: 20px; margin: 20px;">
        <div class="input-container" style="flex: 1;">
            <div class="stock-value" style="margin-bottom: 20px;">
                <h3>Total Stock Value (Rotterdam, Almere, Eindhoven)</h3>
                <p>$<?php echo number_format($total_stock_value, 2); ?></p>
            </div>
            <div class="product-overview">
                <h2>Product Overview</h2>
                <?php if (!empty($products)): ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <h3><?php echo htmlspecialchars($product['type']); ?></h3>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($product['productid']); ?></p>
                                <p><strong>Manufacturer:</strong> <?php echo htmlspecialchars($product['manufacturer']); ?></p>
                                <p><strong>Price:</strong>
                                    $<?php echo htmlspecialchars($product['sale_price'] ?? $product['price']); ?></p>
                                <p><strong>Stock:</strong> <?php echo htmlspecialchars($product['amount_in_stock'] ?? '0'); ?>
                                </p>
                                <p><strong>Location:</strong>
                                    <?php echo htmlspecialchars($product['city'] ?? 'Not assigned'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No products found in the database.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($low_stock_warnings)): ?>
            <div class="sidebar" style="flex: 0 0 25%; max-width: 300px;">
                <div class="warning-box"
                    style="background-color: #fff3f3; border: 2px solid #ff4d4d; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #d8000c;">Low Stock Alert</h3>
                    <p style="margin: 5px 0;">The following products are below the minimum stock threshold
                        (<?php echo $min_stock_threshold; ?>):</p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <?php foreach ($low_stock_warnings as $warning): ?>
                            <li style="color: #d8000c;"><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'php/footer.php'; ?>
</body>

</html>