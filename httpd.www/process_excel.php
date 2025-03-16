<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'vendor/autoload.php'; // PhpSpreadsheet krävs här
use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    $fileTmpPath = $_FILES['excelFile']['tmp_name'];
    $fileType = $_FILES['excelFile']['type'];

    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        $users = [];
        foreach ($data as $row) {
            // Antag att namn är i kolumn 1 och e-post i kolumn 2
            $name = $row[0];
            $email = $row[1];

            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $users[] = ['name' => $name, 'email' => $email];
            }
        }

        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ingen fil uppladdad.']);
}
?>
