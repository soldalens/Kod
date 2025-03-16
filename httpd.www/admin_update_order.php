<?php
session_start();

require 'db_connect.php';

// Hämta ActiveVersionId från Settings-tabellen
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

$data = json_decode(file_get_contents('php://input'), true);
error_log('Received data: ' . json_encode($data));

$questionID = isset($data['id']) ? (int) $data['id'] : 0;
$newOrder = isset($data['newOrder']) ? (int) $data['newOrder'] : 0;

if ($questionID === 0 || $newOrder === 0) {
    error_log('Invalid data received');
    echo json_encode(['success' => false, 'message' => 'Ogiltig data']);
    exit;
}

// Debug-logg
error_log('Received update: ID=' . $questionID . ', New Order=' . $newOrder);

// Hämta nuvarande OrderNumber för frågan och kontrollera VersionId
$sql = "SELECT OrderNumber FROM Questions WHERE QuestionID = ? AND VersionId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $questionID, $activeVersionId);
$stmt->execute();
$result = $stmt->get_result();
$currentOrder = $result->fetch_assoc()['OrderNumber'];

if (!$currentOrder) {
    echo json_encode(['success' => false, 'message' => 'Frågan kunde inte hittas eller tillhör inte den aktiva versionen']);
    exit;
}

// Uppdatera OrderNumber för andra frågor inom samma VersionId
if ($newOrder < $currentOrder) {
    // Flytta frågor framåt
    $sql = "UPDATE Questions 
            SET OrderNumber = OrderNumber + 1 
            WHERE VersionId = ? AND OrderNumber >= ? AND OrderNumber < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $activeVersionId, $newOrder, $currentOrder);
    $stmt->execute();
} elseif ($newOrder > $currentOrder) {
    // Flytta frågor bakåt
    $sql = "UPDATE Questions 
            SET OrderNumber = OrderNumber - 1 
            WHERE VersionId = ? AND OrderNumber <= ? AND OrderNumber > ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $activeVersionId, $newOrder, $currentOrder);
    $stmt->execute();
}

// Uppdatera OrderNumber för den aktuella frågan
$sql = "UPDATE Questions SET OrderNumber = ? WHERE QuestionID = ? AND VersionId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $newOrder, $questionID, $activeVersionId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera frågan']);
}

// Stäng anslutning och statement
$stmt->close();
$conn->close();
?>
