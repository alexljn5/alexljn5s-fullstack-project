<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools4Ever Employee Portal</title>
    <link href="styles.css" rel="stylesheet">
    <script src="javascript/headerui.js"></script>
</head>

<body>
    <div class="header-container">
        <header>
            <div class="logo-box">
                <img src="img/logoplaceholder.webp" alt="Tools4Ever Logo">
            </div>
            <div class="date-and-time-box">
                <p>Date and Time</p>
            </div>
            <div class="weather-box">
                <p>Weather</p>
            </div>
        </header>
    </div>
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
                <input type="text" name="username" placeholder="Username/Email" required>
                <label>Password:</label>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
    <div class="footer-container">
        <footer>
            <div class="business-number-box">
                <p>Business Number</p>
            </div>
            <div class="legal-text-box">
                <p>Legal Text</p>
            </div>
            <div class="copyright-box">
                <p>Copyright</p>
            </div>
        </footer>
    </div>
</body>

</html>