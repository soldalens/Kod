<?php
require 'db_connect.php';

$inviteCode = isset($_GET['inviteCode']) ? trim($_GET['inviteCode']) : '';

if ($inviteCode === '') {
    echo json_encode(['success' => false, 'message' => 'No invite code provided.']);
    exit;
}

// Hämta den senaste raden från HeaderTemp baserat på inviteCode
$sql = "SELECT BestPerson, Gender, Age, Education, GroupName, Company, isCompany 
        FROM HeaderTemp 
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
    echo json_encode(['success' => true, 'header' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'No header data found.']);
}

$stmt->close();
$conn->close();
?>
