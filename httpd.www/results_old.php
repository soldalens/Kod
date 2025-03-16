<?php

require 'db_connect.php';

// Hämta AnswerId från URL
if (!isset($_GET['AnswerId']) || empty($_GET['AnswerId'])) {
    die("AnswerId saknas i URL.");
}

$answerId = intval($_GET['AnswerId']);

// Hämta aktuell version från Settings-tabellen
$sqlVersion = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$resultVersion = $conn->query($sqlVersion);
$activeVersionId = $resultVersion->fetch_assoc()['ActiveVersionId'];

// Hämta svar och beräkna totalpoäng per ResponseId
$sql = "SELECT sa.ResponseId, SUM(q.Value*ao.Multiplicator) AS TotalScore
        FROM SurveyAnswers sa
        INNER JOIN Questions q ON sa.QuestionId = q.QuestionId
        INNER JOIN AnswerOptions as ao ON sa.QuestionId = ao.QuestionId AND sa.ResponseId = ao.ResponseId
        WHERE sa.AnswerId = ? AND q.VersionId = ?
        GROUP BY sa.ResponseId";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $answerId, $activeVersionId);
$stmt->execute();
$result = $stmt->get_result();

$responses = [];
while ($row = $result->fetch_assoc()) {
    $responses[$row['ResponseId']] = $row['TotalScore'];
}
$stmt->close();

// Initialisera poäng för varje kategori
$extroversion = $responses[1] ?? 0;
$introversion = $responses[2] ?? 0;
$sensing = $responses[3] ?? 0;
$intuition = $responses[4] ?? 0;
$thinking = $responses[5] ?? 0;
$feeling = $responses[6] ?? 0;
$judging = $responses[7] ?? 0;
$perceiving = $responses[8] ?? 0;

// Funktion för att beräkna procentvärden
function calculatePercentage($value1, $value2) {
    $total = $value1 + $value2;
    return $total > 0 ? round(($value1 / $total) * 100) : 0;
}

// Beräkna procent
$extroversionPercent = calculatePercentage($extroversion, $introversion);
$sensingPercent = calculatePercentage($sensing, $intuition);
$thinkingPercent = calculatePercentage($thinking, $feeling);
$judgingPercent = calculatePercentage($judging, $perceiving);

//MODIFIERINGAR

// Hämta gender från SurveyHeaders baserat på AnswerId
$sqlGender = "SELECT Gender FROM SurveyHeaders WHERE AnswerId = ?";
$stmtGender = $conn->prepare($sqlGender);
$stmtGender->bind_param("i", $answerId);
$stmtGender->execute();
$resultGender = $stmtGender->get_result();
$gender = $resultGender->fetch_assoc()['Gender'] ?? null;
$stmtGender->close();

// Kontrollera och modifiera värden baserat på gender
if ($gender === 'man') {
    // Kontrollera om två eller fler procentvärden är mellan 42 och 58
    $rangeCount = 0;
    foreach ([$extroversionPercent, $sensingPercent, $thinkingPercent, $judgingPercent] as $percent) {
        if ($percent >= 42 && $percent <= 58) {
            $rangeCount++;
        }
    }
    if ($rangeCount >= 2) {
        $judgingPercent = max(0, $judgingPercent - 0); // Minska Judging med 10, minimum 0
    }

    // Kontrollera ThinkingPercent
    if ($thinkingPercent < 58) {
        $thinkingPercent = max(0, $thinkingPercent - 10); // Minska Thinking med 10, minimum 0
    }
} elseif ($gender === 'kvinna') {
    // Kontrollera ThinkingPercent för kvinnor
    if ($thinkingPercent >= 42 && $thinkingPercent <= 50) {
        $thinkingPercent = min(100, $thinkingPercent + 10); // Öka Thinking med 10, max 100
    }
} else {
    // För andra kön än 'man' och 'kvinna', minska ThinkingPercent med 10
    $thinkingPercent = max(0, $thinkingPercent - 10); // Minska Thinking med 10, minimum 0
}

//SLUT MODIFIERINGAR

$introversionPercent = 100 - $extroversionPercent;
$intuitionPercent = 100 - $sensingPercent;
$feelingPercent = 100 - $thinkingPercent;
$perceivingPercent = 100 - $judgingPercent;

// Bestäm personlighetstyp
$personalityType = '';
$personalityType .= $extroversionPercent >= 50 ? 'E' : 'I';
$personalityType .= $sensingPercent >= 50 ? 'S' : 'N';
$personalityType .= $thinkingPercent > 50 ? 'T' : 'F';
$personalityType .= $judgingPercent > 50 ? 'J' : 'P';

// Spara till databasen
$saveSql = "UPDATE SurveyHeaders 
            SET PersonalityType = ?, ExtroversionPercent = ?, SensingPercent = ?, ThinkingPercent = ?, JudgingPercent = ? 
            WHERE AnswerId = ?";
$saveStmt = $conn->prepare($saveSql);
$saveStmt->bind_param(
    "siiiii", 
    $personalityType, 
    $extroversionPercent, 
    $sensingPercent, 
    $thinkingPercent, 
    $judgingPercent, 
    $answerId
);

if ($saveStmt->execute()) {
    echo "";
} else {
    echo "Fel vid sparande av resultat: " . $conn->error;
}

$saveStmt->close();

$conn->close(); // Stäng databasen först


include 'send_email_result.php';

 //Säkerställ att sidan öppnas utanför en iframe och inkludera AnswerId i URL:en
echo '<script type="text/javascript">';
echo 'var answerId = ' . json_encode($answerId) . ';'; // Gör AnswerId tillgängligt i JavaScript
echo 'if (window.top !== window.self) {';
echo '    window.top.location.href = "https://proanalys.se/analysen-inskickad.html?AnswerId=" + answerId;';
echo '} else {';
echo '    window.location.href = "https://proanalys.se/analysen-inskickad.html?AnswerId=" + answerId;';
echo '}';
echo '</script>';

// Som fallback om JavaScript inte är aktiverat
echo '<noscript>';
echo '<meta http-equiv="refresh" content="0; url=https://proanalys.se/analysen-inskickad.html?AnswerId=' . htmlspecialchars($answerId) . '">';
echo '</noscript>';

exit();


?>

