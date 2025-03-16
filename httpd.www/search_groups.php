<?php
require 'db_connect.php';

$query = $_GET['query'] ?? '';
$stmt = $conn->prepare("SELECT GroupName FROM Groups WHERE GroupName LIKE ?");
$query = "%$query%";
$stmt->bind_param("s", $query);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['groups' => $result->fetch_all(MYSQLI_ASSOC)]);
?>
