<?php

require 'db_connect.php';

// ---------------------------------------------------------------- Hämta ActiveVersionId
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

// ---------------------------------------------------------------- Hämta data från frontend
$data = json_decode(file_get_contents('php://input'), true);

$questionID = isset($data['id']) ? (int) $data['id'] : 0;
$isActive = isset($data['isActive']) ? (int) $data['isActive'] : null;

if ($questionID === 0 || $isActive === null) {
    echo json_encode(['success' => false, 'message' => 'Ogiltig data']);
    exit;
}

// ---------------------------------------------------------------- Kontrollera att frågan tillhör den aktiva versionen
$checkQuestionSql = "SELECT QuestionID FROM Questions WHERE QuestionID = ? AND VersionId = ?";
$stmt = $conn->prepare($checkQuestionSql);
$stmt->bind_param("ii", $questionID, $activeVersionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Frågan kunde inte hittas eller tillhör inte den aktiva versionen']);
    exit;
}

// ---------------------------------------------------------------- Uppdatera isActive för frågan i den aktiva versionen
$updateSql = "UPDATE Questions SET isActive = ? WHERE QuestionID = ? AND VersionId = ?";
$stmt = $conn->prepare($updateSql);
$stmt->bind_param("iii", $isActive, $questionID, $activeVersionId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera status för frågan']);
}

$stmt->close();
$conn->close();
