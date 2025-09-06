<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<nav>
    <span class="hamburger" onclick="document.querySelector('nav ul').classList.toggle('show')">☰</span>
    <ul>
        <li><a href="home.php">Home</a></li>
        <li><a href="my_dishes.php">My Dishes</a></li>
        <li><a href="view_dishes.php">View Dishes</a></li>
        <?php if (isset($_SESSION["user_id"])): ?>
            <li><a href="../logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
