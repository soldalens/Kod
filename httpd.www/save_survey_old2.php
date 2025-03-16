<?php
// Aktivera felrapportering

require 'db_connect.php';

// Hämta den aktuella VersionId från Settings
$versionIdQuery = "SELECT ActiveVersionId FROM Settings LIMIT 1";
$versionIdResult = $conn->query($versionIdQuery);

if ($versionIdResult->num_rows > 0) {
    $versionIdRow = $versionIdResult->fetch_assoc();
    $activeVersionId = intval($versionIdRow['ActiveVersionId']);
} else {
    die("ActiveVersionId saknas i Settings-tabellen.");
}

// Hämta antalet aktiva frågor för den aktuella versionen
$result = $conn->query("SELECT COUNT(*) AS totalQuestions FROM Questions WHERE isActive = 1 AND VersionId = $activeVersionId");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalQuestions = $row['totalQuestions'];
} else {
    die("Kunde inte hämta antalet frågor för den aktuella versionen.");
}

$questionIds = [];
$result = $conn->query("SELECT QuestionID FROM Questions WHERE VersionId = $activeVersionId AND isActive = 1 ORDER BY QuestionID");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $questionIds[] = $row['QuestionID'];
    }
}

// Lägg till de dynamiska fälten baserat på QuestionId
$required_fields = ['name', 'email', 'best-person', 'gender', 'age', 'education', 'inviteCode', 'group'];
foreach ($questionIds as $questionId) {
    $required_fields[] = "q" . $questionId;
}

// Kolla om något fält saknas
$missingFields = [];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missingFields[] = $field;
    }
}

// Om något saknas, visa felmeddelande
if (!empty($missingFields)) {
    die("All fields are required. Missing: " . implode(', ', $missingFields));
}

// Hämta data från formuläret
$name = $_POST['name'];
$email = $_POST['email'];
$best_person = $_POST['best-person'];
$gender = $_POST['gender'];
$age = $_POST['age'];
$education = $_POST['education'];
$InviteCode = $_POST['inviteCode'];
$groupName = $_POST['group'];
$company = isset($_POST['company']) ? $_POST['company'] : 'N/A';
$responses = [];
foreach ($questionIds as $questionId) {
    $fieldKey = "q" . $questionId; // Exempel: q1, q2, q3, ...
    if (isset($_POST[$fieldKey]) && !empty($_POST[$fieldKey])) {
        $responses[$questionId] = intval($_POST[$fieldKey]); // Lägg till i responses med QuestionId som nyckel
    } else {
        die("Saknat svar för fråga med ID: " . $questionId);
    }
}

// Generera aktuellt datum och tid
$stockholmTime = new DateTime("now", new DateTimeZone("Europe/Stockholm"));
$submissionTime = $stockholmTime->format('Y-m-d H:i:s');

// Starta en transaktion
$conn->begin_transaction();

try {
    // Spara enkäthuvudet med VersionId
    $stmt = $conn->prepare("INSERT INTO SurveyHeaders (Name, Email, BestPerson, Gender, Age, Education, inviteCode, GroupName, Company, SubmissionTime, VersionId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisssssi", $name, $email, $best_person, $gender, $age, $education, $InviteCode, $groupName, $company, $submissionTime, $activeVersionId);
    $stmt->execute();
    $answerId = $stmt->insert_id; // Hämta det nyligen skapade AnswerId
    $stmt->close();

    // Spara varje svar i SurveyAnswers
    $stmt = $conn->prepare("INSERT INTO SurveyAnswers (AnswerId, QuestionId, ResponseId) VALUES (?, ?, ?)");
    foreach ($responses as $questionId => $responseId) {
        $stmt->bind_param("iii", $answerId, $questionId, $responseId);
        $stmt->execute();
    }
    $stmt->close();

    // Uppdatera 'Used' i SurveyInvites till 1
    $stmt = $conn->prepare("UPDATE SurveyInvites SET Used = 1 WHERE Email = ? AND InviteCode = ?");
    $stmt->bind_param("ss", $email, $InviteCode);
    $stmt->execute();
    $stmt->close();

    // Utför transaktionen
    $conn->commit();

    header("Location: feedback.php?status=success&message=Survey+submitted+successfully");
} catch (Exception $e) {
    // Om något går fel, avbryt transaktionen
    $conn->rollback();
    die("Error saving survey: " . $e->getMessage());
}

// Stäng anslutningen
$conn->close();
header("Location: https://proanalys.se/results.php?AnswerId=$answerId");
exit();
?>
