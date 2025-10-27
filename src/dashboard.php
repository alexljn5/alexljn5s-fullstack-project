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

// Handle stock deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_stock'])) {
    $product_id = trim($_POST['product_id']);
    $location_id = $_POST['location_id'];
    $errors = [];

    try {
        $conn->begin_transaction();

        // Get current quantity to subtract from products
        $check_sql = "SELECT quantity FROM location_has_products WHERE location_idlocation = ? AND products_productid = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $location_id, $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows == 0) {
            throw new Exception("Stock entry for product #$product_id at location #$location_id not found");
        }
        $row = $result->fetch_assoc();
        $quantity_to_remove = (int) $row['quantity'];
        $check_stmt->close();

        // Delete the location-product link
        $delete_sql = "DELETE FROM location_has_products WHERE location_idlocation = ? AND products_productid = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("is", $location_id, $product_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Update total stock in products table
        $update_stock_sql = "UPDATE products SET amount_in_stock = amount_in_stock - ? WHERE productid = ?";
        $update_stock_stmt = $conn->prepare($update_stock_sql);
        $update_stock_stmt->bind_param("is", $quantity_to_remove, $product_id);
        $update_stock_stmt->execute();
        $update_stock_stmt->close();

        $conn->commit();
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Error deleting stock: " . $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Handle form submission for adding/updating products
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $form_mode = $_POST['form_mode'] ?? 'add';
    $errors = [];

    if ($form_mode === 'add') {
        // Logic for adding new product
        $productid = trim($_POST['productid']);
        $type = trim($_POST['type']);
        $manufacturer = trim($_POST['manufacturer']);
        $price = trim($_POST['price']);
        $amount_in_stock = trim($_POST['amount_in_stock']);
        $city = trim(strtolower($_POST['city'])); // Normalize city to lowercase

        // Validate inputs
        if (empty($productid) || empty($type) || empty($manufacturer) || empty($price) || empty($city)) {
            $errors[] = "All fields are required for adding a new product!";
        }
        if (!is_numeric($price) || $price < 0 || $price > 99999999.99) {
            $errors[] = "Price must be a non-negative number and not exceed 99999999.99!";
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

                // Get or insert location (case-insensitive)
                $zipcode = '';
                $location_check_sql = "SELECT idlocation FROM location WHERE LOWER(city) = ?";
                $check_stmt = $conn->prepare($location_check_sql);
                $check_stmt->bind_param("s", $city);
                $check_stmt->execute();
                $location_result = $check_stmt->get_result();
                $check_stmt->close();

                if ($location_result->num_rows > 0) {
                    $location_row = $location_result->fetch_assoc();
                    $location_id = $location_row['idlocation'];
                } else {
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
                $price_float = number_format((float) $price, 2, '.', '');
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
    } else {
        // Logic for updating stock
        $existing_productid = trim($_POST['existing_productid']);
        $additional_stock = trim($_POST['additional_stock']);
        $city = trim(strtolower($_POST['city_update'])); // Normalize city to lowercase

        // Validate inputs
        if (empty($existing_productid) || empty($additional_stock) || empty($city)) {
            $errors[] = "All fields are required for updating stock!";
        }
        if (!is_numeric($additional_stock) || $additional_stock < 0) {
            $errors[] = "Additional stock must be a non-negative number!";
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Check if product exists
                $check_sql = "SELECT productid, price FROM products WHERE productid = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $existing_productid);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows == 0) {
                    throw new Exception("Product does not exist!");
                }
                $product_row = $result->fetch_assoc();
                $price_float = number_format((float) $product_row['price'], 2, '.', '');
                $check_stmt->close();

                // Get or insert location (case-insensitive)
                $zipcode = '';
                $location_check_sql = "SELECT idlocation FROM location WHERE LOWER(city) = ?";
                $check_stmt = $conn->prepare($location_check_sql);
                $check_stmt->bind_param("s", $city);
                $check_stmt->execute();
                $location_result = $check_stmt->get_result();
                $check_stmt->close();

                if ($location_result->num_rows > 0) {
                    $location_row = $location_result->fetch_assoc();
                    $location_id = $location_row['idlocation'];
                } else {
                    $location_sql = "INSERT INTO location (city, zipcode) VALUES (?, ?)";
                    $location_stmt = $conn->prepare($location_sql);
                    $location_stmt->bind_param("ss", $city, $zipcode);
                    $location_stmt->execute();
                    $location_id = $conn->insert_id;
                    $location_stmt->close();
                }

                // Check if location-product link exists
                $link_check_sql = "SELECT quantity FROM location_has_products WHERE location_idlocation = ? AND products_productid = ?";
                $link_check_stmt = $conn->prepare($link_check_sql);
                $link_check_stmt->bind_param("is", $location_id, $existing_productid);
                $link_check_stmt->execute();
                $link_result = $link_check_stmt->get_result();
                $additional_stock_int = (int) $additional_stock;

                if ($link_result->num_rows > 0) {
                    // Update existing link
                    $current_quantity = $link_result->fetch_assoc()['quantity'];
                    $new_quantity = $current_quantity + $additional_stock_int;
                    $link_update_sql = "UPDATE location_has_products SET quantity = ? WHERE location_idlocation = ? AND products_productid = ?";
                    $link_update_stmt = $conn->prepare($link_update_sql);
                    $link_update_stmt->bind_param("iis", $new_quantity, $location_id, $existing_productid);
                    $link_update_stmt->execute();
                    $link_update_stmt->close();
                } else {
                    // Insert new location-product link
                    $link_sql = "INSERT INTO location_has_products (location_idlocation, products_productid, quantity, purchase_price, sale_price) VALUES (?, ?, ?, ?, ?)";
                    $link_stmt = $conn->prepare($link_sql);
                    $link_stmt->bind_param("isidd", $location_id, $existing_productid, $additional_stock_int, $price_float, $price_float);
                    $link_stmt->execute();
                    $link_stmt->close();
                }
                $link_check_stmt->close();

                // Update total stock in products table
                $update_stock_sql = "UPDATE products SET amount_in_stock = amount_in_stock + ? WHERE productid = ?";
                $update_stock_stmt = $conn->prepare($update_stock_sql);
                $update_stock_stmt->bind_param("is", $additional_stock_int, $existing_productid);
                $update_stock_stmt->execute();
                $update_stock_stmt->close();

                $conn->commit();
                header("Location: dashboard.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Error updating stock: " . $e->getMessage();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch products and calculate stock value
$sql = "SELECT p.productid, p.type, p.manufacturer, p.price, p.amount_in_stock, 
               GROUP_CONCAT(l.city) AS cities, 
               GROUP_CONCAT(lhp.quantity) AS quantities,
               GROUP_CONCAT(l.idlocation) AS location_ids
        FROM products p 
        LEFT JOIN location_has_products lhp ON p.productid = lhp.products_productid 
        LEFT JOIN location l ON lhp.location_idlocation = l.idlocation 
        GROUP BY p.productid, p.type, p.manufacturer, p.price, p.amount_in_stock";
$result = $conn->query($sql);

$products = [];
$low_stock_warnings = [];
$min_stock_threshold = 10;
$total_stock_value = 0.00;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
        $quantities = explode(',', $row['quantities'] ?? '0');
        $cities = explode(',', $row['cities'] ?? 'Unknown');
        foreach ($quantities as $index => $quantity) {
            if ($quantity <= $min_stock_threshold) {
                $city = $cities[$index] ?? 'Unknown';
                $key = $row['type'] . "_" . $city;
                $low_stock_warnings[$key] = "{$row['type']} (Stock: {$quantity}, {$city})";
            }
            $total_stock_value += (float) $quantity * (float) ($row['price'] ?? 0);
        }
    }
}

// Fetch cities for datalist
$cities_query = "SELECT DISTINCT city FROM location WHERE city != ''";
$cities_result = $conn->query($cities_query);
$cities = [];
if ($cities_result->num_rows > 0) {
    while ($city_row = $cities_result->fetch_assoc()) {
        $cities[] = $city_row['city'];
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
            <form method="POST" action="dashboard.php" onsubmit="return confirmFormSubmission()">
                <input type="hidden" name="add_product" value="1">
                <label for="form_mode">Action:</label>
                <select id="form_mode" name="form_mode" onchange="toggleFormFields()">
                    <option value="add">Add New Product</option>
                    <option value="update">Update Existing Stock</option>
                </select>

                <!-- Fields for updating stock -->
                <div id="update_stock_fields" style="display: none;">
                    <label for="existing_productid">Select Product:</label>
                    <select id="existing_productid" name="existing_productid">
                        <option value="">Select a product</option>
                        <?php
                        $product_query = "SELECT productid FROM products";
                        $product_result = $conn->query($product_query);
                        if ($product_result->num_rows > 0) {
                            while ($row = $product_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['productid']) . '">' . htmlspecialchars($row['productid']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <label for="additional_stock">Additional Stock:</label>
                    <input type="number" id="additional_stock" name="additional_stock" min="0"
                        placeholder="Enter additional stock">
                    <label for="city_update_select">Select City:</label>
                    <select id="city_update_select"
                        onchange="document.getElementById('city_update').value = this.value;">
                        <option value="">Select a city</option>
                        <option value="Almere">Almere</option>
                        <option value="Amsterdam">Amsterdam</option>
                        <option value="Utrecht">Utrecht</option>
                    </select>
                    <label for="city_update">Location (City):</label>
                    <input type="text" id="city_update" name="city_update" list="cityList"
                        placeholder="Select or enter city">
                    <datalist id="cityList">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Fields for adding new product -->
                <div id="add_product_fields">
                    <label for="productid">Product Name:</label>
                    <input type="text" id="productid" name="productid" required>
                    <label for="type">Type:</label>
                    <input type="text" id="type" name="type" required>
                    <label for="manufacturer">Manufacturer:</label>
                    <input type="text" id="manufacturer" name="manufacturer" required>
                    <label for="price">Price:</label>
                    <input type="number" step="0.01" min="0" max="99999999.99" id="price" name="price" required>
                    <label for="amount_in_stock">Stock:</label>
                    <input type="number" id="amount_in_stock" name="amount_in_stock" min="0" required>
                    <label for="city_select">Select City:</label>
                    <select id="city_select" onchange="document.getElementById('city').value = this.value;">
                        <option value="">Select a city</option>
                        <option value="Almere">Almere</option>
                        <option value="Amsterdam">Amsterdam</option>
                        <option value="Utrecht">Utrecht</option>
                    </select>
                    <label for="city">Location (City):</label>
                    <input type="text" id="city" name="city" list="cityList" required
                        placeholder="Select or enter new city">
                    <datalist id="cityList">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <button type="submit">Save</button>
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
                            <p><strong>Total Stock:</strong> <?php echo htmlspecialchars($product['amount_in_stock'] ?? '0'); ?>
                            </p>
                            <p><strong>Locations:</strong>
                                <?php
                                $cities = explode(',', $product['cities'] ?? 'Not assigned');
                                $quantities = explode(',', $product['quantities'] ?? '0');
                                $location_ids = explode(',', $product['location_ids'] ?? '');
                                $location_stock = [];
                                foreach ($cities as $index => $city) {
                                    $quantity = $quantities[$index] ?? '0';
                                    $location_id = $location_ids[$index] ?? '';
                                    $location_stock[] = htmlspecialchars("$city: $quantity") .
                                        ($location_id ? ' <form method="POST" action="dashboard.php" style="display:inline;" onsubmit="return confirmDeleteStock(\'' . htmlspecialchars($product['productid']) . '\', \'' . $city . '\')">' .
                                            '<input type="hidden" name="delete_stock" value="1">' .
                                            '<input type="hidden" name="location_id" value="' . $location_id . '">' .
                                            '<input type="hidden" name="product_id" value="' . htmlspecialchars($product['productid']) . '">' .
                                            '<button type="submit" class="confirm-button">Delete Stock</button>' .
                                            '</form>' : '');
                                }
                                echo implode(', ', $location_stock) ?: 'Not assigned';
                                ?>
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