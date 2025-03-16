<?php
require 'db_connect.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['ids']) || !is_array($data['ids'])) {
        throw new Exception("Ogiltig data: 'ids' saknas eller är inte en array.");
    }

    $ids = implode(",", array_map("intval", $data['ids']));

    if (empty($ids)) {
        throw new Exception("Ogiltig data: Inga giltiga IDs mottagna.");
    }

    // Steg 1: Hämta InviteCode för de angivna AnswerId
    $inviteCodeQuery = "SELECT DISTINCT InviteCode FROM SurveyHeaders WHERE AnswerId IN ($ids)";
    $result = $conn->query($inviteCodeQuery);

    if (!$result) {
        throw new Exception("Failed to fetch InviteCode: " . $conn->error);
    }

    $inviteCodes = [];
    while ($row = $result->fetch_assoc()) {
        $inviteCodes[] = $row['InviteCode'];
    }

    if (empty($inviteCodes)) {
        throw new Exception("No InviteCodes found for the given AnswerIds.");
    }

    // Gör InviteCodes redo för användning i SQL
    $inviteCodesIn = "'" . implode("','", array_map([$conn, 'real_escape_string'], $inviteCodes)) . "'";

    // Steg 2: Ta bort från AnswerLog baserat på InviteCode
    $deleteAnswerLog = "DELETE FROM AnswerLog WHERE InviteCode IN ($inviteCodesIn)";
    if (!$conn->query($deleteAnswerLog)) {
        throw new Exception("Failed to delete from AnswerLog: " . $conn->error);
    }

    // Steg 3: Ta bort från SurveyAnswers
    $deleteAnswers = "DELETE FROM SurveyAnswers WHERE AnswerId IN ($ids)";
    if (!$conn->query($deleteAnswers)) {
        throw new Exception("Failed to delete from SurveyAnswers: " . $conn->error);
    }

    // Steg 4: Ta bort från SurveyHeaders
    $deleteHeaders = "DELETE FROM SurveyHeaders WHERE AnswerId IN ($ids)";
    if (!$conn->query($deleteHeaders)) {
        throw new Exception("Failed to delete from SurveyHeaders: " . $conn->error);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Error in delete_rows.php: " . $e->getMessage());
}
?>
