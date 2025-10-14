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

// Handle form submission
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $productid = trim($_POST['productid']);
    $type = trim($_POST['type']);
    $manufacturer = trim($_POST['manufacturer']);
    $price = trim($_POST['price']);
    $amount_in_stock = trim($_POST['amount_in_stock']);
    $city = trim($_POST['city']);

    // Validate inputs
    if (empty($productid) || empty($type) || empty($manufacturer) || empty($price) || empty($city)) {
        $errors[] = "All fields are required!";
    }
    if (!is_numeric($price) || $price < 0) {
        $errors[] = "Price must be a non-negative number!";
    }
    if (!is_numeric($amount_in_stock) || $amount_in_stock < 0) {
        $errors[] = "Stock must be a non-negative number!";
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Check if product already exists
            $check_sql = "SELECT productid FROM products WHERE productid = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $productid);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Product ID already exists!");
            }
            $check_stmt->close();

            // Get or insert location
            $zipcode = '';
            // First try to get existing location
            $location_check_sql = "SELECT idlocation FROM location WHERE city = ?";
            $check_stmt = $conn->prepare($location_check_sql);
            $check_stmt->bind_param("s", $city);
            $check_stmt->execute();
            $location_result = $check_stmt->get_result();
            $check_stmt->close();

            if ($location_result->num_rows > 0) {
                // Location exists, use its ID
                $location_row = $location_result->fetch_assoc();
                $location_id = $location_row['idlocation'];
            } else {
                // Location doesn't exist, insert new one
                $location_sql = "INSERT INTO location (city, zipcode) VALUES (?, ?)";
                $location_stmt = $conn->prepare($location_sql);
                $location_stmt->bind_param("ss", $city, $zipcode);
                $location_stmt->execute();
                $location_id = $conn->insert_id;
                $location_stmt->close();
            }

            // Insert product with explicit type casting
            $product_sql = "INSERT INTO products (productid, type, manufacturer, price, amount_in_stock) VALUES (?, ?, ?, ?, ?)";
            $product_stmt = $conn->prepare($product_sql);
            $price_float = (float) $price;
            $amount_int = (int) $amount_in_stock;
            $product_stmt->bind_param("sssdi", $productid, $type, $manufacturer, $price_float, $amount_int);
            $product_stmt->execute();
            $product_stmt->close();

            // Insert location-product link
            $link_sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) VALUES (?, ?, ?, ?, ?)";
            $link_stmt = $conn->prepare($link_sql);
            $link_stmt->bind_param("isidd", $location_id, $productid, $amount_int, $price_float, $price_float);
            $link_stmt->execute();
            $link_stmt->close();

            $conn->commit();
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error adding product: " . $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch products and calculate stock value
$sql = "SELECT p.productid, p.type, p.manufacturer, p.price, p.amount_in_stock, l.city, lhp.sale_price, lhp.quantity
        FROM products p 
        LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid 
        LEFT JOIN location l ON lhp.location_idlocation = l.idlocation";
$result = $conn->query($sql);

$products = [];
$low_stock_warnings = [];
$min_stock_threshold = 10;
$total_stock_value = 0.00;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
        if ($row['amount_in_stock'] <= $min_stock_threshold) {
            $city = $row['city'] ?? 'Unknown';
            $key = $row['type'] . "_" . $city;
            $low_stock_warnings[$key] = "{$row['type']} (Stock: {$row['amount_in_stock']}, {$city})";
        }
        $total_stock_value += ($row['quantity'] ?? 0) * (float) ($row['price'] ?? 0);
    }
}

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
                <label for="productid">Product Name:</label>
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
                <?php
                $cities_query = "SELECT DISTINCT city FROM location WHERE city != ''";
                $cities_result = $conn->query($cities_query);
                $cities = [];
                if ($cities_result->num_rows > 0) {
                    while ($city_row = $cities_result->fetch_assoc()) {
                        $cities[] = $city_row['city'];
                    }
                }
                ?>
                <input type="text" id="city" name="city" list="cityList" required
                    placeholder="Select or enter new city">
                <datalist id="cityList">
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>">
                        <?php endforeach; ?>
                </datalist>
                <button type="submit">Save Product</button>
                <button type="button" onclick="toggleForm()">Cancel</button>
            </form>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <p style="color: red;"><?php echo implode("<br>", array_map('htmlspecialchars', $errors)); ?></p>
    <?php endif; ?>
    <div class="input-container">
        <div class="stock-value">
            <h3>Total Stock Value</h3>
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
                            <p><strong>Price:</strong> $<?php echo htmlspecialchars($product['price']); ?></p>
                            <p><strong>Stock:</strong> <?php echo htmlspecialchars($product['amount_in_stock'] ?? '0'); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($product['city'] ?? 'Not assigned'); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No products found.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($low_stock_warnings)): ?>
        <div class="sidebar">
            <div class="warning-box">
                <h3>Low Stock Alert</h3>
                <p>Below threshold (<?php echo $min_stock_threshold; ?>):</p>
                <ul>
                    <?php foreach ($low_stock_warnings as $warning): ?>
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