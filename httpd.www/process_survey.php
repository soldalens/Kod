<?php
include 'db_connect.php';

// Hämta användarens val
$selectedCluster = $_POST['selected_cluster'] ?? null;
$profileCode = $_POST['profile_code'] ?? null;
$answerId = $_POST['answer_id'] ?? null;

if (!$selectedCluster || !$profileCode || !$answerId) {
    die("Ogiltig begäran.");
}

// Endast dessa dimensioner finns i databasen och kan uppdateras
$savedDimensions = ['E', 'S', 'T', 'J']; 

// Hämta de osäkra dimensionerna från ProfileCode
$uncertainDimensions = [];
$dimensionMap = [
    0 => 'E', 1 => 'S', 2 => 'T', 3 => 'J' // Positionerna i profileCode
];

for ($i = 0; $i < strlen($profileCode); $i++) {
    if ($profileCode[$i] === '?' && in_array($dimensionMap[$i], $savedDimensions)) {
        $uncertainDimensions[] = $dimensionMap[$i];
    }
}

if (empty($uncertainDimensions)) {
    header("Location: results.php?answerId=$answerId");
    exit();
}

// Hämta vald personlighetstyp
$sql = "SELECT PeMaDescr FROM PeMaTypes WHERE PeMaId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selectedCluster);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $selectedProfile = $row['PeMaDescr'];
} else {
    die("Kunde inte hitta vald personlighetstyp.");
}
$stmt->close();

// Mappa bokstäver till rätt kolumnnamn i databasen
$responseMapping = [
    'E' => 'ExtroversionPercent',
    'S' => 'SensingPercent',
    'T' => 'ThinkingPercent',
    'J' => 'JudgingPercent'
];

$updates = [];
$sqlUpdates = []; // Sparar SQL-frågor för alert

foreach ($uncertainDimensions as $letter) {
    // Determine the opposite letter (not used further here)
    $opposite = [
        'E' => 'I', 'S' => 'N', 'T' => 'F', 'J' => 'P'
    ][$letter];

    // Check if the column exists in the database
    if (!isset($responseMapping[$letter])) {
        continue;
    }

    $positiveColumn = $responseMapping[$letter];

    // Determine the adjustment: +10 if the selected profile contains the letter, otherwise -10.
    $adjustment = (strpos($selectedProfile, $letter) !== false) ? 10 : -10;

    // Create the adjustment text, e.g. "E 10" or "S -10"
    $adjustmentText = $letter . " " . $adjustment;

    // Update the database:
    // - Increase the value of $positiveColumn by the adjustment,
    // - Set ExtraQuestionDone to 1,
    // - Append the new adjustment to the existing Adjustment column.
    $sql = "UPDATE SurveyHeaders 
            SET $positiveColumn = $positiveColumn + ?,
                ExtraQuestionDone = 1,
                Adjustment = TRIM(CONCAT(IFNULL(Adjustment, ''), ' ', ?))
            WHERE AnswerId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $adjustment, $adjustmentText, $answerId);
    $stmt->execute();

    // Store the SQL statement for debugging/alert purposes
    $sqlUpdates[] = "UPDATE SurveyHeaders 
                      SET $positiveColumn = $positiveColumn + $adjustment,
                          ExtraQuestionDone = 1,
                          Adjustment = TRIM(CONCAT(IFNULL(Adjustment, ''), ' ', '$adjustmentText'))
                      WHERE AnswerId = $answerId;";
}


// Stäng anslutningen
$stmt->close();

$sql = "UPDATE SurveyHeaders SET PersonalityType = ? WHERE AnswerId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $selectedProfile, $answerId);
$stmt->execute();
$stmt->close();

$conn->close();


// Omdirigera användaren
echo "<script>window.location.href = 'results.php?AnswerId=$answerId';</script>";
exit();
