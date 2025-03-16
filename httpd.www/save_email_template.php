<?php
header('Content-Type: application/json');

require 'db_connect.php';

// Hämta JSON-data från klienten
$data = json_decode(file_get_contents('php://input'), true);
$templateName = $data['templateName'];
$emailBody = $data['emailBody'];
$overwrite = isset($data['overwrite']) ? $data['overwrite'] : false;

// Kontrollera om fälten är korrekt ifyllda
if (empty($templateName) || empty($emailBody)) {
    echo json_encode(['success' => false, 'message' => 'Fälten templateName och emailBody krävs']);
    exit();
}

// Kontrollera om en mall med samma namn redan finns
$stmt = $conn->prepare("SELECT id FROM EmailTemplates WHERE template_name = ?");
$stmt->bind_param("s", $templateName);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // En mall med samma namn finns
    if (!$overwrite) {
        echo json_encode(['success' => false, 'exists' => true, 'message' => 'En mall med samma namn finns redan.']);
        $stmt->close();
        $conn->close();
        exit();
    } else {
        // Skriv över den befintliga mallen
        $stmt->close();
        $updateStmt = $conn->prepare("UPDATE EmailTemplates SET email_body = ? WHERE template_name = ?");
        $updateStmt->bind_param("ss", $emailBody, $templateName);
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Mejlmallen uppdaterades.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera mejlmallen.']);
        }
        $updateStmt->close();
        $conn->close();
        exit();
    }
} else {
    // Spara en ny mall
    $stmt->close();
    $insertStmt = $conn->prepare("INSERT INTO EmailTemplates (template_name, email_body) VALUES (?, ?)");
    $insertStmt->bind_param("ss", $templateName, $emailBody);
    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Mejlmallen sparades.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kunde inte spara mejlmallen.']);
    }
    $insertStmt->close();
    $conn->close();
}
?>
