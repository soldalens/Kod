<?php
require 'db_connect.php';

// Bestäm språk - standard är svenska ('sv')
$language = isset($_POST['language']) ? $_POST['language'] : 'sv';

// Välj kolumnnamn beroende på valt språk
$infoTypeColumn = ($language == 'en') ? 'InfoTypeDescrENG' : 'InfoTypeDescr';
$infoTextColumn = ($language == 'en') ? 'InfoTextENG' : 'InfoText';

// Hämta personlighetstyper och deras ID
$personalityTypes = $conn->query("SELECT PeMaId, PeMaDescr FROM PeMaTypes ORDER BY PeMaId");

// Om en specifik personlighetstyp valts
$selectedPeMaId = isset($_POST['personality_type']) ? (int)$_POST['personality_type'] : null;
$infoData = [];
$infoTypes = [];
$pdfLink = "";

if ($selectedPeMaId) {
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
}

// Uppdatera Info-data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    foreach ($_POST['info'] as $infoId => $text) {
        $text = $conn->real_escape_string($text);
        $updateQuery = "UPDATE Info SET $infoTextColumn = '$text' WHERE InfoId = $infoId";
        $conn->query($updateQuery);
    }
    echo "<div class='alert alert-success'>Informationen har uppdaterats!</div>";

    // Ladda om data efter uppdatering
    if ($selectedPeMaId) {
        $infoData = [];
        $infoQuery = "SELECT InfoId, InfoTypeId, $infoTextColumn as InfoText FROM Info WHERE PeMaId = $selectedPeMaId ORDER BY InfoTypeId";
        $infoResult = $conn->query($infoQuery);
        while ($row = $infoResult->fetch_assoc()) {
            $infoData[$row['InfoTypeId']][] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redigera Info</title>
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
            <!-- Behåll valt personlighetstyp om det redan är valt -->
            <?php if ($selectedPeMaId): ?>
                <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
            <?php endif; ?>
        </form>

        <!-- Formulär för att välja personlighetstyp -->
        <form method="POST" class="mb-4 d-flex align-items-center">
            <input type="hidden" name="language" value="<?php echo $language; ?>">
            <label for="personality_type" class="form-label me-2">Välj personlighetstyp:</label>
            <select name="personality_type" id="personality_type" class="form-select me-2" onchange="this.form.submit()">
                <option value="">-- Välj --</option>
                <?php while ($row = $personalityTypes->fetch_assoc()): ?>
                    <option value="<?php echo $row['PeMaId']; ?>" <?php echo $selectedPeMaId == $row['PeMaId'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['PeMaDescr']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <!-- Visa och redigera information -->
        <?php if ($selectedPeMaId && $infoData): ?>
            <form method="POST">
                <input type="hidden" name="language" value="<?php echo $language; ?>">
                <input type="hidden" name="personality_type" value="<?php echo $selectedPeMaId; ?>">
                <?php if ($selectedPeMaId && $pdfLink): ?>
                    <a href="<?php echo htmlspecialchars($pdfLink); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                        Öppna PDF
                    </a>
                <?php endif; ?>
                <button type="submit" name="save" class="btn btn-primary">Spara</button>
            
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
