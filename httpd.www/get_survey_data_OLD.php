<?php
include 'db_connect.php';

// Hämta ProfileCode och AnswerId från URL-parametrar
$profileCode = $_GET['ProfileCode'] ?? '';
$answerId = $_GET['AnswerId'] ?? '';

// Räkna antalet osäkra dimensioner (?)
$numUncertain = substr_count($profileCode, '?');

// Bestäm sökmönster beroende på antal osäkra dimensioner
if ($numUncertain === 1) {
    // Exempel: ESF?
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode); // ESF? → ESF%
} elseif ($numUncertain === 2) {
    // Exempel: E?F?
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode); // E?F? → E%F%
} else {
    die("Ogiltigt ProfileCode.");
}

// Hämta relevanta PeMaId
$stmt->bind_param("s", $searchPattern);
$stmt->execute();
$result = $stmt->get_result();
$peMaIds = [];
while ($row = $result->fetch_assoc()) {
    $peMaIds[] = $row['PeMaId'];
}

// Kontrollera att vi har rätt antal profiler
if (count($peMaIds) !== (2 * $numUncertain)) {
    die("Fel vid hämtning av profiler.");
}

// Determine browser language (first two characters)
$browserLanguage = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

// Choose the column based on language: 'Text' for Swedish, 'TextENG' for others
$column = ($browserLanguage === 'sv') ? 'Text' : 'TextENG';

// Prepare the SQL statement with a placeholder for DiffCode

if ($numUncertain === 2) {
    $sql = "
        SELECT PeMaId, CharTypeId, DisplayText
        FROM (
            SELECT 
                PeMaId, 
                CharTypeId, 
                $column AS DisplayText,
                ROW_NUMBER() OVER (PARTITION BY PeMaId, CharTypeId ORDER BY DiffId) AS rn
            FROM Diffs
            WHERE DiffCode = ? 
              AND CharTypeId IN (1, 2, 3)
        ) AS sub
        WHERE rn = 1
    ";
} else {
    $sql = "SELECT PeMaId, CharTypeId, $column AS DisplayText FROM Diffs WHERE DiffCode = ?";
}


// Prepare the statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind the parameter and execute the statement
$stmt->bind_param("s", $profileCode);
$stmt->execute();

// Get the result set
$result = $stmt->get_result();

// Organize results into clusters: first by PeMaId, then by CharTypeId
$clusters = [];
while ($row = $result->fetch_assoc()) {
    $peMaId = $row['PeMaId'];
    $categoryId = $row['CharTypeId'];
    $clusters[$peMaId][$categoryId][] = $row['DisplayText'];
}

$conn->close();

$response = [
    "numUncertain" => $numUncertain,
    "clusters"     => $clusters,
    "profileCode"  => $profileCode,
    "answerId"     => $answerId
];

echo json_encode($response);
