<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anslut till databasen
require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$groupNames = $data['groupNames'] ?? [];

if (empty($groupNames)) {
    echo json_encode(['success' => false, 'message' => 'Inga grupper angivna.']);
    exit;
}

// Kontrollera beroenden
$placeholders = implode(',', array_fill(0, count($groupNames), '?'));
$stmt = $conn->prepare("SELECT DISTINCT GroupName FROM SurveyHeaders WHERE GroupName IN ($placeholders)");
$stmt->bind_param(str_repeat('s', count($groupNames)), ...$groupNames);
$stmt->execute();
$result = $stmt->get_result();

$groupsWithDependencies = [];
while ($row = $result->fetch_assoc()) {
    $groupsWithDependencies[] = $row['GroupName'];
}

$stmt->close();
$conn->close();

// Returnera resultatet
echo json_encode([
    'success' => true,
    'groupsWithDependencies' => $groupsWithDependencies,
]);
