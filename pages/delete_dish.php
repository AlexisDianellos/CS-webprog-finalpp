<?php include "../includes/header.php"; ?>

<?php
require_once "../includes/db.php";

if (!isset($_SESSION["user_id"])) {
    die("Μη εξουσιοδοτημένη πρόσβαση.");
}

$dish_id = $_POST["dish_id"];
$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("DELETE FROM dishes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $dish_id, $user_id);
$stmt->execute();

header("Location: my_dishes.php");
exit();
?>
<?php include "../includes/footer.php"; ?>

