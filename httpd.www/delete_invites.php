<?php

require 'db_connect.php';

// Kontrollera att förfrågan är en POST-begäran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kontrollera att data finns
    if (!isset($_POST['deleteIds']) || empty($_POST['deleteIds'])) {
        echo json_encode(['success' => false, 'message' => 'Inga ID:n skickades.']);
        exit();
    }

    // Dekoda de skickade ID:n
    $deleteIds = json_decode($_POST['deleteIds'], true);

    // Kontrollera att det är en array
    if (!is_array($deleteIds) || count($deleteIds) === 0) {
        echo json_encode(['success' => false, 'message' => 'Ogiltiga ID:n angivna.']);
        exit();
    }

    // Skapa placeholders för parameteriserad fråga
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

    // Hämta InviteCodes baserat på InviteIds
    $inviteCodes = [];
    $inviteCodeQuery = "SELECT InviteCode FROM SurveyInvites WHERE InviteId IN ($placeholders)";
    $getInviteCodeStmt = $conn->prepare($inviteCodeQuery);

    if (!$getInviteCodeStmt) {
        echo json_encode(['success' => false, 'message' => 'Kunde inte förbereda frågan för InviteCode.']);
        exit();
    }

    // Bind parametervärden
    $getInviteCodeStmt->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);
    $getInviteCodeStmt->execute();
    $result = $getInviteCodeStmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $inviteCodes[] = $row['InviteCode'];
    }

    $getInviteCodeStmt->close();

    if (empty($inviteCodes)) {
        echo json_encode(['success' => false, 'message' => 'Inga InviteCodes hittades för de angivna InviteIds.']);
        exit();
    }

    // Ta bort InviteIds från SurveyInvites
    $deleteInviteQuery = "DELETE FROM SurveyInvites WHERE InviteId IN ($placeholders)";
    $deleteInviteStmt = $conn->prepare($deleteInviteQuery);

    if (!$deleteInviteStmt) {
        echo json_encode(['success' => false, 'message' => 'Kunde inte förbereda DELETE-frågan.']);
        exit();
    }

    // Bind parametervärden
    $deleteInviteStmt->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);

    // Kör DELETE-frågan
    if (!$deleteInviteStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Misslyckades med att ta bort inbjudningar.']);
        $deleteInviteStmt->close();
        $conn->close();
        exit();
    }

    $deleteInviteStmt->close();

    // Ta bort poster från AnswerLog om InviteCode inte finns i SurveyHeaders
    foreach ($inviteCodes as $inviteCode) {
        $checkQuery = "SELECT COUNT(*) AS count FROM SurveyHeaders WHERE InviteCode = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $inviteCode);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkRow = $checkResult->fetch_assoc();

        // Om InviteCode inte finns i SurveyHeaders, ta bort från AnswerLog
        if ($checkRow['count'] == 0) {
            $deleteAnswerLogQuery = "DELETE FROM AnswerLog WHERE InviteCode = ?";
            $deleteAnswerLogStmt = $conn->prepare($deleteAnswerLogQuery);
            $deleteAnswerLogStmt->bind_param("s", $inviteCode);

            if (!$deleteAnswerLogStmt->execute()) {
                echo json_encode(['success' => false, 'message' => "Misslyckades med att ta bort poster från AnswerLog för InviteCode: $inviteCode."]);
                $deleteAnswerLogStmt->close();
                continue;
            }

            $deleteAnswerLogStmt->close();
        }

        $checkStmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Valda inbjudningar och relevanta poster togs bort.']);
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågningsmetod.']);
}
?>
