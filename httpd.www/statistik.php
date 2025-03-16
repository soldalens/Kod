<?php
session_start();

require 'db_connect.php';

    // ---------------------------------------------------------------- Inloggning PHP

    if (!isset($_SESSION['logged_in'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = trim($_POST['userid']);
            $password = trim($_POST['password']);
    
            // Fetch user and password hash
            $stmt = $conn->prepare("SELECT Password FROM Users WHERE UserId = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->bind_result($hashedPassword);
            $stmt->fetch();
            $stmt->close();
    
            if (!$hashedPassword) {
                $error = "Fel användarnamn eller lösenord!";
            } elseif (password_verify($password, $hashedPassword)) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $userId;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "Fel användarnamn eller lösenord!";
            }
    }

?>

    <!---------------------------------------------------------------- HTML - Inloggningsruta -->

    <!DOCTYPE html>
    
    <html lang="en">
    <head>
       
        <meta charset="UTF-8">
        <title>Logga in</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                font-family: Arial, sans-serif;  
                background-color: #214A81;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                color: #F2F2F2;
            }
            .login-container {
                background-color: #163A5F;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
                width: 350px;
            }
            .login-container h2 {
                text-align: center;
                margin-bottom: 20px;
            }
            .form-control {
                margin-bottom: 15px;
            }
            .btn-primary {
                width: 100%;
                background-color: #214A81;
                border-color: #214A81;
            }
            .btn-primary:hover {
                background-color: #163A5F;
            }
            .error {
                color: red;
                text-align: center;
                margin-bottom: 10px;
            }
            #mbtiGrid .col-3 {
                height: 150px;
                border: 1px solid #ccc;
                border-radius: 5px;
                background-color: #f9f9f9;
            }
            #mbtiGrid .col-3 strong {
                font-size: 1.2em;
                display: block;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Logga in</h2>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="text" name="userid" class="form-control" placeholder="Användar-ID" required>
                <input type="password" name="password" class="form-control" placeholder="Lösenord" required>
                <button type="submit" class="btn btn-primary">Logga in</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    // Förstör sessionen och omdirigera användaren
    session_unset(); // Rensa alla session-variabler
    session_destroy(); // Förstör sessionen
    header("Location: administration.php"); // Omdirigera till inloggningssidan
    exit();
}

 //---------------------------------------------------------------- HTML - Inloggningsruta -->

// Fetch the latest ActiveVersionId
$versionResult = $conn->query("SELECT MAX(ActiveVersionId) AS ActiveVersionId FROM Settings");
if ($versionResult && $versionRow = $versionResult->fetch_assoc()) {
    $activeVersionId = $versionRow['ActiveVersionId'];
} else {
    die("Failed to fetch ActiveVersionId");
}

// Fetch available genders for the filter
$gendersResult = $conn->query("SELECT DISTINCT Gender FROM SurveyHeaders WHERE Gender IS NOT NULL ORDER BY Gender");
$genders = $gendersResult->fetch_all(MYSQLI_ASSOC);

// Fetch available personality types for the filter
$personalityTypesResult = $conn->query("SELECT DISTINCT PersonalityType FROM SurveyHeaders WHERE PersonalityType IS NOT NULL ORDER BY PersonalityType");
$personalityTypes = $personalityTypesResult->fetch_all(MYSQLI_ASSOC);

// Fetch available group names for the filter
$groupNamesResult = $conn->query("SELECT DISTINCT GroupName FROM SurveyHeaders WHERE GroupName IS NOT NULL ORDER BY GroupName");
$groupNames = $groupNamesResult->fetch_all(MYSQLI_ASSOC);

// Handle filters
$selectedGenders = isset($_GET['genders']) ? $_GET['genders'] : [];
$selectedPersonalityTypes = isset($_GET['personalityTypes']) ? $_GET['personalityTypes'] : [];
$selectedGroupNames = isset($_GET['groupNames']) ? $_GET['groupNames'] : [];
$selectedSortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'OrderNumber';
$selectedAgeRanges = isset($_GET['ageRanges']) ? $_GET['ageRanges'] : [];

$ageFilter = "";
if (!empty($selectedAgeRanges)) {
    $ageConditions = [];
    foreach ($selectedAgeRanges as $range) {
        [$start, $end] = explode('-', $range);
        $ageConditions[] = "(sh.Age BETWEEN $start AND $end)";
    }
    $ageFilter = " AND (" . implode(" OR ", $ageConditions) . ")";
}

$genderFilter = "";
if (!empty($selectedGenders)) {
    $genderFilter = " AND sh.Gender IN ('" . implode("','", array_map([$conn, 'real_escape_string'], $selectedGenders)) . "')";
}

$personalityTypeFilter = "";
if (!empty($selectedPersonalityTypes)) {
    $personalityTypeFilter = " AND sh.PersonalityType IN ('" . implode("','", array_map([$conn, 'real_escape_string'], $selectedPersonalityTypes)) . "')";
}

$groupNameFilter = "";
if (!empty($selectedGroupNames)) {
    $groupNameFilter = " AND sh.GroupName IN ('" . implode("','", array_map([$conn, 'real_escape_string'], $selectedGroupNames)) . "')";
}

// Determine sort order
$orderClause = "q.OrderNumber";
if ($selectedSortOrder === 'ResponseId') {
    $orderClause = "r_left.ResponseId";
} elseif ($selectedSortOrder === 'Difference') {
    $orderClause = "ABS((SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId IN (1, 3, 5, 7) $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) - (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId IN (2, 4, 6, 8) $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter)) DESC";
}

// Build the query
$query = "
SELECT 
    q.QuestionID, 
    q.OrderNumber, 
    q.QuestionText, 
    ao_left.AnswerText AS AnswerTextLeft, 
    ao_right.AnswerText AS AnswerTextRight, 
    r_left.ResponseDescr AS ResponseDescrLeft, 
    r_right.ResponseDescr AS ResponseDescrRight, 
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 1 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count1, 
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 2 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count2,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 3 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count3,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 4 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count4,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 5 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count5,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 6 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count6,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 7 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count7,
    (SELECT COUNT(*) FROM SurveyAnswers sa JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId WHERE sa.QuestionId = q.QuestionID AND sa.ResponseId = 8 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter) AS Count8
FROM Questions q
LEFT JOIN AnswerOptions ao_left ON ao_left.QuestionID = q.QuestionID AND ao_left.ResponseId IN (1, 3, 5, 7)
LEFT JOIN AnswerOptions ao_right ON ao_right.QuestionID = q.QuestionID AND ao_right.ResponseId IN (2, 4, 6, 8)
LEFT JOIN ResponseOptions r_left ON r_left.ResponseId = ao_left.ResponseId
LEFT JOIN ResponseOptions r_right ON r_right.ResponseId = ao_right.ResponseId
WHERE q.isActive = 1 AND q.VersionId = '$activeVersionId'
GROUP BY q.QuestionID
ORDER BY $orderClause";

$result = $conn->query($query);

$responseMapping = [
    'Extroversion' => 'E',
    'Introversion' => 'I',
    'Sensing' => 'S',
    'Intuition' => 'N',
    'Thinking' => 'T',
    'Feeling' => 'F',
    'Judging' => 'J',
    'Perceiving' => 'P'
];

// Fetch ResponseDescr statistics
$responseStatsQuery = "
SELECT r.ResponseDescr, COUNT(sa.AnswerId) AS ResponseCount
FROM SurveyAnswers sa
JOIN ResponseOptions r ON sa.ResponseId = r.ResponseId
JOIN SurveyHeaders sh ON sa.AnswerId = sh.AnswerId
WHERE 1=1 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter
GROUP BY r.ResponseDescr";
$responseStatsResult = $conn->query($responseStatsQuery);

// Initialize response statistics
$responseStats = [
    'E' => 0, 'I' => 0,
    'S' => 0, 'N' => 0,
    'T' => 0, 'F' => 0,
    'J' => 0, 'P' => 0,
];
$totalResponses = 0;


// Fetch data and map ResponseDescr to stats
$responseStatsResult = $conn->query($responseStatsQuery);
if ($responseStatsResult) {
    while ($row = $responseStatsResult->fetch_assoc()) {
        // Map the response descriptions to their corresponding keys
        if (isset($responseMapping[$row['ResponseDescr']])) {
            $mappedKey = $responseMapping[$row['ResponseDescr']];
            if (isset($responseStats[$mappedKey])) {
                $responseStats[$mappedKey] = $row['ResponseCount'];
            }
        }
    }
}

// Calculate percentages for each pair
$percentStats = [
    'E/I' => [
        'E' => $responseStats['E'] + $responseStats['I'] > 0 
            ? round(($responseStats['E'] / ($responseStats['E'] + $responseStats['I'])) * 100, 0) 
            : 0,
        'I' => $responseStats['E'] + $responseStats['I'] > 0 
            ? round(($responseStats['I'] / ($responseStats['E'] + $responseStats['I'])) * 100, 0) 
            : 0,
    ],
    'S/N' => [
        'S' => $responseStats['S'] + $responseStats['N'] > 0 
            ? round(($responseStats['S'] / ($responseStats['S'] + $responseStats['N'])) * 100, 0) 
            : 0,
        'N' => $responseStats['S'] + $responseStats['N'] > 0 
            ? round(($responseStats['N'] / ($responseStats['S'] + $responseStats['N'])) * 100, 0) 
            : 0,
    ],
    'T/F' => [
        'T' => $responseStats['T'] + $responseStats['F'] > 0 
            ? round(($responseStats['T'] / ($responseStats['T'] + $responseStats['F'])) * 100, 0) 
            : 0,
        'F' => $responseStats['T'] + $responseStats['F'] > 0 
            ? round(($responseStats['F'] / ($responseStats['T'] + $responseStats['F'])) * 100, 0) 
            : 0,
    ],
    'J/P' => [
        'J' => $responseStats['J'] + $responseStats['P'] > 0 
            ? round(($responseStats['J'] / ($responseStats['J'] + $responseStats['P'])) * 100, 0) 
            : 0,
        'P' => $responseStats['J'] + $responseStats['P'] > 0 
            ? round(($responseStats['P'] / ($responseStats['J'] + $responseStats['P'])) * 100, 0) 
            : 0,
    ],
];

// Fetch unique respondent count
$respondentCountQuery = "
SELECT COUNT(DISTINCT sh.AnswerId) AS RespondentCount
FROM SurveyHeaders sh
JOIN SurveyAnswers sa ON sh.AnswerId = sa.AnswerId AND sh.VersionId = '$activeVersionId'
WHERE 1=1 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter";
$respondentCountResult = $conn->query($respondentCountQuery);
$respondentCount = $respondentCountResult->fetch_assoc()['RespondentCount'];

if ($result->num_rows > 0):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #FFFFFF;
            color: #F2F2F2;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: left;
            color: #F2F2F2;
            font-size: 28px;
        }
        .table {
            margin: 20px 0;
            background-color: #2B65B3; /* Blå bakgrund */
            border-radius: 8px; /* Rundade hörn */
            color: #F2F2F2; /* Textfärg */
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); /* Skugga */
            overflow: hidden; /* För att hålla hörnen rundade */
        }
        
        .table thead {
            background-color: #163A5F; /* Mörkare blå för rubriken */
            color: #F2F2F2;
        }
        .table tbody tr:nth-child(even) {
            background-color: #163A5F; /* Ljusare blå bakgrund för jämna rader */
        }
        
        .table tbody tr:nth-child(odd) {
            background-color: #214A81; /* Huvudfärg för udda rader */
        }
        .small-text-table {
            font-size: 12px; /* Anpassa storleken som du vill */
        }
        .container {
            max-width: 90%;
            margin: 20px auto;
            background-color: #214A81;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        }    
        .form-label {
            font-weight: bold;
            color: #F2F2F2;
            font-size: 14px !important;
        }
        .btn-secondary {
            background-color: #163A5F ;
            border-color: #163A5F ;
            color: #FFFFFF ;
            font-size: 14px;
            
        .niklas-button {
            font-size: 14px !important;
            float: right !important;
        }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 10px; /* Mellanrum mellan elementen */
            margin-top: 15px;
        }
        
        .form-actions .btn {
            font-size: 14px !important
        }
        .btn-secondary:hover {
            background-color: #102A47;
            border-color: #102A47 ;
            font-size: 12px;
        }
        .table {
            margin-top: 20px;
            background-color: #214A81;
            color: #F2F2F2;
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead {
            background-color: #163A5F;
        }
        .table tbody tr:nth-child(even) {
            background-color: #163A5F;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #214A81;
        }
        .form-select {
            font-size: 12px !important; /* Tvingar igenom fontstorleken */
        }
        .statistics-section {
            margin-bottom: 20px;
            background-color: #163A5F;
            padding: 15px;
            border-radius: 8px;
            color: #F2F2F2;
        }
        
        .statistics-section h2 {
            color: #FCD169;
        }
        
        .statistics-section .table {
            background-color: #214A81;
            color: #F2F2F2;
            font-size: 12px !important;
        }
        
    </style>
</head>
<body>
<div class="container">
    <h1>Statistik med filter</h1>
    <form method="GET">
        <div class="row">
            <div class="col-md-2">
                <label for="genders" class="form-label">Kön:</label>
                <select name="genders[]" id="genders" class="form-select" multiple size="8" multiple style="font-size: 12px;">
                    <?php foreach ($genders as $gender): ?>
                        <option value="<?= htmlspecialchars($gender['Gender']) ?>" <?= in_array($gender['Gender'], $selectedGenders) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($gender['Gender']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ageRanges" class="form-label">Ålder:</label>
                <select name="ageRanges[]" id="ageRanges" class="form-select" multiple size="8" style="font-size: 12px;">
                    <?php 
                    $ageRanges = ['10-19', '20-29', '30-39', '40-49', '50-59', '60-69', '70-79', '80-89'];
                    foreach ($ageRanges as $range): ?>
                        <option value="<?= htmlspecialchars($range) ?>" <?= in_array($range, $_GET['ageRanges'] ?? []) ? 'selected' : '' ?>><?= $range ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="personalityTypes" class="form-label">Personality Type:</label>
                <div style="display: flex; gap: 10px;">
                    <select name="personalityTypes[]" id="personalityTypes1" class="form-select" multiple size="8" style="font-size: 12px; flex: 1;">
                        <?php
                        $half = ceil(count($personalityTypes) / 2);
                        foreach (array_slice($personalityTypes, 0, $half) as $type):
                        ?>
                            <option value="<?= htmlspecialchars($type['PersonalityType']) ?>" <?= in_array($type['PersonalityType'], $selectedPersonalityTypes) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['PersonalityType']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="personalityTypes[]" id="personalityTypes2" class="form-select" multiple size="8" style="font-size: 12px; flex: 1;">
                        <?php
                        foreach (array_slice($personalityTypes, $half) as $type):
                        ?>
                            <option value="<?= htmlspecialchars($type['PersonalityType']) ?>" <?= in_array($type['PersonalityType'], $selectedPersonalityTypes) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['PersonalityType']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <label for="groupNames" class="form-label">Grupp:</label>
                <select name="groupNames[]" id="groupNames" class="form-select" multiple size="8" multiple style="font-size: 12px;">
                    <?php foreach ($groupNames as $group): ?>
                        <option value="<?= htmlspecialchars($group['GroupName']) ?>" <?= in_array($group['GroupName'], $selectedGroupNames) ? 'selected' : '' ?>><?= htmlspecialchars($group['GroupName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="sortOrder" class="form-label">Sortera efter:</label>
                <select name="sortOrder" id="sortOrder" class="form-select" multiple size="8" multiple style="font-size: 12px;">
                    <option value="OrderNumber" <?= $selectedSortOrder === 'OrderNumber' ? 'selected' : '' ?>>Nummer</option>
                    <option value="ResponseId" <?= $selectedSortOrder === 'ResponseId' ? 'selected' : '' ?>>Typ</option>
                    <option value="Difference" <?= $selectedSortOrder === 'Difference' ? 'selected' : '' ?>>Skillnad</option>
                </select>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <button type="submit" class="btn btn-secondary" style="background-color: #2979C9; border-color: #102A47;">Filter</button>
                <button type="button" onclick="window.location.href='?'" class="btn btn-secondary" style="background-color: #2979C9; border-color: #102A47; float: right;">Rensa filter</button>
            </div>
        </div>
    </form>
</div>
<div class="container" style="background-color: #214A81;">
    <!-- Statistiksektion -->
    <div class="row">
        <div class="col-md-4">
            <!-- Statistiktabell -->
            <div class="statistics-section">
                <p style="color: #F2F2F2;"><strong>Antal respondenter:</strong> <?= $respondentCount ?></p>
                <table class="table table-bordered" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th>Dimension</th>
                            <th style="text-align: center;">Procent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>E/I</td>
                            <td style="text-align: center;"><?= $percentStats['E/I']['E'] ?>%&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;<?= $percentStats['E/I']['I'] ?>%</td>
                        </tr>
                        <tr>
                            <td>S/N</td>
                            <td style="text-align: center;"><?= $percentStats['S/N']['S'] ?>%&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;<?= $percentStats['S/N']['N'] ?>%</td>
                        </tr>
                        <tr>
                            <td>T/F</td>
                            <td style="text-align: center;"><?= $percentStats['T/F']['T'] ?>%&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;<?= $percentStats['T/F']['F'] ?>%</td>
                        </tr>
                        <tr>
                            <td>J/P</td>
                            <td style="text-align: center;"><?= $percentStats['J/P']['J'] ?>%&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;<?= $percentStats['J/P']['P'] ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="col-md-2" style="align-self: flex-start;">
            <!-- Tabell för kön -->
            <table class="table table-bordered" style="font-size: 12px;">
                <br>
                <thead>
                    <tr>
                        <th>Kön</th>
                        <th style="text-align: center;">Antal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $genderCountQuery = "
                    SELECT sh.Gender, COUNT(DISTINCT sh.AnswerId) AS GenderCount
                    FROM SurveyHeaders sh
                    WHERE 1=1 $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter AND sh.VersionId = '$activeVersionId'
                    GROUP BY sh.Gender";
                    $genderCountResult = $conn->query($genderCountQuery);
        
                    while ($genderRow = $genderCountResult->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($genderRow['Gender']) . "</td>
                                <td style='text-align: center;'>" . $genderRow['GenderCount'] . "</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>


        <div class="col-md-6">
            <!-- Matris med personlighetstyper -->
            <div class="matrix-section" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px;">
                <?php
                $personalityMatrix = ["ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP", "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"];
                foreach ($personalityMatrix as $type) {
                    // Query to get count of respondents for each personality type
                    $typeCountQuery = "
                    SELECT COUNT(DISTINCT sh.AnswerId) AS TypeCount
                    FROM SurveyHeaders sh
                    WHERE sh.PersonalityType = '$type' $genderFilter $personalityTypeFilter $groupNameFilter $ageFilter AND sh.VersionId = '$activeVersionId'";
                    $typeCountResult = $conn->query($typeCountQuery);
                    $typeCount = $typeCountResult->fetch_assoc()['TypeCount'] ?? 0;
                    echo "<div style='background-color: #FFFFFF; text-align: center; padding: 2px; border: 1px solid #ccc;'>
                            <span style='font-size: 12px; color: #000;'><strong>$type</strong></span><br>
                            <span style='font-size: 14px; color: #000;'>$typeCount</span>
                          </div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>


<div class="container" style="background-color: #214A81;">
    
    <table class="table small-text-table" style="margin: 0; padding: 0; width: 100%;">
        <thead>
            <tr>
                <th>#</th>
                <th>Fråga</th>
                <th>Svar 1</th>
                <th>Typ</th>
                <th>Del</th>
                <th>Svar 2</th>
                <th>Typ</th>
                <th>Del</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['OrderNumber'] ?></td>
                    <td><?= $row['QuestionText'] ?></td>
                    <td style="<?= (($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= $row['AnswerTextLeft'] ?>
                    </td>
                    <td style="<?= (($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= $row['ResponseDescrLeft'] ?>
                    </td>
                    <td style="<?= (($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= round((($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100, 1) ?>%
                    </td>
                    <td style="<?= (($row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= $row['AnswerTextRight'] ?>
                    </td>
                    <td style="<?= (($row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= $row['ResponseDescrRight'] ?>
                    </td>
                    <td style="<?= (($row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100 > 50 ? 'background-color: #DAF2D0;' : '' ?>">
                        <?= round((($row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8']) / ($row['Count1'] + $row['Count3'] + $row['Count5'] + $row['Count7'] + $row['Count2'] + $row['Count4'] + $row['Count6'] + $row['Count8'])) * 100, 1) ?>%
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php
else:
    echo "<p class='text-center text-light'>No results found.</p>";
endif;

$conn->close();
?>
