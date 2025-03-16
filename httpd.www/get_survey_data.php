<?php
include 'db_connect.php';

// Hämta ProfileCode och AnswerId från URL-parametrar
$profileCode = $_GET['ProfileCode'] ?? '';
$answerId = $_GET['AnswerId'] ?? '';
$userLanguage = $_GET['lang'] ?? ''; // Nytt: Ta emot språk från URL

// Om inget språk skickas via URL, använd webbläsarens språk som fallback
if (!$userLanguage) {
    $userLanguage = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
}

// Välj rätt kolumn baserat på språket
$column = ($userLanguage === 'sv') ? 'Text' : 'TextENG';

// Räkna antalet osäkra dimensioner
$numUncertain = substr_count($profileCode, '?');

// Bestäm sökmönster beroende på antal osäkra dimensioner
if ($numUncertain === 1) {
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode);
} elseif ($numUncertain === 2) {
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode);
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

// Skapa SQL-frågan beroende på antalet osäkra dimensioner
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

// Förbered SQL-frågan
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Kör frågan
$stmt->bind_param("s", $profileCode);
$stmt->execute();
$result = $stmt->get_result();

// Organisera resultatet i kluster
$clusters = [];
while ($row = $result->fetch_assoc()) {
    $peMaId = $row['PeMaId'];
    $categoryId = $row['CharTypeId'];
    $clusters[$peMaId][$categoryId][] = $row['DisplayText'];
}

$conn->close();

// Skicka JSON-svar
$response = [
    "numUncertain" => $numUncertain,
    "clusters"     => $clusters,
    "profileCode"  => $profileCode,
    "answerId"     => $answerId
];

echo json_encode($response);
?>
