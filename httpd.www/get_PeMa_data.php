<?php
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (empty($data['groupIds']) || !is_array($data['groupIds'])) {
        echo json_encode(['success' => false, 'message' => 'Inga grupper valda.']);
        exit;
    }

    $groupIds = $data['groupIds'];

    // Hämta GroupName baserat på GroupId
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $stmt = $conn->prepare("SELECT GroupName FROM Groups WHERE GroupId IN ($placeholders)");
    $types = str_repeat('i', count($groupIds));
    $stmt->bind_param($types, ...$groupIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $groupNames = [];
    while ($row = $result->fetch_assoc()) {
        $groupNames[] = $row['GroupName'];
    }
    $stmt->close();

    if (empty($groupNames)) {
        echo json_encode(['success' => false, 'message' => 'Kunde inte hitta några grupper.']);
        exit;
    }

    // Hämta data från SurveyHeaders baserat på GroupName
    $placeholders = implode(',', array_fill(0, count($groupNames), '?'));
    $stmt = $conn->prepare("
        SELECT 
            Name, 
            SUBSTRING_INDEX(Name, ' ', 1) AS firstName, 
            IF(INSTR(Name, ' ') > 0, LEFT(TRIM(SUBSTRING_INDEX(Name, ' ', -1)), 1), '') AS lastInitial, 
            PersonalityType, 
            CorrectedPersonalityType 
        FROM SurveyHeaders 
        WHERE GroupName IN ($placeholders)
    ");
    $types = str_repeat('s', count($groupNames));
    $stmt->bind_param($types, ...$groupNames);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
