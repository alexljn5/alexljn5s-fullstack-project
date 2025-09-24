<?php
session_start(); // Start session

$servername = "mysql-db";
$username = "alexljn5";
$password = "password";
$dbname = "tools4ever_db";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header("Location: index.php?error=" . urlencode("Database connection failed: " . $conn->connect_error));
    exit();
}

// Get posted form data safely
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

// Validate input
if (empty($user) || empty($pass)) {
    header("Location: index.php?error=" . urlencode("Username and password are required"));
    exit();
}

// Use prepared statement to fetch user
$stmt = $conn->prepare("SELECT idemployees, password FROM employees WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // Compare plain-text password (temporary for placeholder)
    if ($pass === $row['password']) {
        // Set session variables
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $row['idemployees'];
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=" . urlencode("Invalid username or password"));
        exit();
    }
} else {
    header("Location: index.php?error=" . urlencode("Invalid username or password"));
    exit();
}

$stmt->close();
$conn->close();
?>