<?php
require 'db_connect.php';

$inviteCode = isset($_GET['inviteCode']) ? trim($_GET['inviteCode']) : '';

if ($inviteCode === '') {
    echo json_encode(['success' => false, 'message' => 'No inviteCode provided']);
    exit;
}

// Hämta raden från SurveyHeaders med det angivna InviteCode
$sql = "SELECT AnswerId, ProfileCode, ExtraQuestionDone, PersonalityType 
        FROM SurveyHeaders 
        WHERE InviteCode = ? 
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}
$stmt->bind_param("s", $inviteCode);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Om ProfileCode finns och ExtraQuestionDone = 0, samt AnswerId är ifyllt
    if ($row['ProfileCode'] !== null && $row['AnswerId'] !== null && (int)$row['ExtraQuestionDone'] === 0) {
        echo json_encode([
            'success' => true,
            'redirect' => 'proanalyse',
            'AnswerId' => $row['AnswerId'],
            'ProfileCode' => $row['ProfileCode']
        ]);
    }
    // Om (ProfileCode finns och ExtraQuestionDone = 1) ELLER (ProfileCode är NULL och PersonalityType finns)
    else if (( $row['ProfileCode'] !== null && (int)$row['ExtraQuestionDone'] === 1 ) || 
             ($row['ProfileCode'] === null && $row['PersonalityType'] !== null)) {
        echo json_encode([
            'success' => true,
            'redirect' => 'analysen-inskickad',
            'AnswerId' => $row['AnswerId']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No matching conditions found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No matching row found']);
}

$stmt->close();
$conn->close();
?>
