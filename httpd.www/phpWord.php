<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering

ini_set('memory_limit', '1024M');
set_time_limit(300);

// CloudConvert API Key
$apiKey = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiOTVmZWMwNWY0MjNkZWQ4YzJkOWE5NmE5OWVlMzAwNzUxYThkYzkzZjEzMmYzZmI0NGZjMGIzYjhhNWEyMGRhODdlOGI3ODZiZGFiNzk4MGMiLCJpYXQiOjE3NDExODI0MjguNTc5OTgzLCJuYmYiOjE3NDExODI0MjguNTc5OTg1LCJleHAiOjQ4OTY4NTYwMjguNTc1Nzg4LCJzdWIiOiI3MTI0MTg2NyIsInNjb3BlcyI6WyJ0YXNrLnJlYWQiLCJ0YXNrLndyaXRlIl19.HRAcJDNyd8LgaoOVwr1yyTFMDnQAQXYMbsjfLF9Nfs-XsA16VS9QiaW9NrElih8B6-T68LPNKOemF4RGA1Je4r-dK3yO8RBgtbigW2k-XXbyFCG5H-2sxJZg5XlgVXb6HAzu0L8QMNJioEcBoN_nuBiBlnZ68SSFGy5nBRM4ZfwP2n84BL8PCGbMB3bTF121YEyzSlOMZmXexotOkL1dtP8EnGUwAOhenLOeaMJ_9-FAVDfUea3pxiqUETHoeu98k6J5Z83pbCMf9pleq2yaCh4g_DEqamcIE0jicuOm0dadIsRWZkcpXReFxYJUK5xjXEr-xjYlD_fsDDdTt4itpfiP-4VWcuPjUgIQC4eYJp4vTGCPFh-wiRaYTFf2QTsd-i4Uj3S4EHmgshiisBLb7NZ04oic3wlMAVbF3E23YAt0T2obmEGFd8tQu9VRDNlMEspOVTP9l1UoJj4xkax6fK3BOaZyoHoUuEYp_DJF9Q4oxX2n3w8c9KQxJwR2pjYxfMZdWbv9CxsI_pcAiWilCLA8wzrtS8rfph_heiXS5ipNs7enBzs8Dej4TTccxBkx_Xa1rnNJX66r6xhIZ2SM7U7le3xLAxdaOgKeDEi52azVnh47KHDslyXNln4zw2Y6nLZHk4g4XZDKMqEYROXTDCwrPqZ8jOws9jAJYzZ5J20"; // Replace with your actual API Key

// Load PHPWord's autoloader
require_once 'vendor/PHPWord/src/PhpWord/Autoloader.php';
\PhpOffice\PhpWord\Autoloader::register();

// Include database connection
require_once 'db_connect.php';

// Get parameters from URL
$profileCode = isset($_GET['ProfileCode']) ? $_GET['ProfileCode'] : 'ENFJ';
$language    = isset($_GET['Language']) ? $_GET['Language'] : 'sv';
$date    = isset($_GET['Date']) ? $_GET['Date'] : date('Y-m-d');
$name    = isset($_GET['Name']) ? $_GET['Name'] : '';

function sanitizeFileName($string) {
    // Convert special characters to ASCII equivalents
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    
    // Replace any remaining non-alphanumeric characters (except underscores) with an underscore
    $string = preg_replace('/[^A-Za-z0-9_]/', '_', $string);

    // Remove multiple consecutive underscores
    $string = preg_replace('/_+/', '_', $string);

    // Trim leading and trailing underscores
    return trim($string, '_');
}

// Sanitize the name variable
$sanitizedName = sanitizeFileName($name);

// 💡 KONTROLLERA OM PDF-FILEN REDAN FINNS
$pdfFilePath = "onewebmedia/Pdf/Personliga/" . strtolower($profileCode) . "_" . strtolower($language) . "_" . strtolower(str_replace(' ', '_', $sanitizedName)) . ".pdf";
$pdfFullUrl = "https://proanalys.se/" . $pdfFilePath;

$jsAlerts = []; // Array för att samla meddelanden

// Kontrollera om filen finns lokalt på servern
if (file_exists($pdfFilePath)) {
 

    // Kontrollera om filen är åtkomlig via webbläsare
    $headers = @get_headers($pdfFullUrl);
    if ($headers && strpos($headers[0], '200') !== false) {


        // Omdirigera till befintlig PDF och avsluta scriptet
        echo "<script>

            window.location.href = '$pdfFullUrl';
        </script>";
        exit();
    } else {

    }
} else {

}

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Retrieve profile information from PeMaTypes
$sql = "SELECT PeMaId, PeMaDescr, ImageLink, LinkSV, LinkEN FROM PeMaTypes WHERE PeMaDescr = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $profileCode);
$stmt->execute();
$result = $stmt->get_result();
$peMaTypes = $result->fetch_assoc();
if (!$peMaTypes) {
    die("Profile not found.");
}
$peMaId    = $peMaTypes['PeMaId'];
$imageLink = $peMaTypes['ImageLink'];
$pdfLink   = ($language === 'sv') ? $peMaTypes['LinkSV'] : $peMaTypes['LinkEN'];

$pdfLink = "https://proanalys.se/onewebmedia/Pdf/Personliga/" . strtolower($profileCode) . "_" . strtolower($language) . "_" . strtolower(str_replace(' ', '_', $sanitizedName)) . ".pdf";

if (!$pdfLink) {
    die("No PDF link found for this profile and language.");
}

// Retrieve profile content
$sql = "SELECT Tagline, ProfileDescription, Energy, Information, Decisions, Lifestyle 
        FROM ProfileContent 
        WHERE PeMaId = ? AND Language = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $peMaId, $language);
$stmt->execute();
$result = $stmt->get_result();
$profileContent = $result->fetch_assoc();
if (!$profileContent) {
    die("Profile content not found.");
}

// Determine the template file
$templateFile = 'onewebmedia/WordFile/Templates/Template_' . $language . '.docx';
if (!file_exists($templateFile)) {
    die("Template file not found: $templateFile");
}

// Load Word template
$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templateFile);

function forceBlueColor($wordML) {
    // Remove any existing <w:color .../> tag (if any)
    $wordML = preg_replace('/<w:color[^>]*\/>/', '', $wordML);
    // Insert the blue color tag immediately after every <w:rPr> opening tag
    $wordML = preg_replace('/(<w:rPr(?:\s[^>]+)?>)/', '$1<w:color w:val="214A81"/>', $wordML);
    return $wordML;
}

// Set placeholders
$templateProcessor->setValue('ProfileType', $profileCode);
$templateProcessor->setValue('Tagline', $profileContent['Tagline']);
$templateProcessor->setValue('ProfileDescription', $profileContent['ProfileDescription']);
$templateProcessor->setValue('Energy', forceBlueColor($profileContent['Energy']), true);
$templateProcessor->setValue('Information', forceBlueColor($profileContent['Information']), true);
$templateProcessor->setValue('Decisions', forceBlueColor($profileContent['Decisions']), true);
$templateProcessor->setValue('Lifestyle', forceBlueColor($profileContent['Lifestyle']), true);
$templateProcessor->setValue('Date', $date);
$templateProcessor->setValue('Name', $name);

// Insert profile image
$templateProcessor->setImageValue('ProfileImage', [
    'path'   => $imageLink,
    'width'  => 298,
    'height' => 298
]);

// Define bullet categories mapping
$bulletCategories = [
    1  => ['block' => 'STYRKOR_BULLETS',    'placeholder' => 'styrkor_BulletText'],
    2  => ['block' => 'DRIV_BULLETS',       'placeholder' => 'driv_BulletText'],
    3  => ['block' => 'KOM_BULLETS',        'placeholder' => 'kom_BulletText'],
    4  => ['block' => 'RELATION_BULLETS',   'placeholder' => 'relation_BulletText'],
    5  => ['block' => 'ARBETE_BULLETS',     'placeholder' => 'arbete_BulletText'],
    6  => ['block' => 'ROLL_BULLETS',       'placeholder' => 'roll_BulletText'],
    7  => ['block' => 'LEDARE_BULLETS',     'placeholder' => 'ledare_BulletText'],
    8  => ['block' => 'UPPSKATTAR_BULLETS', 'placeholder' => 'uppskattar_BulletText'],
    9  => ['block' => 'UTVECKLING_BULLETS', 'placeholder' => 'utveckling_BulletText'],
    10 => ['block' => 'TANKA_BULLETS',      'placeholder' => 'tanka_BulletText']
];

// Fetch bullet points
$sql = "SELECT BulletText 
        FROM ProfileBullets 
        WHERE PeMaId = ? AND CategoryId = ? AND Language = ? 
        ORDER BY BulletId ASC";
$stmtBullets = $conn->prepare($sql);

foreach ($bulletCategories as $catId => $info) {
    $stmtBullets->bind_param("iis", $peMaId, $catId, $language);
    $stmtBullets->execute();
    $resultBullets = $stmtBullets->get_result();

    $bullets = [];
    while ($row = $resultBullets->fetch_assoc()) {
        $bullets[] = $row['BulletText'];
    }

    if (count($bullets) > 0) {
        $templateProcessor->cloneBlock($info['block'], count($bullets), true, true);
        $i = 1;
        foreach ($bullets as $bullet) {
            $templateProcessor->setValue($info['placeholder'] . "#{$i}", $bullet);
            $i++;
        }
    } else {
        $templateProcessor->deleteBlock($info['block']);
    }
}

// Save the latest Word file
$docxFile = 'onewebmedia/WordFile/Personliga/' . $profileCode . '_' . $language . '_' . str_replace(' ', '_', $sanitizedName) . '.docx';
$templateProcessor->saveAs($docxFile);

// Ensure the latest file is being used
clearstatcache(true, $docxFile);
if (!file_exists($docxFile)) {
    die("ERROR: Word file was not properly saved.");
}

// Force CloudConvert to use the fresh file by appending a timestamp
$cacheBuster = time();
$cloudconvertDocxUrl = "https://proanalys.se/onewebmedia/WordFile/Personliga/" . rawurlencode($profileCode . "_" . $language . "_" . str_replace(' ', '_', $sanitizedName) . ".docx");

// Convert to PDF using CloudConvert
$payload = [
    "tasks" => [
        "import" => [
            "operation" => "import/url",
            "url" => $cloudconvertDocxUrl
        ],
        "convert" => [
            "operation" => "convert",
            "input" => "import",
            "output_format" => "pdf"
        ],
        "export" => [
            "operation" => "export/url",
            "input" => "convert"
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.cloudconvert.com/v2/jobs");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$jobId = $data["data"]["id"] ?? null;

if (!$jobId) {
    die("CloudConvert job creation failed.");
}

// Poll for job completion
sleep(3);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.cloudconvert.com/v2/jobs/" . $jobId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$pdfUrl = null;
foreach ($data["data"]["tasks"] as $task) {
    if ($task["name"] === "export" && isset($task["result"]["files"][0]["url"])) {
        $pdfUrl = $task["result"]["files"][0]["url"];
        break;
    }
}

if (!$pdfUrl) {
    die("Failed to retrieve the PDF file.");
}

// Ensure the correct PDF is saved and replaces the old file
$pdfFilePath = str_replace('https://proanalys.se/', '', $pdfLink);
if (file_exists($pdfFilePath)) {
    unlink($pdfFilePath); // Delete old file
}
file_put_contents($pdfFilePath, file_get_contents($pdfUrl), LOCK_EX);
clearstatcache(true, $pdfFilePath);

// Redirect to the fresh PDF
$pdfLink .= '?t=' . time(); // Prevent browser caching
header("Location: $pdfLink");
exit();

ob_end_flush();
