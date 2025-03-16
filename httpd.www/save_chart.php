<?php

// Receive JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['AnswerId'], $data['imageData'])) {
    die('Invalid data.');
}

$answerId = intval($data['AnswerId']);
$imageData = $data['imageData'];

// Remove base64 prefix from data URL
$imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
$imageData = base64_decode($imageData);

if ($imageData === false) {
    die('Base64 decode failed.');
}

// Define image save path (ensure the folder exists and is writable)
$savePath = __DIR__ . '/onewebmedia/Graphs/' . $answerId . '.png';

// Save the image
if (file_put_contents($savePath, $imageData)) {
    echo "Image successfully saved as {$answerId}.png";
} else {
    echo "Failed to save image.";
}

?>
