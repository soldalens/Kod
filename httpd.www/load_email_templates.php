<?php

require 'db_connect.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Kunde inte ansluta till databasen.']);
    exit();
}

$result = $conn->query("SELECT template_name, email_body FROM EmailTemplates ORDER BY template_name ASC");
$templates = [];

while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

echo json_encode(['success' => true, 'templates' => $templates]);

$conn->close();
?>
