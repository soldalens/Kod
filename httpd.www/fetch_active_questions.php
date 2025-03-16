<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require 'db_connect.php';

// Hämta ActiveVersionId från Settings-tabellen
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if (!$activeVersionIdResult || $activeVersionIdResult->num_rows === 0) {
    echo json_encode(["error" => "ActiveVersionId saknas i Settings-tabellen."]);
    exit();
}

$activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
$activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);

// Hämta språkinställning från GET-parameter (som skickas från klienten)
$selectedLang = isset($_GET['lang']) ? strtolower($_GET['lang']) : null;

// Om inget språk skickats via GET, använd webbläsarens språk
if (!$selectedLang) {
    $selectedLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
}

// Välj rätt kolumner baserat på språket
if ($selectedLang === 'sv') {
    $questionColumn = "q.QuestionText";
    $answerColumn   = "a.AnswerText";
} else {
    $questionColumn = "q.QuestionTextENG";
    $answerColumn   = "a.AnswerTextENG";
}

// Uppdaterad SQL-fråga med dynamiskt valda kolumner
$sql = "
    SELECT 
        q.QuestionID, 
        $questionColumn AS QuestionText, 
        q.OrderNumber, 
        a.AnswerOptionID, 
        $answerColumn AS AnswerText, 
        a.ResponseID
    FROM Questions q
    LEFT JOIN AnswerOptions a 
        ON q.QuestionID = a.QuestionID AND a.VersionId = ?
    WHERE q.isActive = 1 AND q.VersionId = ?
    ORDER BY q.OrderNumber, RAND()"; // Slumpmässig ordning på svar

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $activeVersionId, $activeVersionId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $questionID = $row['QuestionID'];

        // Om frågan inte redan finns i arrayen, lägg till den
        if (!isset($data[$questionID])) {
            $data[$questionID] = [
                'id' => $row['QuestionID'],
                'text' => $row['QuestionText'],
                'order' => $row['OrderNumber'],
                'answers' => []
            ];
        }

        // Lägg till svarsalternativ till frågan
        if (!empty($row['AnswerOptionID'])) {
            $data[$questionID]['answers'][] = [
                'id' => $row['AnswerOptionID'],
                'text' => $row['AnswerText'],
                'response_id' => $row['ResponseID']
            ];
        }
    }
}

// Skicka JSON-svar
echo json_encode(array_values($data), JSON_UNESCAPED_UNICODE);
$conn->close();
?>
