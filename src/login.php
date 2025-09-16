<?php
$servername = "mysql-db";
$username = "alexljn5";
$password = "password";
$dbname = "tools4ever_db";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header("Location: login.html?error=" . urlencode("Database connection failed: " . $conn->connect_error));
    exit();
}

// Get posted form data safely
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

// Validate input
if (empty($user) || empty($pass)) {
    header("Location: login.html?error=" . urlencode("Username and password are required"));
    exit();
}

// Use prepared statement to avoid SQL injection
$stmt = $conn->prepare("SELECT * FROM employees WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists → redirect to dashboard.php
    header("Location: dashboard.php");
    exit();
} else {
    // Invalid login → redirect back with error
    header("Location: login.html?error=" . urlencode("Invalid username or password"));
    exit();
}

$stmt->close();
$conn->close();
?>