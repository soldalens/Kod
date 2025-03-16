<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

// Skapa anslutning
$conn = new mysqli($servername, $username, $password, $database);

// Kontrollera anslutning
if ($conn->connect_error) {
    die("Koppling misslyckades: " . $conn->connect_error);
}
?>
