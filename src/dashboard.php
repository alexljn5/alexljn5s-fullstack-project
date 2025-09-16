<?php
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

    // Basic validation
    if (!empty($productid) && !empty($type) && !empty($manufacturer) && !empty($price)) {
        $sql = "INSERT INTO products (productid, type, manufacturer, price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $productid, $type, $manufacturer, $price);

        if ($stmt->execute()) {
            // Redirect to avoid form resubmission
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<p style='color: red;'>Error adding product: " . $conn->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>All fields are required!</p>";
    }
}

// Query to fetch all products
$sql = "SELECT productid, type, manufacturer, price FROM products";
$result = $conn->query($sql);

// Store products in an array
$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools4Ever Dashboard!</title>
    <link href="styles.css" rel="stylesheet">
    <script src="javascript/dashboard.js"></script>
    <script src="javascript/searchbar.js"></script>
</head>

<body>
    <div class="header-container">
        <header>
            <div class="logo-box">
                <img src="img/logoplaceholder.webp" alt="Placeholder Logo">
            </div>
            <div class="signout-box">
                <a href="logout.php">Sign out</a>
            </div>
        </header>
    </div>
    <div class="search-bar-container">
        <form method="POST" action="">
            <input type="text" id="searchbar" placeholder="Search by type or ID">
        </form>
    </div>
    <div class="addproductbutton">
        <button type="button" onclick="toggleForm()">Add Product</button>
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
                <button type="submit">Save Product</button>
                <button type="button" onclick="toggleForm()">Cancel</button>
            </form>
        </div>
    </div>
    <div class="input-container">
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
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No products found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer-container">
        <footer>
            <div class="business-number-box">
                <p>Business number</p>
            </div>
            <div class="legal-text-box">
                <p>Legal text</p>
            </div>
            <div class="copyright-box">
                <p>Copyright</p>
            </div>
        </footer>
    </div>
</body>

</html>