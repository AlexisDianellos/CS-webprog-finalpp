<?php include "../includes/header.php"; ?>

<?php
require_once "../includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $db_pass);
    $stmt->fetch();

    if ($stmt->num_rows === 1 && $password === $db_pass) {
        // Είσοδος: Sessions + Cookies
        $_SESSION["user_id"] = $id;
        $_SESSION["username"] = $username;
        setcookie("user_id", $id, time() + 3600, "/"); // 1 ώρα
        header("Location: home.php");
        exit();
    } else {
        $error = "Λάθος username ή password.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container">
    <h2>Σύνδεση</h2>
<div>
<?php if ($error): ?><p style="color:red;"><?php echo $error; ?></p><?php endif; ?>

<form class ="container" method="post">
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Κωδικός" required><br>
    <button type="submit">Σύνδεση</button>
</form>

<p>Δεν έχεις λογαριασμό; <a href="register.php">Κάνε εγγραφή</a></p>
</body>
</html>

<?php include "../includes/footer.php"; ?>