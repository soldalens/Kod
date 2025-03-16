<?php
session_start();

// Rensa sessionen och avsluta
session_unset(); // Rensa alla sessionvariabler
session_destroy(); // Förstör sessionen

// Omdirigera till utfall.php
header("Location: Utfall.php");
exit();
?>
