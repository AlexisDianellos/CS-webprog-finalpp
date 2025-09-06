<?php

$host = "localhost"; 
$user = "root";
$pass = "";
$dbname = "diet_ds";
//δημ συνδεσης
$conn = new mysqli($host, $user, $pass, $dbname);
//ελεγχος αν συνδθηκε
if ($conn->connect_error) {
    die("Αποτυχία σύνδεσης στη βάση: " . $conn->connect_error);
}
//για ελληνικ
$conn->set_charset("utf8mb4");
?>
