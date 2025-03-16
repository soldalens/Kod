<?php

require 'db_connect.php';

// Bygg WHERE-sats baserat på aktiva filter
$whereClauses = [];

// Funktion för att säkerställa att varje värde i arrayen är säkert
function escapeArray($conn, $array) {
    return array_map(function ($item) use ($conn) {
        return $conn->real_escape_string($item);
    }, $array);
}

if (!empty($_GET['personalityType'])) {
    $types = implode("','", escapeArray($conn, $_GET['personalityType']));
    $whereClauses[] = "PersonalityType IN ('$types')";
}
if (!empty($_GET['name'])) {
    $names = implode("','", escapeArray($conn, $_GET['name']));
    $whereClauses[] = "Name IN ('$names')";
}
if (!empty($_GET['education'])) {
    $educations = implode("','", escapeArray($conn, $_GET['education']));
    $whereClauses[] = "Education IN ('$educations')";
}
if (!empty($_GET['group'])) {
    $groups = implode("','", escapeArray($conn, $_GET['group']));
    $whereClauses[] = "GroupName IN ('$groups')";
}
if (!empty($_GET['gender'])) {
    $genders = implode("','", escapeArray($conn, $_GET['gender']));
    $whereClauses[] = "Gender IN ('$genders')";
}
if (!empty($_GET['age'])) {
    $ages = implode("','", escapeArray($conn, $_GET['age']));
    $whereClauses[] = "Age IN ('$ages')";
}
if (!empty($_GET['orderNumber'])) {
    $orderNumbers = implode("','", escapeArray($conn, $_GET['orderNumber']));
    $whereClauses[] = "OrderNumber IN ('$orderNumbers')";
}
if (!empty($_GET['responseDescr'])) {
    $responseDescrs = implode("','", escapeArray($conn, $_GET['responseDescr']));
    $whereClauses[] = "ResponseDescr IN ('$responseDescrs')";
}

$where = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Funktion för att hämta alternativ
function getOptions($conn, $column, $where) {
    $result = $conn->query("SELECT DISTINCT $column AS value FROM QuestionTimeAnalysis $where ORDER BY $column ASC");
    $options = "";
    while ($row = $result->fetch_assoc()) {
        $options .= "<option value='" . htmlspecialchars($row['value']) . "'>" . htmlspecialchars($row['value']) . "</option>";
    }
    return $options;
}

// Generera dropdown-data
echo json_encode([
    "personalityType" => getOptions($conn, "PersonalityType", $where),
    "name" => getOptions($conn, "Name", $where),
    "education" => getOptions($conn, "Education", $where),
    "group" => getOptions($conn, "GroupName", $where),
    "gender" => getOptions($conn, "Gender", $where),
    "age" => getOptions($conn, "Age", $where),
    "orderNumber" => getOptions($conn, "OrderNumber", $where),
    "responseDescr" => getOptions($conn, "ResponseDescr", $where)
]);

$conn->close();
?>
