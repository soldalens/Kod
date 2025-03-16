<?php
require 'db_connect.php';

// Retrieve and sanitize POST data
$name       = isset($_POST['name']) ? trim($_POST['name']) : '';
$email      = isset($_POST['email']) ? trim($_POST['email']) : '';
$bestPerson = isset($_POST['best-person']) ? trim($_POST['best-person']) : '';
$gender     = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$age        = isset($_POST['age']) ? trim($_POST['age']) : '';
$education  = isset($_POST['education']) ? trim($_POST['education']) : '';
$inviteCode = isset($_POST['inviteCode']) ? trim($_POST['inviteCode']) : '';
$groupName  = isset($_POST['group']) ? trim($_POST['group']) : '';
$company    = isset($_POST['company']) ? trim($_POST['company']) : '';

// Determine if the respondent is representing a company (1) or is a private individual (0)
$isCompany  = (isset($_POST['orgType']) && $_POST['orgType'] === 'company') ? 1 : 0;

// First, delete any existing rows with the same InviteCode
$sqlDelete = "DELETE FROM HeaderTemp WHERE inviteCode = ?";
$stmtDelete = $conn->prepare($sqlDelete);
if (!$stmtDelete) {
    echo json_encode(array('success' => false, 'message' => $conn->error));
    exit;
}
$stmtDelete->bind_param("s", $inviteCode);
$stmtDelete->execute();
$stmtDelete->close();

// Now, prepare the INSERT statement using a prepared statement for security
$sql = "INSERT INTO HeaderTemp (Name, Email, BestPerson, Gender, Age, Education, inviteCode, GroupName, Company, isCompany) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Handle error in preparing the statement
    echo json_encode(array('success' => false, 'message' => $conn->error));
    exit;
}

// Bind parameters
$stmt->bind_param("ssssissssi", $name, $email, $bestPerson, $gender, $age, $education, $inviteCode, $groupName, $company, $isCompany);

// Execute and check for success
if ($stmt->execute()) {
    echo json_encode(array('success' => true));
} else {
    echo json_encode(array('success' => false, 'message' => $stmt->error));
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
