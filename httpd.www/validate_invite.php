<?php
header('Content-Type: application/json');

// Kontrollera om POST-data är tillgänglig
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['code'])) {
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);

    // Databasanslutning
    require 'db_connect.php';

    // Kontrollera om e-post och kod finns i databasen och om Used är 0
    $stmt = $conn->prepare("SELECT Used FROM SurveyInvites WHERE Email = ? AND InviteCode = ?");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['Used'] == 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kombinationen mejladress och kod är redan använd.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ogiltig kod eller e-postadress.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Ogiltig förfrågan.']);
}
?>

