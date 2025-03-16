<?php
require 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$ids = implode(",", array_map("intval", $data['ids']));
$type = $data['personalityType'];

$stmt = $conn->prepare("UPDATE SurveyHeaders SET CorrectedPersonalityType = ? WHERE AnswerId IN ($ids)");
$stmt->bind_param("s", $type);
$stmt->execute();

echo json_encode(['success' => true]);
?>
