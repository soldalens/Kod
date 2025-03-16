<?php

// Database connection credentials
$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all AnswerIds from the database
    $query = "SELECT AnswerId FROM SurveyHeaders WHERE VersionId = 2";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $answerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Loop through each AnswerId and send a GET request
    foreach ($answerIds as $answerId) {
        // Construct the URL with the AnswerId as a query parameter
        $url = "https://proanalys.se/results.php?AnswerId=$answerId";

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the GET request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            echo "cURL error for AnswerId $answerId: " . curl_error($ch) . "\n";
        } else {
            echo "Response for AnswerId $answerId: $response\n";
        }

        // Close cURL session
        curl_close($ch);
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
}
