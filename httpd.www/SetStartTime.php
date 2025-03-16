<?php
header('Content-Type: application/json');

// Databasinställningar
$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

try {
    // Skapa en anslutning till databasen
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Hämta InviteCode från POST-data
    $inviteCode = $_POST['inviteCode'] ?? '';

    // Kontrollera att InviteCode inte är tom
    if (!empty($inviteCode)) {
        // Uppdatera tabellen SurveyInvites
        $stmt = $pdo->prepare("
            UPDATE SurveyInvites
            SET StartedTime = :startedTime, Started = Started + 1
            WHERE InviteCode = :inviteCode
        ");

        // Ställ in aktuellt datum och tid i Stockholm
        $startedTime = (new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d H:i:s');

        // Kör SQL-frågan
        $stmt->execute([
            ':startedTime' => $startedTime,
            ':inviteCode' => $inviteCode
        ]);

        // Kontrollera om någon rad uppdaterades
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Starttid uppdaterad.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ingen matchande InviteCode hittades.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'InviteCode är tomt eller saknas.']);
    }
} catch (PDOException $e) {
    // Fånga och returnera eventuella fel
    echo json_encode(['success' => false, 'message' => 'Databasfel: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Fånga och returnera andra fel
    echo json_encode(['success' => false, 'message' => 'Ett oväntat fel inträffade: ' . $e->getMessage()]);
}
?>
