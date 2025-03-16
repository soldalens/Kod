<?php

if (isset($_GET['AnswerId'])) {
    $AnswerId = intval($_GET['AnswerId']); // Konvertera till ett heltal
}

require 'db_connect.php';

// Kontrollera om answerId är tillgängligt
if (!isset($_GET['AnswerId'])) {
    die("Ingen AnswerId angiven.");
}

$AnswerId = $_GET['AnswerId'];

// Hämta data från databasen
$stmt = $conn->prepare("
    SELECT ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent, PersonalityType, ProfileCode, LanguageId 
    FROM SurveyHeaders 
    WHERE AnswerId = ?
");
$stmt->bind_param("i", $AnswerId); // Använd "i" för att binda som ett heltal
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ingen data hittades för AnswerId: " . htmlspecialchars($AnswerId));
}

$row = $result->fetch_assoc();
$stmt->close();

$languageId = $row['LanguageId'];

// Bestäm vilka kolumner som ska användas beroende på LanguageId
if ($languageId == 1) {
    $infoTypeColumn = "it.InfoTypeDescr";
    $infoTextColumn = "i.InfoText";
} else {
    $infoTypeColumn = "it.InfoTypeDescrENG";
    $infoTextColumn = "i.InfoTextENG";
}

// Hämta beskrivning av personlighetstypen
$descriptionStmt = $conn->prepare("
    SELECT i.InfoTypeId, $infoTypeColumn as InfoTypeDescr, $infoTextColumn as InfoText
    FROM Info AS i
    LEFT JOIN InfoType AS it ON it.InfoTypeId = i.InfoTypeId
    WHERE i.PeMaId = (SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr = ?)
    ORDER BY i.InfoTypeId
");
$descriptionStmt->bind_param("s", $row['PersonalityType']);
$descriptionStmt->execute();
$descriptionResult = $descriptionStmt->get_result();

// Bygg upp strukturen för beskrivningen
$personalityDescription = [];
while ($descRow = $descriptionResult->fetch_assoc()) {
    $personalityDescription[$descRow['InfoTypeId']]['InfoTypeDescr'] = $descRow['InfoTypeDescr'];
    $personalityDescription[$descRow['InfoTypeId']]['texts'][] = $descRow['InfoText'];
}
$descriptionStmt->close();

$conn->close();

// Rendera grafiken
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detaljerad Information</title>
    <style>
        body {
            background-color: #F8F9FA;
            /*color: #333;*/
            font-family: Tahoma, sans-serif;
            padding: 20px;
        }

        .progress-container {
            position: relative;
            margin-bottom: 30px;
        }
        /* Adjust spacing for the heading */
        h2 {
            margin-top: 10px; /* Less vertical space above */
            margin-bottom: 30px; /* More vertical space below */
        }
        
        /* Progress container spacing */
        .progress-container {
            margin-top: 20px; /* Adds spacing between the heading and the first bar */
            margin-bottom: 30px; /* Existing spacing between bars */
        }
        /* Reduce spacing after the last progress bar */
        .progress-container:last-child {
            margin-bottom: 1px; /* Reduced space below the last bar */
        }

        .progress {
            display: flex;
            height: 40px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            z-index: 2; /* Staplarna har högre z-index */
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            text-align: center;
            font-size: 14px;
            white-space: nowrap;
        }

        /* Vertikal linje */
        .vertical-line {
            position: absolute;
            top: 0;
            left: 50%; /* Centrera linjen */
            width: 2px; /* Tjocklek på linjen */
            height: calc(100% - 20px); /* Justera så att den inte når texten */
            background-color: black;
            z-index: 1; /* Linjen ligger bakom staplarna */
        }

        /* Rubrikstil */
        .title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px; /* Mindre typsnitt */
            margin-bottom: 5px;
            color: #214A81;
        }

        /* Färger för staplar */
        .bg-extrovert-dark { background-color: #FF9C00; color: black; }
        .bg-extrovert-light { background-color: #FFE2C5; }

        .bg-sensing-dark { background-color: #00B100; color: white; }
        .bg-sensing-light { background-color: #E6FFE6; }

        .bg-thinking-dark { background-color: #00E2EF; color: black; }
        .bg-thinking-light { background-color: #D5FFFF; }

        .bg-judging-dark { background-color: #4F57FF; color: white; }
        .bg-judging-light { background-color: #D4EEFF; }
        
        .large-bold {
            font-size: 18px; /* Adjust size to make it prominent */
            font-weight: bold; /* Makes the text bold */
        }
        .description-container {
            margin-top: 30px;
            padding: 20px;
            background-color: #F8F9FA;
            color: #214A81;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            font-family: Tahoma, sans-serif;
            font-size: 11px;
        }
        .description-container h3 {
            font-size: 1.3rem;
            color: #214A81;
            margin-bottom: 15px;
        }
        .description-container h4 {
            font-size: 1.15rem;
            color: #214A81;
            margin-top: 15px;
        }

    </style>
</head>
<body>
    
    <h2 class="text-center">
        <?php 
        echo ($languageId == 1) ? "Din personlighetstyp är sannolikt:" : "Your personality type is probably:"; 
        ?>
        <?php 
        echo htmlspecialchars($row['PersonalityType']); 
        if (!empty($row['ProfileCode'])) {
            echo " (" . htmlspecialchars($row['ProfileCode']) . ")";
        }
        ?>
    </h2>
<div class="description-container">
    <div class="progress-container">
        <div class="title-container">
            <span>
                <span class="large-bold">E</span> (Extroversion)
            </span>
            <span>
                (Introversion) <span class="large-bold">I</span>
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar <?php echo $row['ExtroversionPercent'] > 50 ? 'bg-extrovert-dark' : 'bg-extrovert-light'; ?>" 
                style="width: <?php echo $row['ExtroversionPercent']; ?>%;">
                <?php if ($row['ExtroversionPercent'] > 50): ?>
                    Extraversion: <?php echo $row['ExtroversionPercent']; ?>%
                <?php endif; ?>
            </div>
            <div class="progress-bar <?php echo $row['ExtroversionPercent'] <= 50 ? 'bg-extrovert-dark' : 'bg-extrovert-light'; ?>" 
                style="width: <?php echo 100 - $row['ExtroversionPercent']; ?>%;">
                <?php if ($row['ExtroversionPercent'] <= 50): ?>
                    Introversion: <?php echo 100 - $row['ExtroversionPercent']; ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="vertical-line"></div>
    </div>

    <div class="progress-container">
        <div class="title-container">
            <span>
                <span class="large-bold">S</span> (Sensing)
            </span>
            <span>
                (Intuition) <span class="large-bold">N</span>
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar <?php echo $row['SensingPercent'] > 50 ? 'bg-sensing-dark' : 'bg-sensing-light'; ?>" 
                style="width: <?php echo $row['SensingPercent']; ?>%;">
                <?php if ($row['SensingPercent'] > 50): ?>
                    Sensing: <?php echo $row['SensingPercent']; ?>%
                <?php endif; ?>
            </div>
            <div class="progress-bar <?php echo $row['SensingPercent'] <= 50 ? 'bg-sensing-dark' : 'bg-sensing-light'; ?>" 
                style="width: <?php echo 100 - $row['SensingPercent']; ?>%;">
                <?php if ($row['SensingPercent'] <= 50): ?>
                    Intuition: <?php echo 100 - $row['SensingPercent']; ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="vertical-line"></div>
    </div>

    <div class="progress-container">
        <div class="title-container">
            <span>
                <span class="large-bold">T</span> (Thinking)
            </span>
            <span>
                (Feeling) <span class="large-bold">F</span>
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar <?php echo $row['ThinkingPercent'] > 50 ? 'bg-thinking-dark' : 'bg-thinking-light'; ?>" 
                style="width: <?php echo $row['ThinkingPercent']; ?>%;">
                <?php if ($row['ThinkingPercent'] > 50): ?>
                    Thinking: <?php echo $row['ThinkingPercent']; ?>%
                <?php endif; ?>
            </div>
            <div class="progress-bar <?php echo $row['ThinkingPercent'] <= 50 ? 'bg-thinking-dark' : 'bg-thinking-light'; ?>" 
                style="width: <?php echo 100 - $row['ThinkingPercent']; ?>%;">
                <?php if ($row['ThinkingPercent'] <= 50): ?>
                    Feeling: <?php echo 100 - $row['ThinkingPercent']; ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="vertical-line"></div>
    </div>

    <div class="progress-container">
        <div class="title-container">
            <span>
                <span class="large-bold">J</span> (Judging)
            </span>
            <span>
                (Perceiving) <span class="large-bold">P</span>
            </span>
        </div>
        <div class="progress">
            <div class="progress-bar <?php echo $row['JudgingPercent'] > 50 ? 'bg-judging-dark' : 'bg-judging-light'; ?>" 
                style="width: <?php echo $row['JudgingPercent']; ?>%;">
                <?php if ($row['JudgingPercent'] > 50): ?>
                    Judging: <?php echo $row['JudgingPercent']; ?>%
                <?php endif; ?>
            </div>
            <div class="progress-bar <?php echo $row['JudgingPercent'] <= 50 ? 'bg-judging-dark' : 'bg-judging-light'; ?>" 
                style="width: <?php echo 100 - $row['JudgingPercent']; ?>%;">
                <?php if ($row['JudgingPercent'] <= 50): ?>
                    Perceiving: <?php echo 100 - $row['JudgingPercent']; ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="vertical-line"></div>
   </div>
</div>

<div class="page-break"></div>

<br class="print-only">
<br class="print-only">


<div class="description-container">
    <!-- Visa huvudrubrik (InfoTypeId = 0) -->
    <?php if (!empty($personalityDescription[0]['texts'][0])): ?>
        <h3><strong><?php echo htmlspecialchars($personalityDescription[0]['texts'][0]); ?></strong></h3>
    <?php endif; ?>

    <!-- Visa löptext (InfoTypeId = 1) -->
    <?php if (!empty($personalityDescription[1]['texts'][0])): ?>
        <p><?php echo nl2br(htmlspecialchars($personalityDescription[1]['texts'][0])); ?></p>
    <?php endif; ?>

    <!-- Visa rubriker och punktlistor för InfoTypeId >= 2 -->
    <?php foreach ($personalityDescription as $infoTypeId => $infoData): ?>
        <?php if ($infoTypeId >= 2): ?>
            <h4><?php echo htmlspecialchars($infoData['InfoTypeDescr'] ?? "Okänd kategori"); ?></h4>
            <ul>
                <?php foreach ($infoData['texts'] as $text): ?>
                    <li><?php echo nl2br(htmlspecialchars($text)); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

</body>
</html>
