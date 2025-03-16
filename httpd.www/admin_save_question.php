<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anslut till databasen
require 'db_connect.php';

// Hämta ActiveVersionId
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

// Hämta JSON-data från klienten
$data = json_decode(file_get_contents('php://input'), true);

// Kontrollera om data är korrekt
if (!isset($data['text'], $data['value'], $data['answers'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Bestäm språk, standard är svenska
$lang = 'sv';
if (isset($data['language']) && $data['language'] === 'en') {
    $lang = 'en';
}

// Bestäm vilka kolumner som ska användas vid insättning
if ($lang === 'en') {
    $questionColumn = "QuestionTextENG";
    $answerColumn   = "AnswerTextENG";
} else {
    $questionColumn = "QuestionText";
    $answerColumn   = "AnswerText";
}

// Hämta den nya frågans ordning (OrderNumber)
$orderNumberSql = "SELECT COUNT(*) + 1 AS next_order FROM Questions WHERE VersionId = ?";
$orderNumberStmt = $conn->prepare($orderNumberSql);
$orderNumberStmt->bind_param("i", $activeVersionId);
$orderNumberStmt->execute();
$orderNumberResult = $orderNumberStmt->get_result();
$orderNumber = $orderNumberResult->fetch_assoc()['next_order'];
$orderNumberStmt->close();

// Lägg till frågan i Questions-tabellen med rätt kolumn för texten
$stmt = $conn->prepare("INSERT INTO Questions ($questionColumn, Value, OrderNumber, VersionId) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siii", $data['text'], $data['value'], $orderNumber, $activeVersionId);
$stmt->execute();
$questionID = $stmt->insert_id;
$stmt->close();

// Lägg till svarsalternativen i AnswerOptions-tabellen med rätt kolumn för svarstexten
$stmt = $conn->prepare("INSERT INTO AnswerOptions (QuestionID, $answerColumn, ResponseID, Multiplicator, VersionId) VALUES (?, ?, ?, ?, ?)");
foreach ($data['answers'] as $answer) {
    $multiplicator = isset($answer['multiplier']) ? floatval($answer['multiplier']) : 1.0; // Standardvärde: 1.0
    $stmt->bind_param("isidi", $questionID, $answer['text'], $answer['response_id'], $multiplicator, $activeVersionId);
    $stmt->execute();
}

$stmt->close();

// Skicka tillbaka ett framgångsmeddelande
echo json_encode(['success' => true]);

// Stäng anslutningen
$conn->close();
?>
