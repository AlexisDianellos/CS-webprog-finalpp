<?php include "../includes/header.php"; ?>

<?php
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

// φερνουμε τα πιατα του user ταξινομημενα κατα ημερομηνια
$result = $conn->prepare("SELECT * FROM dishes WHERE user_id = ? ORDER BY published_at DESC");
$result->bind_param("i", $user_id);
$result->execute();
$dishes = $result->get_result();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <link rel="stylesheet" href="../css/style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Τα Πιάτα Μου</title>
</head>
<body>
<div class="container">
    <h2>Τα Πιάτα Μου</h2>

<?php while ($row = $dishes->fetch_assoc()): ?>
    <div  class="dish-card container">
        <h3><?= htmlspecialchars($row["title"]) ?></h3>
        <p>Κατηγορία: <?= htmlspecialchars($row["main_category"]) ?></p>
        <p>Δευτερεύουσες: 
        <?php
        // αν οι κατηγοριες ειναι αποθηκευμενες σε json array
        $cats = json_decode($row["secondary_categories"], true);
        if (is_array($cats)) {
            echo htmlspecialchars(implode(', ', $cats));
        } else {
            echo htmlspecialchars($row["secondary_categories"]);
        }
        ?>
        </p>

        <p>Ημερομηνία: <?= $row["published_at"] ?></p>
        <p>Θερμίδες: <?= $row["total_calories"] ?></p>
        <ul>
            <?php foreach (json_decode($row["ingredients"], true) as $ing): ?>
                <li><?= $ing["name"] ?> - <?= $ing["quantity"] ?> γρ</li>
            <?php endforeach; ?>
        </ul>

        <?php
        // delete button παντα ενεργο
        ?>
        <form action="delete_dish.php" method="post" onsubmit="return confirm('Είσαι σίγουρος;');" style="display:inline;">
            <input type="hidden" name="dish_id" value="<?= $row["id"] ?>">
            <button type="submit">Delete</button>
        </form>

        <?php
        // edit button μονο αν το πιατο ειναι 30 ημερων
        $created_at = new DateTime($row["published_at"]);
        $now = new DateTime();
        $interval = $created_at->diff($now)->days;
        if ($interval <= 30): ?>
            <form action="edit_dish.php" method="get" style="display:inline;">
                <input type="hidden" name="dish_id" value="<?= $row["id"] ?>">
                <button type="submit">Edit</button>
            </form>
        <?php else: ?>
            <p><em>Δεν μπορείς να επεξεργαστείς πιάτο άνω των 30 ημερών.</em></p>
        <?php endif; ?>
    </div>
<?php endwhile; ?>
</div>
</body>
</html>

<?php include "../includes/footer.php"; ?>