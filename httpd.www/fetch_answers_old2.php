<?php
// Databasinställningar

require 'db_connect.php';

// Kontrollera om AnswerId finns
if (!isset($_GET['AnswerId']) || empty($_GET['AnswerId'])) {
    die("AnswerId saknas.");
}

$answerId = intval($_GET['AnswerId']);

// Hämta värden för `BestPerson` och `Name` från SurveyHeaders
$surveyHeaderStmt = $conn->prepare("SELECT BestPerson, Name FROM SurveyHeaders WHERE AnswerId = ?");
$surveyHeaderStmt->bind_param("i", $answerId);
$surveyHeaderStmt->execute();
$surveyHeaderResult = $surveyHeaderStmt->get_result();
$surveyHeaderRow = $surveyHeaderResult->fetch_assoc();

$bestPerson = $surveyHeaderRow['BestPerson'] ?? "[vän]";
$name = $surveyHeaderRow['Name'] ?? "Okänd";

// Lägg till genitivformen (s) om det inte redan finns
if (substr($name, -1) !== 's') {
    $name .= 's';
}

$surveyHeaderStmt->close();

// Validera sorteringsparametrar
$validColumns = ['q.OrderNumber', 'r.ResponseId'];
$validDirections = ['ASC', 'DESC'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $validColumns) ? $_GET['sort'] : 'q.OrderNumber';
$sortDirection = isset($_GET['direction']) && in_array($_GET['direction'], $validDirections) ? $_GET['direction'] : 'ASC';

// Generera rubrik och tabell
echo "<h5 class='modal-title'>$name svar</h5>";

// Bygg SQL-frågan
$sql = "
    SELECT 
        q.OrderNumber, 
        q.QuestionText, 
        ao.AnswerText AS SelectedAnswer, 
        r.ResponseDescr, 
        GROUP_CONCAT(DISTINCT ao2.AnswerText SEPARATOR ', ') AS OtherAnswers
    FROM 
        SurveyHeaders AS h
    INNER JOIN SurveyAnswers AS a ON a.AnswerId = h.AnswerId
    INNER JOIN Questions AS q ON q.QuestionID = a.QuestionId
    INNER JOIN ResponseOptions AS r ON r.ResponseId = a.ResponseId
    LEFT OUTER JOIN AnswerOptions AS ao ON ao.QuestionID = a.QuestionId AND ao.ResponseID = a.ResponseId
    LEFT OUTER JOIN AnswerOptions AS ao2 ON ao2.QuestionID = a.QuestionId AND ao2.ResponseID <> a.ResponseId
    WHERE h.AnswerId = ?
    GROUP BY q.OrderNumber, q.QuestionText, ao.AnswerText, r.ResponseDescr
    ORDER BY $sortColumn $sortDirection;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $answerId);
$stmt->execute();
$result = $stmt->get_result();

// Generera HTML för modalens rubrik och svar

if ($result->num_rows > 0) {
    echo "<table class='table small-text-table styled-table'>";
    echo "<thead>
            <tr>
                <th><a href='#' class='sort-link' data-sort='q.OrderNumber' data-direction='" . ($sortColumn === 'q.OrderNumber' && $sortDirection === 'ASC' ? 'DESC' : 'ASC') . "'>#</a></th>
                <th>Fråga</th>
                <th>Svar</th>
                <th>Ej Svar</th>
                <th><a href='#' class='sort-link' data-sort='r.ResponseId' data-direction='" . ($sortColumn === 'r.ResponseId' && $sortDirection === 'ASC' ? 'DESC' : 'ASC') . "'>Preferens</a></th>
            </tr>
          </thead>";
    echo "<tbody>";
    while ($row = $result->fetch_assoc()) {
        // Ersätt `[vän]` med `$bestPerson` i relevanta fält
        $questionText = str_replace("[vän]", htmlspecialchars($bestPerson), $row['QuestionText']);
        $selectedAnswer = str_replace("[vän]", htmlspecialchars($bestPerson), $row['SelectedAnswer']);
        $otherAnswers = str_replace("[vän]", htmlspecialchars($bestPerson), $row['OtherAnswers']);

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['OrderNumber']) . "</td>";
        echo "<td>" . $questionText . "</td>";
        echo "<td>" . $selectedAnswer . "</td>";
        echo "<td>" . $otherAnswers . "</td>";
        echo "<td>" . htmlspecialchars($row['ResponseDescr']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "Inga svar hittades.";
}

$stmt->close();
$conn->close();
?>
