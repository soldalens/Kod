<?php
require 'db_connect.php';

// Bestäm språk - standard är svenska ('sv')
$language = isset($_POST['language']) ? $_POST['language'] : 'sv';

// Välj kolumnnamn beroende på valt språk för Info-tabeller
$infoTypeColumn = ($language == 'en') ? 'InfoTypeDescrENG' : 'InfoTypeDescr';
$infoTextColumn = ($language == 'en') ? 'InfoTextENG' : 'InfoText';

// Välj kolumnnamn beroende på valt språk för Diffs-tabellen
$diffTextColumn = ($language == 'en') ? 'TextENG' : 'Text';

// Välj vilken typ av data som skall visas - standard är "Beskrivningar"
// Här lägger vi till ett tredje alternativ "Långa Beskrivningar"
$dataType = isset($_POST['data_type']) ? $_POST['data_type'] : 'beskrivningar';

// Hämta personlighetstyper och deras ID
$personalityTypesResult = $conn->query("SELECT PeMaId, PeMaDescr FROM PeMaTypes ORDER BY PeMaId");
$personalityTypesArray = [];
while($row = $personalityTypesResult->fetch_assoc()){
    $personalityTypesArray[] = $row;
}

// Om en specifik personlighetstyp valts
$selectedPeMaId = isset($_POST['personality_type']) ? (int)$_POST['personality_type'] : null;
$infoData = [];
$infoTypes = [];
$diffData = [];
$pdfLink = "";
// Variabler för Långa Beskrivningar
$profileContent = null;
$profileBullets = [];

if ($selectedPeMaId) {
    if ($dataType == 'beskrivningar') {
        // Hämta InfoType-descriptions med rätt språk
        $infoTypesQuery = "SELECT InfoTypeId, $infoTypeColumn as InfoTypeDescr FROM InfoType ORDER BY InfoTypeId";
        $infoTypesResult = $conn->query($infoTypesQuery);
        while ($row = $infoTypesResult->fetch_assoc()) {
            $infoTypes[$row['InfoTypeId']] = $row['InfoTypeDescr'];
        }

        // Hämta Info-data för vald personlighetstyp med rätt språk
        $infoQuery = "SELECT InfoId, InfoTypeId, $infoTextColumn as InfoText FROM Info WHERE PeMaId = $selectedPeMaId ORDER BY InfoTypeId";
        $infoResult = $conn->query($infoQuery);
        while ($row = $infoResult->fetch_assoc()) {
            $infoData[$row['InfoTypeId']][] = $row;
        }
        // Hämta PDF-länken för vald personlighetstyp
        $linkQuery = "SELECT Link FROM PeMaTypes WHERE PeMaId = $selectedPeMaId";
        $linkResult = $conn->query($linkQuery);
        if ($linkRow = $linkResult->fetch_assoc()) {
            $pdfLink = $linkRow['Link'];
        }
    } elseif ($dataType == 'past') {
        // Hämta Diff-data för vald personlighetstyp med rätt språk, grupperat på DiffCode
        $diffsQuery = "SELECT DiffId, DiffCode, $diffTextColumn as DiffText FROM Diffs WHERE PeMaId = $selectedPeMaId ORDER BY DiffCode, CharTypeId";
        $diffsResult = $conn->query($diffsQuery);
        while ($row = $diffsResult->fetch_assoc()) {
            $diffData[$row['DiffCode']][] = $row;
        }
    } elseif ($dataType == 'langabeskrivningar') {
        // Hämta data för Långa Beskrivningar

        // Hämta ProfileContent (Tagline och ProfileDescription)
        $contentQuery = "SELECT ContentId, Tagline, ProfileDescription FROM ProfileContent WHERE PeMaId = $selectedPeMaId AND Language = '$language'";
        $contentResult = $conn->query($contentQuery);
        if ($contentResult && $contentResult->num_rows > 0) {
            $profileContent = $contentResult->fetch_assoc();
        }

        // Hämta ProfileBullets och koppla ihop med BulletCategories för att få rubriker
        $bulletsQuery = "SELECT pb.BulletId, pb.CategoryId, pb.BulletText, bc.Header_en, bc.Header_sv
            FROM ProfileBullets pb
            JOIN BulletCategories bc ON pb.CategoryId = bc.CategoryId
            WHERE pb.PeMaId = $selectedPeMaId AND pb.Language = '$language'
            ORDER BY pb.CategoryId, pb.BulletId";
        $bulletsResult = $conn->query($bulletsQuery);
        if ($bulletsResult) {
            while ($row = $bulletsResult->fetch_assoc()) {
                $profileBullets[$row['CategoryId']][] = $row;
            }
        }
    }
}

// Uppdatera data vid sparning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if ($dataType == 'beskrivningar' && isset($_POST['info'])) {
        foreach ($_POST['info'] as $infoId => $text) {
            $text = $conn->real_escape_string($text);
            $updateQuery = "UPDATE Info SET $infoTextColumn = '$text' WHERE InfoId = $infoId";
            $conn->query($updateQuery);
        }
        echo "<div class='alert alert-success'>Informationen har uppdaterats!</div>";

        // Ladda om Info-data efter uppdatering
        $infoData = [];
        $infoQuery = "SELECT InfoId, InfoTypeId, $infoTextColumn as InfoText FROM Info WHERE PeMaId = $selectedPeMaId ORDER BY InfoTypeId";
        $infoResult = $conn->query($infoQuery);
        while ($row = $infoResult->fetch_assoc()) {
            $infoData[$row['InfoTypeId']][] = $row;
        }
    } elseif ($dataType == 'past' && isset($_POST['diff'])) {
        foreach ($_POST['diff'] as $diffId => $text) {
            $text = $conn->real_escape_string($text);
            $updateQuery = "UPDATE Diffs SET $diffTextColumn = '$text' WHERE DiffId = $diffId";
            $conn->query($updateQuery);
        }
        echo "<div class='alert alert-success'>Informationen har uppdaterats!</div>";

        // Ladda om Diff-data efter uppdatering
        $diffData = [];
        $diffsQuery = "SELECT DiffId, DiffCode, $diffTextColumn as DiffText FROM Diffs WHERE PeMaId = $selectedPeMaId ORDER BY DiffCode, CharTypeId";
        $diffsResult = $conn->query($diffsQuery);
        while ($row = $diffsResult->fetch_assoc()) {
            $diffData[$row['DiffCode']][] = $row;
        }
    } elseif ($dataType == 'langabeskrivningar') {
        // Uppdatera ProfileContent (Tagline och ProfileDescription)
        $contentId = (int)$_POST['content_id'];
        $tagline = $conn->real_escape_string($_POST['tagline']);
        $profileDescription = $conn->real_escape_string($_POST['profile_description']);
        $updateContentQuery = "UPDATE ProfileContent SET Tagline = '$tagline', ProfileDescription = '$profileDescription' WHERE ContentId = $contentId";
        $conn->query($updateContentQuery);

        // Uppdatera ProfileBullets
        if (isset($_POST['bullets']) && is_array($_POST['bullets'])) {
            foreach ($_POST['bullets'] as $bulletId => $bulletText) {
                $bulletText = $conn->real_escape_string($bulletText);
                $updateBulletQuery = "UPDATE ProfileBullets SET BulletText = '$bulletText' WHERE BulletId = $bulletId";
                $conn->query($updateBulletQuery);
            }
        }
        echo "<div class='alert alert-success'>Långa beskrivningar har uppdaterats!</div>";

        // Ladda om ProfileContent och ProfileBullets efter uppdatering
        $contentResult = $conn->query($contentQuery);
        if ($contentResult && $contentResult->num_rows > 0) {
            $profileContent = $contentResult->fetch_assoc();
        }
        $profileBullets = [];
        $bulletsResult = $conn->query($bulletsQuery);
        if ($bulletsResult) {
            while ($row = $bulletsResult->fetch_assoc()) {
                $profileBullets[$row['CategoryId']][] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redigera Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #214A81;
            color: #F2F2F2;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #163A5F;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        }
        h1, h2, h5 {
            color: #F2F2F2;
        }
        .form-label {
            font-size: 16px;
            margin-top: 5px;
        }
        .btn-primary {
            background-color: #214A81;
            border-color: #214A81;
        }
        .btn-primary:hover {
            background-color: #163A5F;
        }
        textarea {
            background-color: #214A81;
            color: #F2F2F2;
            border: 1px solid #102A47;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
        }
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Redigera Information</h2>

        <!-- Formulär för att välja språk -->
        <form method="POST" class="mb-4 d-flex align-items-center">
            <label for="language" class="form-label me-2">Välj språk:</label>
            <select name="language" id="language" class="form-select me-2" onchange="this.form.submit()">
                <option value="sv" <?php echo $language == 'sv' ? 'selected' : ''; ?>>Svenska</option>
                <option value="en" <?php echo $language == 'en' ? 'selected' : ''; ?>>English</option>
            </select>
            <!-- Behåll valt data-typ och personlighetstyp -->
            <input type="hidden" name="data_type" value="<?php echo $dataType; ?>">
            <?php if ($selectedPeMaId): ?>
                <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
            <?php endif; ?>
        </form>

        <!-- Formulär för att välja data-typ -->
        <form method="POST" class="mb-4 d-flex align-items-center">
            <input type="hidden" name="language" value="<?php echo $language; ?>">
            <label for="data_type" class="form-label me-2">Välj typ:</label>
            <select name="data_type" id="data_type" class="form-select me-2" onchange="this.form.submit()">
                <option value="beskrivningar" <?php echo $dataType == 'beskrivningar' ? 'selected' : ''; ?>>Beskrivningar</option>
                <option value="past" <?php echo $dataType == 'past' ? 'selected' : ''; ?>>Påståenden</option>
                <option value="langabeskrivningar" <?php echo $dataType == 'langabeskrivningar' ? 'selected' : ''; ?>>Långa Beskrivningar</option>
            </select>
            <!-- Behåll valt personlighetstyp -->
            <?php if ($selectedPeMaId): ?>
                <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
            <?php endif; ?>
        </form>

        <!-- Formulär för att välja personlighetstyp -->
        <form method="POST" class="mb-4 d-flex align-items-center">
            <input type="hidden" name="language" value="<?php echo $language; ?>">
            <input type="hidden" name="data_type" value="<?php echo $dataType; ?>">
            <label for="personality_type" class="form-label me-2">Välj personlighetstyp:</label>
            <select name="personality_type" id="personality_type" class="form-select me-2" onchange="this.form.submit()">
                <option value="">-- Välj --</option>
                    <?php foreach ($personalityTypesArray as $row): ?>
                        <option value="<?php echo $row['PeMaId']; ?>" <?php echo $selectedPeMaId == $row['PeMaId'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['PeMaDescr']); ?>
                        </option>
                    <?php endforeach; ?>
            </select>
        </form>

        <!-- Visa och redigera information -->
        <?php if ($selectedPeMaId && (($dataType == 'beskrivningar' && $infoData) || ($dataType == 'past' && $diffData))): ?>
            <form method="POST">
                <input type="hidden" name="language" value="<?php echo $language; ?>">
                <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
                <input type="hidden" name="data_type" value="<?php echo $dataType; ?>">
                <!-- Ny PDF-knapp för "beskrivningar" med phpWord_short.php -->
                    <?php
                    if ($selectedPeMaId) {
                        $selectedPeMaDescr = "";
                        foreach ($personalityTypesArray as $pt) {
                            if ($pt['PeMaId'] == $selectedPeMaId) {
                                $selectedPeMaDescr = $pt['PeMaDescr'];
                                break;
                            }
                        }
                        $languageCodeParam = ($language == 'en') ? 'en' : 'sv';
                        $generatedLink = "https://proanalys.se/phpWord_short_standard.php?ProfileCode=" . urlencode($selectedPeMaDescr) . "&Language=" . urlencode($languageCodeParam);
                    ?>
                        <a href="<?php echo htmlspecialchars($generatedLink); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                            Öppna PDF
                        </a>
                    <?php
                    }
                    ?>
                <?php if ($dataType == 'beskrivningar'): ?>
                    <?php foreach ($infoData as $infoTypeId => $rows): ?>
                        <h5 class="form-label"><?php echo htmlspecialchars($infoTypes[$infoTypeId] ?? "Okänd typ"); ?></h5>
                        <div class="row">
                            <?php foreach ($rows as $row): ?>
                                <div class="col-md-6">
                                    <textarea name="info[<?php echo $row['InfoId']; ?>]" rows="3" class="form-control"><?php echo htmlspecialchars($row['InfoText']); ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($dataType == 'past'): ?>
                    <?php foreach ($diffData as $diffCode => $rows): ?>
                        <h5 class="form-label"><?php echo htmlspecialchars($diffCode); ?></h5>
                        <div class="row">
                            <?php foreach ($rows as $row): ?>
                                <div class="col-md-6">
                                    <textarea name="diff[<?php echo $row['DiffId']; ?>]" rows="3" class="form-control"><?php echo htmlspecialchars($row['DiffText']); ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <br>
                <button type="submit" name="save" class="btn btn-primary">Spara</button>
            </form>
        <?php elseif ($selectedPeMaId && $dataType == 'langabeskrivningar'): ?>
<!-- Formulär för Långa Beskrivningar -->
<form method="POST">
    <input type="hidden" name="language" value="<?php echo $language; ?>">
    <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
    <input type="hidden" name="data_type" value="<?php echo $dataType; ?>">
    <?php if ($profileContent): ?>
        <input type="hidden" name="content_id" value="<?php echo $profileContent['ContentId']; ?>">
    <?php endif; ?>

    <?php
    // Om en personlighetstyp är vald, hämta motsvarande PeMaDescr från arrayen
    if ($selectedPeMaId) {
        $selectedPeMaDescr = "";
        foreach ($personalityTypesArray as $pt) {
            if ($pt['PeMaId'] == $selectedPeMaId) {
                $selectedPeMaDescr = $pt['PeMaDescr'];
                break;
            }
        }
        // Bestäm språk-koden: 'sv' om svenska, annars 'en'
        $languageCodeParam = ($language == 'en') ? 'en' : 'sv';
        // Skapa länken enligt den angivna syntaxen
        $generatedLink = "https://proanalys.se/phpWord.php?ProfileCode=" . urlencode($selectedPeMaDescr) . "&Language=" . urlencode($languageCodeParam);
    ?>
        <a href="<?php echo htmlspecialchars($generatedLink); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
            Öppna PDF
        </a>
    <?php
    }
    ?>

    <div class="mb-3">
        <label for="tagline" class="form-label">Tagline</label>
        <input type="text" name="tagline" id="tagline" class="form-control" value="<?php echo $profileContent ? htmlspecialchars($profileContent['Tagline']) : ''; ?>">
    </div>
    <div class="mb-3">
        <label for="profile_description" class="form-label">Profile Description</label>
        <textarea name="profile_description" id="profile_description" rows="5" class="form-control"><?php echo $profileContent ? htmlspecialchars($profileContent['ProfileDescription']) : ''; ?></textarea>
    </div>
    <?php if (!empty($profileBullets)): ?>
        <?php foreach ($profileBullets as $categoryId => $bullets): ?>
            <?php 
                // Välj rubrik baserat på språk
                $header = ($language == 'en') ? $bullets[0]['Header_en'] : $bullets[0]['Header_sv'];
            ?>
            <h5 class="form-label"><?php echo htmlspecialchars($header); ?></h5>
            <div class="row mb-3">
                <?php foreach ($bullets as $bullet): ?>
                    <div class="col-md-6">
                        <textarea name="bullets[<?php echo $bullet['BulletId']; ?>]" rows="2" class="form-control"><?php echo htmlspecialchars($bullet['BulletText']); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <button type="submit" name="save" class="btn btn-primary">Spara</button>
</form>

        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
