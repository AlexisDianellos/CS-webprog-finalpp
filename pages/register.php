<?php include "../includes/header.php"; ?>

<?php
require_once "../includes/db.php";

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // έλεχος/val
    if (!preg_match("/^[a-zA-Zα-ωΑ-Ω\s]+$/u", $first_name)) {
        $errors[] = "Το όνομα πρέπει να περιέχει μόνο χαρακτήρες";
    }
    if (!preg_match("/^[a-zA-Zα-ωΑ-Ω\s]+$/u", $last_name)) {
        $errors[] = "Το επώνυμο πρέπει να περιέχει μόνο χαρακτήρες";
    }
    if (strpos($email, '@') === false) {
        $errors[] = "Το email πρέπει να περιέχει το σύμβολο @.";
    }
    if (strlen($password) < 4 || strlen($password) > 10 || !preg_match("/[0-9]/", $password)) {
        $errors[] = "Ο κωδικός πρέπει να έχει 4-10 χαρακτήρες και τουλάχιστον έναν αριθμό";
    }

    // έλεγχος για μοναδικότητα
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errors[] = "Το username ή το email χρησιμοποιείται ήδη";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $first_name, $last_name, $username, $email, $password);
        if ($stmt->execute()) {
            $success = "Εγγραφή επιτυχής";
        } else {
            $errors[] = "Σφάλμα κατά την εγγραφή";
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Εγγραφή</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container">    
    <h2>Εγγραφή</h2>
</div>
<?php foreach ($errors as $e): ?>
    <p style="color:red;"><?php echo $e; ?></p>
<?php endforeach; ?>

<?php if ($success): ?>
    <div class="container">
        <p><?php echo $success; ?></p>
        <a href="login.php">Μετάβαση σε login</a>
    </div>
<?php endif; ?>

<form class ="container" method="post">
    <input type="text" name="first_name" placeholder="Όνομα" required><br>
    <input type="text" name="last_name" placeholder="Επώνυμο" required><br>
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="password" placeholder="Κωδικός" required><br>
    <button type="submit">Εγγραφή</button>
</form>
</body>
</html>

<?php include "../includes/footer.php"; ?>
