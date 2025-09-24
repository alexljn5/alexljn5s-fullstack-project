<?php
session_start(); // Start session for login status
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools4Ever Employee Login</title>
    <link href="styles.css" rel="stylesheet">
</head>

<body>
    <?php include 'php/header.php'; ?>
    <div class="input-container">
        <div class="welcome-box">
            <p>Welcome to the Tools4Ever Employee Portal!</p>
        </div>
        <?php if (isset($_GET['error'])): ?>
            <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="input-boxes">
                <label>Username:</label>
                <input type="text" name="username" placeholder="Username" required>
                <label>Password:</label>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
    <?php include 'php/footer.php'; ?>
</body>

</html>