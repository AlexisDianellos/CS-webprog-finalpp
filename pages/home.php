<?php include "../includes/header.php"; ?>

<?php
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require_once "../includes/db.php";

//φερνω υλικα απο βαση
$ingredients_res = $conn->query("SELECT * FROM ingredients");
//φερνω κατηγοριες απο βαση
$main_categories_res = $conn->query("SELECT * FROM main_categories");
$secondary_categories_res = $conn->query("SELECT * FROM secondary_categories");
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Δημιουργία Πιάτου</title>
    <link rel="stylesheet" href="../css/style.css">
<script>

let ingredientsData = {
<?php
$ingredients_res->data_seek(0);
while ($row = $ingredients_res->fetch_assoc()) {
    $name = addslashes($row['name']);
    $cal = $row['calories_per_unit'];
    $unit = addslashes($row['unit_description']);
    echo "'$name': {cal: $cal, unit: '$unit'},\n";
}
?>
};

<?php
//PHP πινακα - JS αντικειμενο
while ($row = $ingredients_res->fetch_assoc()) {
    $name = $row["name"];
    $cal = $row["calories_per_unit"];
    $unit = $row["unit_description"];
    echo "ingredientsData['$name'] = {cal: $cal, unit: '$unit'};\n";
}
?>

function updateCalories() {
    let total = 0;
    const rows = document.querySelectorAll(".ingredient-row");

    rows.forEach(row => {
        const select = row.querySelector("select");
        const input = row.querySelector("input");
        const qty = parseFloat(input.value) || 0;
        const ingredientName = select.value;

        if (ingredientName in ingredientsData) {
            const ing = ingredientsData[ingredientName];
            let calories = 0;

            if (ing.unit.includes("100γρ")) {
                // αν 100γρ διαιρούμε
                calories = (qty / 100) * ing.cal;
            } else {
                // αν μοναδα είναι 1 τεμάχιο 1 μπολ κλπ τοτε πολ
                calories = qty * ing.cal;
            }

            total += calories;
        }
    });

    document.getElementById("total_calories").innerText = total.toFixed(2);
    document.getElementById("hidden_calories").value = total.toFixed(2);
}

function countValidIngredients() {
    const rows = document.querySelectorAll(".ingredient-row");
    let count = 0;

    rows.forEach(row => {
        const select = row.querySelector("select");
        const input = row.querySelector("input");

        if (select.value.trim() !== "" && input.value.trim() !== "") {
            count++;
        }
    });

    return count;
}

document.querySelector("form").addEventListener("submit", function(e) {
    if (countValidIngredients() < 3) {
        alert("Πρέπει να επιλέξετε τουλάχιστον 3 υλικά.");
        e.preventDefault(); // μπλοκαρει submit
    }
});

</script>
</head>
<body>
<div class="container">
    <h2>Δημιουργία Πιάτου</h2>
</div>


<form class ="container" method="post" action="save_dish.php">
    <label>Τίτλος Πιάτου:</label><br>
    <input type="text" name="title" required><br><br>

    <label>Κύρια Κατηγορία:</label><br>
    <select name="main_category" required>
        <option value="">-- Επιλογή κατηγορίας --</option>
        <?php while ($row = $main_categories_res->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <label>Δευτερεύουσες Κατηγορίες (κρατήστε Ctrl για πολλαπλή επιλογή):</label><br>
    <select name="secondary_categories[]" multiple size="4">
        <?php while ($row = $secondary_categories_res->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <label>Υλικά (τουλάχιστον 3):</label><br>
    <?php
    $ingredients_res->data_seek(0); // reset pointer
    for ($i = 0; $i < 5; $i++): ?>
        <div class="ingredient-row">
            <select name="ingredient_names[]" onchange="updateCalories()">
                <option value="">-- Επιλογή υλικού --</option>
                <?php while ($row = $ingredients_res->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['name']) ?>">
                        <?= htmlspecialchars($row['name']) ?> (<?= $row['unit_description'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
            <input type="number" name="ingredient_quantities[]" placeholder="Ποσότητα σε γραμμάρια" step=0.01 min="0" inputmode="decimal" oninput="updateCalories()">
        </div>
        <?php $ingredients_res->data_seek(0); ?>
    <?php endfor; ?>

    <br>
    <p>Συνολικές Θερμίδες: <strong id="total_calories">0</strong></p>
    <input type="hidden" name="total_calories" id="hidden_calories">

    <button type="submit">Δημιουργία Πιάτου</button>
</form>
</body>
</html>

<?php include "../includes/footer.php"; ?>