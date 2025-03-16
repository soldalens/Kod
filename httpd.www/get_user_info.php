<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require 'db_connect.php';

// Kontrollera att AnswerId finns i GET-parametrarna
if (!isset($_GET['AnswerId']) || empty($_GET['AnswerId'])) {
    echo json_encode(['success' => false, 'message' => 'AnswerId saknas']);
    exit();
}

$answerId = intval($_GET['AnswerId']);

// Hämta InviteCode från SurveyHeaders baserat på AnswerId
$stmt = $conn->prepare("SELECT InviteCode FROM SurveyHeaders WHERE AnswerId = ?");
$stmt->bind_param("i", $answerId);
$stmt->execute();
$stmt->bind_result($inviteCode);
$stmt->fetch();
$stmt->close();

if (!$inviteCode) {
    echo json_encode(['success' => false, 'message' => 'Ingen InviteCode hittades för AnswerId']);
    exit();
}

// Hämta InvitedBy från SurveyInvites baserat på InviteCode
$stmt = $conn->prepare("SELECT InvitedBy FROM SurveyInvites WHERE InviteCode = ?");
$stmt->bind_param("s", $inviteCode);
$stmt->execute();
$stmt->bind_result($invitedBy);
$stmt->fetch();
$stmt->close();

if (!$invitedBy) {
    echo json_encode(['success' => false, 'message' => 'Ingen InvitedBy hittades för InviteCode']);
    exit();
}

// Hämta Name, FirstName och Email från Users baserat på InvitedBy
$stmt = $conn->prepare("SELECT Name, FirstName, Email FROM Users WHERE UserId = ?");
$stmt->bind_param("s", $invitedBy);
$stmt->execute();
$stmt->bind_result($name, $firstName, $email);
$stmt->fetch();
$stmt->close();

if ($name && $firstName && $email) {
    echo json_encode([
        'success' => true,
        'Name' => $name,
        'FirstName' => $firstName,
        'Email' => $email,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ingen användare hittades för UserId']);
}

$conn->close();
?>
