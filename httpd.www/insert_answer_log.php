<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inviteCode = $_POST['inviteCode'] ?? null;
    $questionId = $_POST['questionId'] ?? null;
    $responseId = $_POST['responseId'] ?? null;
    $clickTime = (new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d H:i:s');

    if ($inviteCode && $questionId && $responseId) {
        // Database connection details
        $servername = "proanalys.se.mysql";
        $username = "proanalys_seproanalys";
        $password = "MFFDortmund9!";
        $database = "proanalys_seproanalys";

        try {
            // Create a new PDO connection
            $dsn = "mysql:host=$servername;dbname=$database;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Insert query
            $stmt = $pdo->prepare("
                INSERT INTO AnswerLog (InviteCode, QuestionId, ResponseId, ClickTime)
                VALUES (:inviteCode, :questionId, :responseId, :clickTime)
            ");
            $stmt->execute([
                ':inviteCode' => $inviteCode,
                ':questionId' => $questionId,
                ':responseId' => $responseId,
                ':clickTime' => $clickTime,
            ]);

            // Success response
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            // Error response
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        // Missing data response
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    }
}
?>
