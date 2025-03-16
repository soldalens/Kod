<?php
require 'db_connect.php';

// Kontrollera om AnswerId finns i URL
if (!isset($_GET['AnswerId']) || empty($_GET['AnswerId'])) {
    die("AnswerId saknas i URL.");
}

$answerId = intval($_GET['AnswerId']);

// Hämta personlighetsprocent från SurveyHeaders
$sql = "SELECT ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent FROM SurveyHeaders WHERE AnswerId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $answerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ingen data hittades för detta AnswerId.");
}

$row = $result->fetch_assoc();
$extroversionPercent = $row['ExtroversionPercent'];
$sensingPercent = $row['SensingPercent'];
$thinkingPercent = $row['ThinkingPercent'];
$judgingPercent = $row['JudgingPercent'];
$stmt->close();

// Kontrollera hur många av procentsatserna som är mellan 42 och 58
$uncertainDimensions = [];

if ($extroversionPercent >= 46 && $extroversionPercent <= 54) {
    $uncertainDimensions[] = 'E';
}
if ($sensingPercent >= 46 && $sensingPercent <= 54) {
    $uncertainDimensions[] = 'S';
}
if ($thinkingPercent >= 46 && $thinkingPercent <= 54) {
    $uncertainDimensions[] = 'T';
}
if ($judgingPercent >= 46 && $judgingPercent <= 54) {
    $uncertainDimensions[] = 'J';
}

// Om inga eller fler än två dimensioner är osäkra, gå vidare utan att hämta extra frågor
if (count($uncertainDimensions) === 0 || count($uncertainDimensions) > 2) {
    $profileCode = null;
} else {
    // Skapa en profilkod där osäkra dimensioner ersätts med '?'
    $profileCode = '';
    $profileCode .= in_array('E', $uncertainDimensions) ? '?' : ($extroversionPercent >= 50 ? 'E' : 'I');
    $profileCode .= in_array('S', $uncertainDimensions) ? '?' : ($sensingPercent >= 50 ? 'S' : 'N');
    $profileCode .= in_array('T', $uncertainDimensions) ? '?' : ($thinkingPercent > 50 ? 'T' : 'F');
    $profileCode .= in_array('J', $uncertainDimensions) ? '?' : ($judgingPercent > 50 ? 'J' : 'P');

    // ✅ Spara ProfileCode i SurveyHeaders
    $sqlUpdateProfileCode = "UPDATE SurveyHeaders SET ProfileCode = ? WHERE AnswerId = ?";
    $stmtProfile = $conn->prepare($sqlUpdateProfileCode);
    $stmtProfile->bind_param("si", $profileCode, $answerId);
    $stmtProfile->execute();
    $stmtProfile->close();
}

$conn->close();

// Om det finns en osäker profilkod, skicka vidare användaren till extra_survey.php
if ($profileCode !== null) {
    header("Location: https://proanalys.se/proanalyse?AnswerId=$answerId&ProfileCode=$profileCode");
    exit();
}
?>
