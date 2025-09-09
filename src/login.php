<?php
$servername = "mysql-db"; // MySQL container hostname
$username = "alexljn5";
$password = "password";
$dbname = "tools4ever_db";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get posted form data safely
$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';

// Use prepared statement to avoid SQL injection
$stmt = $conn->prepare("SELECT * FROM employees WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists → redirect to test.html
    header("Location: test.html");
    exit();
} else {
    // Invalid login → show error
    echo "Invalid username or password.";
}

$stmt->close();
$conn->close();
?>