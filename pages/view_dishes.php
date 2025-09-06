<?php include "../includes/header.php"; ?>

<?php
require_once "../includes/db.php";

$current_user_id = $_SESSION["user_id"] ?? null;

// αρχικοποιηση πινακων για where και παραμετρους
$where = [];
$params = [];
$param_types = "";
// φιλτρο με username ακριβης αντιστοιχια
if (!empty($_GET["username"])) {
    $where[] = "u.username = ?";
    $params[] = trim($_GET["username"]);
    $param_types .= "s";
}
// φιλτρο για κυρια κατηγορια με ακριβη τιμη
if (!empty($_GET["main_category"])) {
    $where[] = "d.main_category = ?";
    $params[] = $_GET["main_category"];
    $param_types .= "s";
}
// φιλτρο με τιτλο με like για μερικη αντιστοιχια
if (!empty($_GET["title"])) {
    $where[] = "d.title LIKE ?";
    $params[] = '%' . $_GET["title"] . '%';
    $param_types .= "s";
}
// φιλτρο για δευτερευουσες κατηγοριες με or στο find_in_set
if (!empty($_GET["secondary"])) {
    $secondary_ids = array_map('intval', (array)$_GET["secondary"]);
    if (!empty($secondary_ids)) {
        // μετατροπη ids σε ονοματα κατηγοριων
        $placeholders = implode(',', array_fill(0, count($secondary_ids), '?'));
        $types = str_repeat('i', count($secondary_ids));

        $name_stmt = $conn->prepare("SELECT name FROM secondary_categories WHERE id IN ($placeholders)");
        $name_stmt->bind_param($types, ...$secondary_ids);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        // κανονικοποιηση ονοματων σε lower και χωρις κενα
        $secondary_names = [];
        while ($r = $name_result->fetch_assoc()) {
            $secondary_names[] = strtolower(str_replace(' ', '', $r['name']));
        }
        $name_stmt->close();
        // δημιουργια or ορων με find_in_set πανω σε lower και replace
        if (!empty($secondary_names)) {
            $or_parts = [];
            foreach ($secondary_names as $nm) {
                // Αφαιρουμε κενα
                $or_parts[] = "FIND_IN_SET(?, REPLACE(LOWER(d.secondary_categories), ' ', ''))";
                $params[] = $nm;
                $param_types .= "s";
            }
            $where[] = '(' . implode(' OR ', $or_parts) . ')';
        }
    }
}
// συνθεση where clause με and μεταξυ των φιλτρων
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// επιλογη ταξινομησης αναλογα με ζητηση χρηστη
$order_by = "d.published_at DESC";
switch ($_GET["sort"] ?? '') {
    case "most_up":
        $order_by = "upvotes DESC";
        break;
    case "most_down":
        $order_by = "downvotes DESC";
        break;
    case "most_ingredients":
        $order_by = "ingredient_count DESC";
        break;
    case "least_ingredients":
        $order_by = "ingredient_count ASC";
        break;
    case "oldest":
        $order_by = "d.published_at ASC";
        break;
    case "recent":
    default:
        $order_by = "d.published_at DESC";
        break;
}

// βασικο select με join χρηστων και αθροιση ψηφων
$sql = "
SELECT d.*, u.username,
  SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE 0 END) AS upvotes,
  SUM(CASE WHEN v.vote_type = 'down' THEN 1 ELSE 0 END) AS downvotes,
  JSON_LENGTH(d.ingredients) AS ingredient_count
FROM dishes d
JOIN users u ON d.user_id = u.id
LEFT JOIN votes v ON d.id = v.dish_id
$where_clause
GROUP BY d.id
ORDER BY $order_by
";

// προετοιμασια prepared statement και δεσιμο παραμετρων
$stmt = $conn->prepare($sql);
if ($param_types) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Dishes</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container">
    <h2>Πιάτα Χρηστών</h2>

    <form class="container" method="get">
        <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_GET['username'] ?? '') ?>">
        <input type="text" name="title" placeholder="Τίτλος πιάτου" value="<?= htmlspecialchars($_GET['title'] ?? '') ?>">

        <select name="main_category">
            <option value="">--Κατηγορία--</option>
            <option value="Πρωινό"        <?= (($_GET['main_category'] ?? '') === 'Πρωινό') ? 'selected' : '' ?>>Πρωινό</option>
            <option value="Κυρίως Γεύμα"  <?= (($_GET['main_category'] ?? '') === 'Κυρίως Γεύμα') ? 'selected' : '' ?>>Κυρίως Γεύμα</option>
            <option value="Απογευματινό"  <?= (($_GET['main_category'] ?? '') === 'Απογευματινό') ? 'selected' : '' ?>>Απογευματινό</option>
            <option value="Επιδόρπιο"     <?= (($_GET['main_category'] ?? '') === 'Επιδόρπιο') ? 'selected' : '' ?>>Επιδόρπιο</option>
        </select>

        <div>
            <select name="secondary[]" multiple size="4">
                <?php
                $res = $conn->query("SELECT id, name FROM secondary_categories");
                $selected_sec = (array)($_GET['secondary'] ?? []);
                while ($row = $res->fetch_assoc()):
                    $sel = in_array($row['id'], $selected_sec) ? 'selected' : '';
                ?>
                    <option value="<?= $row['id'] ?>" <?= $sel ?>><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit">Αναζήτηση</button>
    </form>

    <form method="get" class="container" style="margin-top: 20px;">
        <?php
        // search params ωστε να μην χαθουν στο sort
        foreach ($_GET as $key => $val) {
            if ($key !== 'sort') {
                if (is_array($val)) {
                    foreach ($val as $subval) {
                        echo '<input type="hidden" name="'.htmlspecialchars($key).'[]" value="'.htmlspecialchars($subval).'">';
                    }
                } else {
                    echo '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($val).'">';
                }
            }
        }
        ?>
        <label for="sort">Ταξινόμηση:</label>
        <select name="sort" onchange="this.form.submit()">
            <option value="">Επιλογή</option>
            <option value="recent"            <?= (($_GET['sort'] ?? '') === 'recent') ? 'selected' : '' ?>>Πιο πρόσφατα</option>
            <option value="oldest"            <?= (($_GET['sort'] ?? '') === 'oldest') ? 'selected' : '' ?>>Πιο παλιά</option>
            <option value="most_up"           <?= (($_GET['sort'] ?? '') === 'most_up') ? 'selected' : '' ?>>Περισσότερα Upvotes</option>
            <option value="most_down"         <?= (($_GET['sort'] ?? '') === 'most_down') ? 'selected' : '' ?>>Περισσότερα Downvotes</option>
            <option value="most_ingredients"  <?= (($_GET['sort'] ?? '') === 'most_ingredients') ? 'selected' : '' ?>>Περισσότερα υλικά</option>
            <option value="least_ingredients" <?= (($_GET['sort'] ?? '') === 'least_ingredients') ? 'selected' : '' ?>>Λιγότερα υλικά</option>
        </select>
    </form>

    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="dish-card">
            <h3><?= htmlspecialchars($row["title"]) ?> - <small><?= htmlspecialchars($row["username"]) ?></small></h3>
            <p>Κατηγορία: <?= htmlspecialchars($row["main_category"]) ?></p>
            <p>Δευτερεύουσες: <?= htmlspecialchars(str_replace(',', ', ', $row["secondary_categories"])) ?></p>
            <p>Συνολικές θερμίδες: <strong><?= htmlspecialchars($row["total_calories"]) ?></strong></p>
            <p>Ημερομηνία: <?= htmlspecialchars($row["published_at"]) ?></p>
            <p>👍 <?= (int)$row["upvotes"] ?> | 👎 <?= (int)$row["downvotes"] ?> | Υλικά: <?= (int)$row["ingredient_count"] ?></p>

            <h4>Υλικά:</h4>
            <ul>
                <?php foreach ((array)json_decode($row["ingredients"], true) as $ing): ?>
                    <li><?= htmlspecialchars($ing["name"]) ?> - <?= htmlspecialchars($ing["quantity"]) ?>γρ</li>
                <?php endforeach; ?>
            </ul>

            <?php if ($current_user_id && $current_user_id != $row["user_id"]): ?>
                <form method="post" action="vote.php" style="display:inline;">
                    <input type="hidden" name="dish_id" value="<?= (int)$row["id"] ?>">
                    <button name="vote" value="up">👍 Vote Up</button>
                    <button name="vote" value="down">👎 Vote Down</button>
                </form>
            <?php elseif ($current_user_id == $row["user_id"]): ?>
                <p><em>Δεν μπορείς να ψηφίσεις το δικό σου πιάτο.</em></p>
            <?php else: ?>
                <p><em>Πρέπει να συνδεθείς για να ψηφίσεις.</em></p>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>

<?php include "../includes/footer.php"; ?>
