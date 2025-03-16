<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anslut till databasen
require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$groupIds = $data['groupIds'] ?? [];

if (empty($groupIds)) {
    echo json_encode(['success' => false, 'message' => 'Inga grupper valda för borttagning.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($groupIds), '?'));
$stmt = $conn->prepare("DELETE FROM Groups WHERE GroupId IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($groupIds)), ...$groupIds);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Markerade grupper togs bort.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort markerade grupper.']);
}

$stmt->close();
$conn->close();
