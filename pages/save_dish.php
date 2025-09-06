<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require_once "../includes/db.php";

$title = $_POST["title"];
$main_id = $_POST["main_category"];                 // id main_categories
$secondary_ids = $_POST["secondary_categories"] ?? []; // ID secondary_categories ειναι array
$ingredient_names = $_POST["ingredient_names"];
$ingredient_quantities = $_POST["ingredient_quantities"];
$calories = $_POST["total_calories"];

// τουλ.3 υλικα
if (count(array_filter($ingredient_names)) < 3) {
    echo "<script>alert('Πρέπει να επιλέξετε τουλάχιστον 3 υλικά.'); window.history.back();</script>";
    exit();
}
//απο μεινid σε μειν name
$main_name = null;
$stmt = $conn->prepare("SELECT name FROM main_categories WHERE id = ?");
$stmt->bind_param("i", $main_id);
$stmt->execute();
$stmt->bind_result($main_name);
$stmt->fetch();
$stmt->close();

if (!$main_name) {
    echo "<script>alert('Μη έγκυρη κύρια κατηγορία.'); window.history.back();</script>";
    exit();
}

// secondary_id σε array
$secondary_names = [];
if (!empty($secondary_ids)) {
    // placeholder (?, ?, ...) για IN
    $placeholders = implode(',', array_fill(0, count($secondary_ids), '?'));
    // τυποι για bind ολα int
    $types = str_repeat('i', count($secondary_ids));

    $sql = "SELECT name FROM secondary_categories WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$secondary_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $secondary_names[] = $r['name'];
    }
    $stmt->close();
}
$secondary_csv = implode(', ', $secondary_names);

$ingredients = [];
for ($i = 0; $i < count($ingredient_names); $i++) {
    $name = trim($ingredient_names[$i] ?? '');
    $qty  = trim($ingredient_quantities[$i] ?? '');
    if ($name !== '' && $qty !== '') {
        $ingredients[] = ["name" => $name, "quantity" => $qty];
    }
}
$ingredients_json = json_encode($ingredients, JSON_UNESCAPED_UNICODE);

$success = false;
$stmt = $conn->prepare("
    INSERT INTO dishes (user_id, title, main_category, secondary_categories, ingredients, total_calories)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "issssd",
    $_SESSION["user_id"],
    $title,
    $main_name,
    $secondary_csv,
    $ingredients_json,
    $calories
);
if ($stmt->execute()) { $success = true; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αποθήκευση Πιάτου</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include "../includes/header.php"; ?>
<div class="container">
    <?php if ($success): ?>
        <h2>Πιάτο δημιουργήθηκε επιτυχώς!</h2>
        <p><a href="home.php"><button>Επιστροφή στη δημιουργία</button></a></p>
        <p><a href="my_dishes.php"><button>Δες τα πιάτα σου</button></a></p>
    <?php else: ?>
        <h2 style="color:red;">Σφάλμα κατά την αποθήκευση του πιάτου.</h2>
        <a href="home.php"><button>Επιστροφή</button></a>
    <?php endif; ?>
</div>
<?php include "../includes/footer.php"; ?>
</body>
</html>
