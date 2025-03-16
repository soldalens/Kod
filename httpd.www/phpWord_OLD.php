<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering

ini_set('memory_limit', '1024M');
set_time_limit(300);

// Hämta PHPWord:s autoloader
require_once 'vendor/PHPWord/src/PhpWord/Autoloader.php';
\PhpOffice\PhpWord\Autoloader::register();

// Inkludera din databasanslutning (mysqli, sparad i $conn)
require_once 'db_connect.php';

// Hämta parametrar från URL
$profileCode = isset($_GET['ProfileCode']) ? $_GET['ProfileCode'] : 'ENFJ';
$language    = isset($_GET['Language']) ? $_GET['Language'] : 'sv';

// Debug: Kontrollera anslutningen
if (!$conn) {
    die("Database connection failed.");
}

// Hämta profilinformation från PeMaTypes med mysqli
$sql = "SELECT PeMaId, PeMaDescr, ImageLink FROM PeMaTypes WHERE PeMaDescr = ?";
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
echo "Profile found: PeMaId = $peMaId, ImageLink = $imageLink<br>";

// Hämta språkberoende profilinnehåll från ProfileContent
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
echo "Profile content loaded.<br>";

// Bestäm mallfilens sökväg baserat på språket
$templateFile = 'onewebmedia/WordFile/Templates/Template_' . $language . '.docx';
if (!file_exists($templateFile)) {
    die("Template file not found: $templateFile");
}

// Ladda Word-mallen
$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templateFile);

// Sätt grundläggande platshållare (använd taggarna utan extra klammerparenteser)
// I din mall ska platshållarna vara i formen: ${ProfileType}, ${TagLine} osv.
$templateProcessor->setValue('ProfileType', $profileCode);
$templateProcessor->setValue('TagLine', $profileContent['Tagline']);
$templateProcessor->setValue('ProfileDescription', $profileContent['ProfileDescription']);
$templateProcessor->setValue('Energy', $profileContent['Energy'], true);
$templateProcessor->setValue('Information', $profileContent['Information'], true);
$templateProcessor->setValue('Decisions', $profileContent['Decisions'], true);
$templateProcessor->setValue('Lifestyle', $profileContent['Lifestyle'], true);

// Sätt in profilbilden med hjälp av fältet ImageLink (mallens bildplats ska vara ${ProfileImage})
$templateProcessor->setImageValue('ProfileImage', [
    'path'   => $imageLink,
    'width'  => 298,
    'height' => 298
]);

// Definiera mapping för bulletkategorier (CategoryId => blocknamn och platshållartext)
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

// Förbered statement för att hämta bulletpunkter
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
    $count = count($bullets);
    echo "Category $catId: $count bullet(s) found.<br>";

    if ($count > 0) {
        $templateProcessor->cloneBlock($info['block'], $count, true, true);
        $i = 1;
        foreach ($bullets as $bullet) {
            $templateProcessor->setValue($info['placeholder'] . "#{$i}", $bullet);
            $i++;
        }
    } else {
        $templateProcessor->deleteBlock($info['block']);
    }
}

// Spara den genererade Word-filen i mappen onewebmedia/WordFile med formatet ProfileCode_Language.docx
$docxFile = 'onewebmedia/WordFile/' . $profileCode . '_' . $language . '.docx';
$templateProcessor->saveAs($docxFile);
echo "DOCX sparad som $docxFile.<br>";

// Se till att filen är publik på din server, t.ex. https://proanalys.se/...
$publicUrl = 'https://proanalys.se/' . $docxFile;

// Lägg till en parameter för att undvika cache
$cacheBuster = time();
$publicUrlWithCacheBuster = $publicUrl . '?t=' . $cacheBuster;

// URL-enkoda länken
$encodedUrl = urlencode($publicUrlWithCacheBuster);

// Skapa Office Online-länken
$viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . $encodedUrl;

// Omdirigera till Office Online Viewer
header("Location: $viewerUrl");
exit();

ob_end_flush();
?>
