<?php
// header.php: Reusable header for Tools4Ever pages with date/time script
// Shows sign-out link only for logged-in users
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<div class="header-container">
    <header>
        <div class="logo-box">
            <img src="/img/logoplaceholder.webp" alt="Placeholder Logo">
        </div>
        <div class="date-and-time-box">
            <p>Loading date and time...</p>
        </div>
        <div class="weather-box">
            <p>Weather</p>
        </div>
        <?php if ($is_logged_in): ?>
            <div class="signout-box">
                <a href="logout.php">Sign out</a>
            </div>
        <?php endif; ?>
    </header>
</div>
<script src="/javascript/headerui.js"></script>