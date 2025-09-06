<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION["user_id"])) {
    echo "<script>alert('Πρέπει να είσαι συνδεδεμένος για να ψηφίσεις.'); window.location.href = 'view_dishes.php';</script>";
    exit();
}

$user_id = $_SESSION["user_id"];
$dish_id = $_POST["dish_id"];
$vote_type = $_POST["vote"];

if (!in_array($vote_type, ["up", "down"])) {
    die("Μη έγκυρη ψήφος.");
}

// ελεγχος αν έχει ήδη ψηφίσει
$stmt = $conn->prepare("SELECT id, vote_type FROM votes WHERE user_id = ? AND dish_id = ?");
$stmt->bind_param("ii", $user_id, $dish_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($vote_id, $existing_vote_type);
$stmt->fetch();

if ($stmt->num_rows > 0) {
    if ($existing_vote_type === $vote_type) {
        // Αν η ίδια ψήφος διαγράφουμε
        $conn->query("DELETE FROM votes WHERE id = $vote_id");
    } else {
        // Αν αλλάζει ψήφο
        $update = $conn->prepare("UPDATE votes SET vote_type = ? WHERE id = ?");
        $update->bind_param("si", $vote_type, $vote_id);
        $update->execute();
    }
} else {
    // Νέα ψήφος
    $insert = $conn->prepare("INSERT INTO votes (user_id, dish_id, vote_type) VALUES (?, ?, ?)");
    $insert->bind_param("iis", $user_id, $dish_id, $vote_type);
    $insert->execute();
}

header("Location: view_dishes.php");
exit();