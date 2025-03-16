<?php

require 'db_connect.php';

if (isset($_GET['inviteCode']) && !empty($_GET['inviteCode'])) {
    $inviteCode = $_GET['inviteCode'];
} else {
    echo json_encode(['success' => false, 'message' => 'InviteCode missing.']);
    exit;
}

if ($inviteCode) {
    $query = "
        SELECT QuestionId, ResponseId 
        FROM AnswerLog 
        WHERE InviteCode = ? 
        GROUP BY QuestionId 
        ORDER BY MAX(ClickTime) DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $inviteCode);
    $stmt->execute();
    $result = $stmt->get_result();

    $answers = [];
    while ($row = $result->fetch_assoc()) {
        $answers[$row['QuestionId']] = $row['ResponseId'];
    }

    // Här lägger du in kontrollen för om det finns några svar
    if (count($answers) > 0) {
        echo json_encode(['success' => true, 'answers' => $answers]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No answers found for the given InviteCode.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'InviteCode missing.']);
}

$conn->close();
?>
