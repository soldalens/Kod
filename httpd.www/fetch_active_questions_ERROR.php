<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require 'db_connect.php';

// Hämta ActiveVersionId från Settings-tabellen
$activeVersionIdSql = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$activeVersionIdResult = $conn->query($activeVersionIdSql);

if ($activeVersionIdResult->num_rows > 0) {
    $activeVersionIdRow = $activeVersionIdResult->fetch_assoc();
    $activeVersionId = intval($activeVersionIdRow['ActiveVersionId']);
} else {
    die(json_encode(["error" => "ActiveVersionId saknas i Settings-tabellen."]));
}

// Uppdaterad SQL-fråga som hämtar både svenska och engelska
$sql = "
    SELECT 
        q.QuestionID, 
        q.QuestionText AS QuestionTextSV, 
        q.QuestionTextENG AS QuestionTextEN,
        q.OrderNumber, 
        a.AnswerOptionID, 
        a.AnswerText AS AnswerTextSV, 
        a.AnswerTextENG AS AnswerTextEN,
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
                'text_sv' => $row['QuestionTextSV'],
                'text_en' => $row['QuestionTextEN'],
                'order' => $row['OrderNumber'],
                'answers' => []
            ];
        }

        // Lägg till svarsalternativ till frågan
        $data[$questionID]['answers'][] = [
            'id' => $row['AnswerOptionID'],
            'text_sv' => $row['AnswerTextSV'],
            'text_en' => $row['AnswerTextEN'],
            'response_id' => $row['ResponseID']
        ];
    }
}

// Konvertera arrayen till JSON och skicka tillbaka till klienten
echo json_encode(array_values($data));
$conn->close();
?>
