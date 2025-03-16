<?php
// Aktivera felrapportering

require 'db_connect.php';

// Hämta ID från GET-parametern
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

$id = intval($_GET['id']);

// Hämta data från databasen
$stmt = $conn->prepare("SELECT ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent FROM SurveyHeaders WHERE AnswerId = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Details not found']);
}

// Stäng anslutningen
$stmt->close();
$conn->close();
?>
