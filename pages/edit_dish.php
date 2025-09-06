<?php include "../includes/header.php"; ?>
<?php
require_once "../includes/db.php";

if (!isset($_SESSION["user_id"])) {
    die("Μη εξουσιοδοτημένη πρόσβαση."); // ελεγχος αν ειναι logged in
}

$dish_id = $_GET["dish_id"];
$user_id = $_SESSION["user_id"];

// βρισκουμε το πιατο που ανηκει στον user
$stmt = $conn->prepare("SELECT * FROM dishes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $dish_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$dish = $result->fetch_assoc();
$stmt->close();

if (!$dish) {
    die("Πιάτο δεν βρέθηκε."); // αν δεν υπαρχει σταματαμε
}

// παιρνουμε ολα τα υλικα απο τη βαση
$ingredients_res = $conn->query("SELECT * FROM ingredients");
$ingredients_array = [];
while ($row = $ingredients_res->fetch_assoc()) {
    $ingredients_array[] = $row;
}

// μετατρεπουμε τα υλικα του πιατου απο json σε array
$dish_ingredients = json_decode($dish["ingredients"], true) ?? [];

// βρισκουμε to id της κυριας κατηγοριας απο το name που εχει το πιατο
$current_main_id = null;
$cur_stmt = $conn->prepare("SELECT id FROM main_categories WHERE name = ?");
$cur_stmt->bind_param("s", $dish['main_category']);
$cur_stmt->execute();
$cur_stmt->bind_result($current_main_id);
$cur_stmt->fetch();
$cur_stmt->close();

// ετοιμαζουμε τα ονοματα των δευτερευουσων που εχει το πιατο (csv -> array)
// Φέρνω όλες τις secondary για mapping
$sec_res_all = $conn->query("SELECT id, name FROM secondary_categories");
$secondary_all = [];
$idByName = [];
while ($r = $sec_res_all->fetch_assoc()) {
    $secondary_all[] = $r;
    $idByName[$r['name']] = (int)$r['id'];
}

// Μετατροπή του stored CSV (μπορεί να είναι IDs ή ονόματα) -> IDs
$selected_secondary_ids = [];
$raw_csv = trim((string)$dish['secondary_categories']);
if ($raw_csv !== '') {
    foreach (explode(',', $raw_csv) as $part) {
        $part = trim($part);
        if ($part === '') continue;

        if (ctype_digit($part)) {
            // ήδη ID
            $selected_secondary_ids[] = (int)$part;
        } else {
            // όνομα -> ID (αν υπάρχει mapping)
            if (isset($idByName[$part])) {
                $selected_secondary_ids[] = $idByName[$part];
            }
        }
    }
}
$selected_secondary_ids = array_values(array_unique($selected_secondary_ids));

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Πιάτου</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
    // κραταμε θερμιδες και μοναδες καθε υλικου σε js object
    let ingredientsData = {};
    <?php foreach ($ingredients_array as $ing): ?>
        ingredientsData["<?= addslashes($ing['name']) ?>"] = {
            cal: <?= (float)$ing['calories_per_unit'] ?>,
            unit: "<?= addslashes($ing['unit_description']) ?>"
        };
    <?php endforeach; ?>

    // function για υπολογισμο θερμιδων καθε φορα που αλλαζει κατι
    function updateCalories() {
        let total = 0;
        document.querySelectorAll(".ingredient-row").forEach(row => {
            const sel = row.querySelector("select");
            const qty = parseFloat(row.querySelector("input").value) || 0;
            const name = sel.value;
            if (ingredientsData[name]) {
                total += (ingredientsData[name].cal / 100) * qty;
            }
        });
        document.getElementById("total_calories").innerText = total.toFixed(2);
        document.getElementById("hidden_calories").value = total.toFixed(2);
    }
    </script>
</head>
<body>
    <h2 class="container">Επεξεργασία Πιάτου</h2>
    <!-- φορμα που στελνει updated δεδομενα -->
    <form class="container" method="post" action="update_dish.php">
        <input type="hidden" name="dish_id" value="<?= (int)$dish['id'] ?>">

        <div>
            <label>Τίτλος:</label><br>
            <input type="text" name="title" value="<?= htmlspecialchars($dish['title']) ?>">
        </div><br>

        <div>
            <label>Κύρια Κατηγορία:</label><br>
            <select name="main_category" required>
                <?php
                // φορτωνουμε ολες τις κυριες κατηγοριες απο τη βαση (value=ID, text=NAME)
                $main_res = $conn->query("SELECT id, name FROM main_categories ORDER BY name");
                while ($m = $main_res->fetch_assoc()):
                    $sel = ((int)$m['id'] === (int)$current_main_id) ? 'selected' : '';
                ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $sel ?>><?= htmlspecialchars($m['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div><br>

        <div>
            <label>Δευτερεύουσες Κατηγορίες:</label><br>
                <select name="secondary_categories[]" multiple size="4">
                    <?php foreach ($secondary_all as $s):
                        $selected = in_array((int)$s['id'], $selected_secondary_ids, true) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $selected ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
        </div><br>

        <div>
            <label>Υλικά:</label>
        </div>

        <?php foreach ($dish_ingredients as $i => $ing): ?>
            <div class="ingredient-row">
                <select name="ingredient_names[]" onchange="updateCalories()">
                    <option>-- Επιλογή --</option>
                    <?php foreach ($ingredients_array as $row): ?>
                        <option value="<?= htmlspecialchars($row['name']) ?>" <?= ($row['name'] === $ing['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['unit_description']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="ingredient_quantities[]" value="<?= htmlspecialchars($ing['quantity']) ?>" step="any" min="0" oninput="updateCalories()">
            </div>
        <?php endforeach; ?>

        <p>Συνολικές Θερμίδες: <span id="total_calories"><?= htmlspecialchars($dish['total_calories']) ?></span></p>
        <input type="hidden" name="total_calories" id="hidden_calories" value="<?= htmlspecialchars($dish['total_calories']) ?>">

        <br><button type="submit">Αποθήκευση</button>
    </form>
</body>
</html>

<?php include "../includes/footer.php"; ?>
