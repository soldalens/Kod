<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anslut till databasen
require 'db_connect.php';

// Ta emot språk-parametern, standard är svenska (sv)
$lang = 'sv';
if (isset($_GET['language']) && $_GET['language'] === 'en') {
    $lang = 'en';
}

// Bestäm vilka kolumner som ska hämtas beroende på språk
if ($lang === 'en') {
    $questionColumn = "q.QuestionTextENG";
    $answerColumn = "a.AnswerTextENG";
} else {
    $questionColumn = "q.QuestionText";
    $answerColumn = "a.AnswerText";
}

// Hämta ActiveVersionId från Settings-tabellen
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

// SQL-fråga för att hämta frågor, svar, MBTI-egenskaper och Multiplicator baserat på ActiveVersionId
$sql = "
    SELECT 
        q.QuestionID,
        $questionColumn AS QuestionText,
        q.Value,
        q.OrderNumber,
        q.isActive,
        a.AnswerOptionID,
        $answerColumn AS AnswerText,
        a.ResponseID,
        a.Multiplicator,
        r.ResponseDescr
    FROM Questions q
    LEFT JOIN AnswerOptions a ON q.QuestionID = a.QuestionID
    LEFT JOIN ResponseOptions r ON a.ResponseID = r.ResponseID
    WHERE q.VersionId = ? AND a.VersionId = ?
    ORDER BY q.OrderNumber, a.AnswerOptionID";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $activeVersionId, $activeVersionId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

// Bygg en strukturerad array med frågor och deras svar
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $questionID = $row['QuestionID'];
        
        // Skapa en ny fråga om den inte redan finns i arrayen
        if (!isset($data[$questionID])) {
            $data[$questionID] = [
                'id' => $row['QuestionID'],
                'text' => $row['QuestionText'],
                'value' => $row['Value'],
                'order' => $row['OrderNumber'],
                'isActive' => $row['isActive'],
                'answers' => []
            ];
        }

        // Lägg till svarsalternativ om det finns
        if (!empty($row['AnswerOptionID'])) {
            $data[$questionID]['answers'][] = [
                'id' => $row['AnswerOptionID'],
                'text' => $row['AnswerText'],
                'response_id' => $row['ResponseID'],
                'mbti_trait' => $row['ResponseDescr'],
                'multiplier' => $row['Multiplicator']
            ];
        }
    }
}

// Konvertera arrayen till JSON och skicka till klienten
echo json_encode(array_values($data));

// Stäng anslutningen
$stmt->close();
$conn->close();
?>
