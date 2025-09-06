<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require_once "../includes/db.php";

$user_id   = (int) $_SESSION["user_id"];
$dish_id   = (int) ($_POST["dish_id"] ?? 0);
$title     = trim($_POST["title"] ?? "");
$main_id   = (int) ($_POST["main_category"] ?? 0);
$sec_ids   = array_map('intval', $_POST["secondary_categories"] ?? []);
$names     = $_POST["ingredient_names"] ?? [];
$quant_raw = $_POST["ingredient_quantities"] ?? [];
$calories  = (float) str_replace(',', '.', (string)($_POST["total_calories"] ?? 0));

// έλεγχος
if ($dish_id <= 0) {
    die("Μη έγκυρο πιάτο.");
}
if (count(array_filter($names)) < 3) {
    echo "<script>alert('Πρέπει να επιλέξετε τουλάχιστον 3 υλικά.'); window.history.back();</script>";
    exit();
}

// βρίσκω την κύρια κατηγορία (name) από το id, γιατί στο dishes αποθηκεύεις name
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

// secondary ως CSV από IDs (για να δουλεύει το FIND_IN_SET στον View)
// secondary απο IDs σε CSV ΟΝΟΜΑΤΩΝ (χωρίς κενά μετά το κόμμα)
$secondary_csv = "";
if (!empty($sec_ids)) {
    $placeholders = implode(',', array_fill(0, count($sec_ids), '?'));
    $types = str_repeat('i', count($sec_ids));
    $sql = "SELECT name FROM secondary_categories WHERE id IN ($placeholders)";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$sec_ids);
    $st->execute();
    $res = $st->get_result();

    $secondary_names = [];
    while ($r = $res->fetch_assoc()) {
        $secondary_names[] = trim($r['name']);
    }
    $st->close();

    //οχι εξτρα κενή γύρω από τα κόμματα, για σωστό FIND_IN_SET
    $secondary_csv = implode(',', $secondary_names); //πχVegan,Lactose Free
}

// περναω τα υλικα σε array με ονομα και ποσοτητα ωστε να τα κανω json και δεχομαι και δεκαδικες τιμες στις ποσοτητες
$ingredients = [];
for ($i = 0; $i < count($names); $i++) {
    $n = trim($names[$i] ?? '');
    $q_raw = (string)($quant_raw[$i] ?? '');
    $q = (float) str_replace(',', '.', $q_raw);
    if ($n !== '' && $q_raw !== '') {
        $ingredients[] = ["name" => $n, "quantity" => $q];
    }
}
$ingredients_json = json_encode($ingredients, JSON_UNESCAPED_UNICODE);

// UPDATE
$upd = $conn->prepare("
    UPDATE dishes
       SET title = ?,
           main_category = ?,
           secondary_categories = ?,
           ingredients = ?,
           total_calories = ?
     WHERE id = ? AND user_id = ?
");
$upd->bind_param(
    "ssssdii",
    $title,
    $main_name,
    $secondary_csv,
    $ingredients_json,
    $calories,
    $dish_id,
    $user_id
);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    header("Location: my_dishes.php?updated=1");
    exit();
} else {
    echo "<script>alert('Πρόβλημα κατά την ενημέρωση.'); window.history.back();</script>";
    exit();
}
