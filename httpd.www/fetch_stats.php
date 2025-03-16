<?php
// Felhantering
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Databasuppgifter
$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

// Skapa anslutning
$mysqli = new mysqli($servername, $username, $password, $database);

// Kontrollera anslutningen
if ($mysqli->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Connection failed: " . $mysqli->connect_error]);
    exit;
}

// Få sorteringsparametrar
$sortColumn = isset($_GET['sortColumn']) ? $mysqli->real_escape_string($_GET['sortColumn']) : 'OrderNumber';
$sortOrder = isset($_GET['sortOrder']) && in_array(strtoupper($_GET['sortOrder']), ['ASC', 'DESC']) ? $_GET['sortOrder'] : 'ASC';

// Bygg WHERE-sats baserat på filter
$whereClauses = [];
$filters = [
    "personalityType" => "PersonalityType",
    "name" => "Name",
    "education" => "Education",
    "group" => "GroupName",
    "gender" => "Gender",
    "age" => "Age",
    "orderNumber" => "OrderNumber",
    "responseDescr" => "ResponseDescr"
];

foreach ($filters as $key => $column) {
    if (!empty($_GET[$key])) {
        $values = implode("','", array_map([$mysqli, 'real_escape_string'], $_GET[$key]));
        $whereClauses[] = "$column IN ('$values')";
    }
}

$where = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Få aktuell vy
$view = isset($_GET['view']) ? $_GET['view'] : 'question';

// Bygg SQL baserat på vy
if ($view === 'personality') {
    $query = "
      SELECT
          PersonalityType,
          ROUND(AVG(TimeDifference)) AS AvgTimeDifference, 
          ROUND(STDDEV(TimeDifference)) AS StdDevTimeDifference, 
          COUNT(TimeDifference) AS CountTimeDifference, 
          IF(
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference) < 0, 
              0, 
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference)
          ) AS Outliers
      FROM 
          QuestionTimeAnalysis
      $where
      GROUP BY 
          PersonalityType
      ORDER BY 
          $sortColumn $sortOrder
    ";
} elseif ($view === 'response') {
    $query = "
      SELECT
          ResponseDescr,
          ROUND(AVG(TimeDifference)) AS AvgTimeDifference, 
          ROUND(STDDEV(TimeDifference)) AS StdDevTimeDifference, 
          COUNT(TimeDifference) AS CountTimeDifference, 
          IF(
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference) < 0, 
              0, 
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference)
          ) AS Outliers
      FROM 
          QuestionTimeAnalysis
      $where
      GROUP BY 
          ResponseDescr
      ORDER BY 
          $sortColumn $sortOrder
    ";
} else {
    $query = "
      SELECT
          OrderNumber, 
          SUBSTRING(QuestionText, 1, 50) AS QuestionTextSnippet,
          SUBSTRING(AnswerText, 1, 50) AS AnswerTextSnippet,
          ROUND(AVG(TimeDifference)) AS AvgTimeDifference, 
          ROUND(STDDEV(TimeDifference)) AS StdDevTimeDifference, 
          COUNT(TimeDifference) AS CountTimeDifference, 
          IF(
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference) < 0, 
              0, 
              (SELECT COUNT(DISTINCT Name) FROM QuestionTimeAnalysis $where) - COUNT(TimeDifference)
          ) AS Outliers
      FROM 
          QuestionTimeAnalysis
      $where
      GROUP BY 
          OrderNumber
      ORDER BY 
          $sortColumn $sortOrder
    ";
}

// Kör SQL-frågan
$result = $mysqli->query($query);

// Kontrollera om frågan misslyckades
if (!$result) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Query failed: " . $mysqli->error]);
    exit;
}

// Generera JSON-data
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row; // Lägg till varje rad som en associerad array
}

// Skicka JSON-svar
header('Content-Type: application/json');
echo json_encode(["rows" => $rows]);

// Stäng anslutning
$mysqli->close();
exit;
