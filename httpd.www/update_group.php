<?php
// Aktivera felrapportering
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

header('Content-Type: application/json'); // Säkerställ att responsen alltid är JSON

try {
    // Läs in och avkoda JSON-data
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Ingen data mottagen eller ogiltigt JSON-format.");
    }

    $ids = $data['ids'] ?? [];
    $groupName = $data['groupName'] ?? '';

    // Validera inkommande data
    if (empty($ids) || !is_array($ids)) {
        throw new Exception("Ogiltiga data: 'ids' saknas eller är inte en array.");
    }
    if (empty($groupName)) {
        throw new Exception("Ogiltiga data: 'groupName' saknas.");
    }

    // Kontrollera att alla IDs är heltal
    $ids = array_map('intval', $ids);
    if (in_array(0, $ids, true)) {
        throw new Exception("Ogiltiga data: Ett eller flera IDs är ogiltiga.");
    }

    // Sanera och förbered data
    $groupName = $conn->real_escape_string($groupName);
    $idList = implode(",", $ids); // Gör ID till en säker kommaseparerad lista

    // Debug-loggning (till serverloggar, ej skickad till klienten)
    error_log("Validerad data:\nIDs: " . implode(", ", $ids) . "\nGruppnamn: $groupName");
    error_log("SQL-fråga: UPDATE SurveyHeaders SET GroupName = '$groupName' WHERE AnswerId IN ($idList)");

    // Uppdatera databasen
    $sql = "UPDATE SurveyHeaders SET GroupName = '$groupName' WHERE AnswerId IN ($idList)";
    if (!$conn->query($sql)) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    // Skicka endast JSON-svar
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Skicka tillbaka felmeddelandet som JSON
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Fel i update_group.php: " . $e->getMessage());
}
?>
