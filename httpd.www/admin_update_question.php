<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anslut till databasen
require 'db_connect.php';

// Hämta ActiveVersionId från Settings-tabellen
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);
if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

// Hämta JSON-data från klienten
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'], $data['text'], $data['value'], $data['order'], $data['answers'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$questionID   = $data['id'];
$questionText = $data['text'];
$value        = $data['value'];
$order        = $data['order'];
$answers      = $data['answers'];

// Bestäm språk, standard är svenska
$lang = 'sv';
if (isset($data['language']) && $data['language'] === 'en') {
    $lang = 'en';
}
if ($lang === 'en') {
    $questionColumn = "QuestionTextENG";
    $answerColumn   = "AnswerTextENG";
} else {
    $questionColumn = "QuestionText";
    $answerColumn   = "AnswerText";
}

// Kontrollera att frågan hör till den aktiva versionen
$versionCheckSql = "SELECT COUNT(*) AS question_exists FROM Questions WHERE QuestionID = ? AND VersionId = ?";
$versionCheckStmt = $conn->prepare($versionCheckSql);
$versionCheckStmt->bind_param("ii", $questionID, $activeVersionId);
$versionCheckStmt->execute();
$versionCheckResult = $versionCheckStmt->get_result();
if ($versionCheckResult->fetch_assoc()['question_exists'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Frågan tillhör inte den aktiva versionen']);
    $versionCheckStmt->close();
    $conn->close();
    exit;
}
$versionCheckStmt->close();

// Uppdatera frågan i Questions-tabellen med rätt språk-kolumn
$sql = "UPDATE Questions SET $questionColumn = ?, Value = ?, OrderNumber = ? WHERE QuestionID = ? AND VersionId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("siiii", $questionText, $value, $order, $questionID, $activeVersionId);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera frågan']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Hämta de befintliga svarsalternativens ID:n för frågan, sorterade med lägst ID först
$sqlExisting = "SELECT AnswerOptionID FROM AnswerOptions WHERE QuestionID = ? AND VersionId = ? ORDER BY AnswerOptionID ASC";
$stmtExisting = $conn->prepare($sqlExisting);
$stmtExisting->bind_param("ii", $questionID, $activeVersionId);
$stmtExisting->execute();
$resultExisting = $stmtExisting->get_result();
$existingIDs = [];
while ($row = $resultExisting->fetch_assoc()) {
    $existingIDs[] = $row['AnswerOptionID'];
}
$stmtExisting->close();

// Uppdatera svarsalternativen enbart (inga insert) – jämför ordningen i databasen med listan från klienten
$updateSql = "UPDATE AnswerOptions SET $answerColumn = ?, ResponseID = ?, Multiplicator = ? WHERE AnswerOptionID = ? AND VersionId = ?";
$updateStmt = $conn->prepare($updateSql);

// Bestäm hur många svar som ska uppdateras (minsta antalet av befintliga och skickade)
$numberOfUpdates = min(count($existingIDs), count($answers));
for ($i = 0; $i < $numberOfUpdates; $i++) {
    $ans = $answers[$i];
    // Kontrollera att nödvändiga fält finns
    if (!isset($ans['text'], $ans['response_id'], $ans['multiplier'])) {
        echo json_encode(['success' => false, 'message' => 'Ett svarsalternativ saknar nödvändiga fält']);
        $updateStmt->close();
        $conn->close();
        exit;
    }
    $answerText = $ans['text'];
    $responseID = $ans['response_id'];
    $multiplier = $ans['multiplier'];
    $answerOptionID = $existingIDs[$i];
    
    $updateStmt->bind_param("sidii", $answerText, $responseID, $multiplier, $answerOptionID, $activeVersionId);
    if (!$updateStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera svarsalternativ']);
        $updateStmt->close();
        $conn->close();
        exit;
    }
}
$updateStmt->close();

// Skicka tillbaka ett framgångsmeddelande
echo json_encode(['success' => true]);
$conn->close();
?>
