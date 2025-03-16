<?php
include 'db_connect.php';

// Hämta ProfileCode och AnswerId från URL-parametrar
$profileCode = $_GET['ProfileCode'] ?? '';
$answerId = $_GET['AnswerId'] ?? '';

// Räkna antalet osäkra dimensioner (?)
$numUncertain = substr_count($profileCode, '?');

// Bestäm sökmönster beroende på antal osäkra dimensioner
if ($numUncertain === 1) {
    // Exempel: ESF?
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode); // ESF? → ESF%
} elseif ($numUncertain === 2) {
    // Exempel: E?F?
    $query = "SELECT PeMaId FROM PeMaTypes WHERE PeMaDescr LIKE ?";
    $stmt = $conn->prepare($query);
    $searchPattern = str_replace("?", "%", $profileCode); // E?F? → E%F%
} else {
    die("Ogiltigt ProfileCode.");
}

// Hämta relevanta PeMaId
$stmt->bind_param("s", $searchPattern);
$stmt->execute();
$result = $stmt->get_result();
$peMaIds = [];
while ($row = $result->fetch_assoc()) {
    $peMaIds[] = $row['PeMaId'];
}

// Kontrollera att vi har rätt antal profiler
if (count($peMaIds) !== (2 * $numUncertain)) {
    die("Fel vid hämtning av profiler.");
}

// Välj 5 slumpmässiga kategorier (StatementCategoryId)
$randomCategoriesQuery = "
    SELECT DISTINCT StatementCategoryId
    FROM Statements
    ORDER BY RAND()
    LIMIT 4
";
$randomCategoriesResult = $conn->query($randomCategoriesQuery);
$categoryIds = [];
while ($row = $randomCategoriesResult->fetch_assoc()) {
    $categoryIds[] = $row['StatementCategoryId'];
}
$categoryPlaceholders = implode(',', array_map('intval', $categoryIds));

// Hämta 5 påståenden per profil och kategori
$placeholders = implode(',', array_map('intval', $peMaIds));
$sql = "
    SELECT s.PeMaId, s.CombStatementText, s.StatementCategoryId
    FROM Statements s
    INNER JOIN (
        SELECT PeMaId, StatementCategoryId, MIN(StatementId) AS min_statementId
        FROM Statements
        WHERE PeMaId IN ($placeholders) 
        AND StatementCategoryId IN ($categoryPlaceholders)
        AND CombStatementText IS NOT NULL
        GROUP BY PeMaId, StatementCategoryId
    ) AS t 
    ON s.PeMaId = t.PeMaId 
    AND s.StatementCategoryId = t.StatementCategoryId
    AND s.StatementId = t.min_statementId
    -- ORDER BY s.StatementCategoryId, s.PeMaId;
";
$result = $conn->query($sql);

// Organisera påståenden i kluster
$clusters = [];
while ($row = $result->fetch_assoc()) {
    $clusters[$row['PeMaId']][$row['StatementCategoryId']][] = $row['CombStatementText'];
}

$conn->close();
?>
<?php
// Exempel: Se till att dessa variabler är definierade innan (hämtade från tidigare kod)
// $numUncertain = antal osäkra dimensioner (1 eller 2);
// $clusters = en associativ array med PeMaId som nyckel och varje värde är en array med kategorier (varje kategori innehåller en array med påståenden);
// $profileCode, $answerId = dolda fält att skicka vidare.
?>
<!DOCTYPE html>
<html lang="sv" style="height:auto !important">
<head>
  <meta charset="utf-8">
  <title>ProAnalys | Professionell Personlighetsanalys - ProAnalys</title>
  <meta name="robots" content="all">
  <meta name="generator" content="One.com Web Editor">
  <meta http-equiv="Cache-Control" content="must-revalidate, max-age=0, public">
  <meta http-equiv="Expires" content="-1">
  <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=3,user-scalable=yes" minpagewidth="1050" rendermobileview="true">
  <meta name="MobileOptimized" content="320">
  <meta name="HandheldFriendly" content="True">
  <meta name="format-detection" content="telephone=no">
  <meta property="og:type" content="website">
  <meta property="og:title" content="ProAnalys | Professionell Personlighetsanalys - ProAnalys">
  <meta property="og:site_name" content="Professionell Personlighetsanalys - ProAnalys">
  <meta property="og:url" content="https://proanalys.se/proanalys">
  <meta property="og:image" content="https://impro.usercontent.one/appid/oneComWsb/domain/proanalys.se/media/proanalys.se/onewebmedia/ProAnalys.png?etag=%221efbd-679f7974%22&amp;sourceContentType=image%2Fpng&amp;quality=85">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="3203">
  <meta property="og:image:height" content="660">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="ProAnalys | Professionell Personlighetsanalys - ProAnalys">
  <meta name="twitter:image:alt" content="ProAnalys | Professionell Personlighetsanalys - ProAnalys">
  <meta name="twitter:image" content="https://impro.usercontent.one/appid/oneComWsb/domain/proanalys.se/media/proanalys.se/onewebmedia/ProAnalys.png?etag=%221efbd-679f7974%22&amp;sourceContentType=image%2Fpng&amp;quality=85">
  <link rel="shortcut icon" sizes="16x16" href="https://impro.usercontent.one/appid/oneComWsb/domain/proanalys.se/media/proanalys.se/onewebmedia/PA-logo.png?etag=W%2F%22313d-6794e9db%22&amp;sourceContentType=image%2Fpng&amp;resize=16,16&amp;ignoreAspectRatio">
  <link rel="icon" sizes="32x32" href="https://impro.usercontent.one/appid/oneComWsb/domain/proanalys.se/media/proanalys.se/onewebmedia/PA-logo.png?etag=W%2F%22313d-6794e9db%22&amp;sourceContentType=image%2Fpng&amp;resize=32,32&amp;ignoreAspectRatio">
  <link rel="apple-touch-icon" href="https://impro.usercontent.one/appid/oneComWsb/domain/proanalys.se/media/proanalys.se/onewebmedia/PA-logo.png?etag=W%2F%22313d-6794e9db%22&amp;sourceContentType=image%2Fpng&amp;resize=57,57&amp;ignoreAspectRatio">
  <!-- Inkludera Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Exakt designspråk – hämtat från ditt exempel */
    body { 
      font-family: Tahoma, sans-serif !important;
      font-size: 0.9em !important;
      background-color: #f8f9fa;
      color: #F2F2F2;
      margin: 0;
      padding: 0px; 
    }
    h2#surveyTitle {
      text-align: center;
      color: #353A3F;
      font-size: 20px;
      margin-top: 20px;
      margin-bottom: 30px;
    }
    .question {
      margin-bottom: 8px;
      padding: 15px;
      background-color: #214A81;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }
    .option-card {
      background-color: #214A81;
      color: #F2F2F2;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    .option-card h4 {
      margin-bottom: 15px;
      color: #F2F2F2;
    }
    .form-check-label {
      font-family: Tahoma, sans-serif !important;
      font-size: 0.9em !important;
      cursor: pointer;
    }
    input[type="radio"] {
      appearance: none;
      width: 20px;
      height: 20px;
      border: 2px solid #214A81;
      border-radius: 50%;
      outline: none;
      transition: all 0.1s ease-in-out;
    }
    input[type="radio"]:checked {
      background-color: #920000;
      border-color: #D90101;
    }
    .btn-primary, .btn-custom {
      background-color: #214A81 !important;
      border-color: #214A81 !important;
      color: #F2F2F2 !important;
      font-family: Tahoma, sans-serif !important;
      font-size: 0.9em !important;
    }
    .btn-primary:hover,
    .btn-primary:focus {
      background-color: #163A5F !important;
      border-color: #163A5F !important;
      color: #FFFFFF !important;
    }
    .btn-primary:active {
      background-color: #102A47 !important;
      border-color: #102A47 !important;
      color: #F2F2F2 !important;
    }
  </style>
</head>
<body class="Preview_body__2wDzb bodyBackground" style="overflow-y:scroll;overflow-x:hidden">
  <div class="container" style="position: relative; min-height: 100vh;">
    <form action="process_survey.php" method="POST">
      <h2 id="surveyTitle">Vilken samling av punkter/påståenden passar bäst in på dig?</h2>
      
      <?php if ($numUncertain === 1): ?>
        <!-- 2 alternativ sida vid sida -->
        <div class="row">
          <?php 
            $groupIndex = 1;
            foreach ($clusters as $peMaId => $categories):
          ?>
            <div class="col-md-6">
              <div class="option-card">
                <h4>Alternativ <?php echo $groupIndex; ?></h4>
<ul>
  <?php foreach ($categories as $categoryId => $statements): ?>
    <?php foreach ($statements as $statement): ?>
      <li><?php echo $statement; ?></li>
    <?php endforeach; ?>
  <?php endforeach; ?>
</ul>
                <div class="form-check mt-3">
                  <input class="form-check-input" type="radio" name="selected_cluster" id="option_<?php echo $groupIndex; ?>" value="<?php echo $peMaId; ?>" required>
                  <label class="form-check-label" for="option_<?php echo $groupIndex; ?>">Detta alternativ passar in bäst</label>
                </div>
              </div>
            </div>
          <?php 
              $groupIndex++;
            endforeach;
          ?>
        </div>
      
      <?php elseif ($numUncertain === 2): ?>
        <!-- 4 alternativ i fyrfältslayout (2 rader med 2 kolumner) -->
        <?php 
          $groupIndex = 1;
          $i = 0;
          foreach ($clusters as $peMaId => $categories):
            if ($i % 2 === 0) {
              echo '<div class="row">';
            }
        ?>
            <div class="col-md-6">
              <div class="option-card">
                <h4>Alternativ <?php echo $groupIndex; ?></h4>
<ul>
  <?php foreach ($categories as $categoryId => $statements): ?>
    <?php foreach ($statements as $statement): ?>
      <li><?php echo $statement; ?></li>
    <?php endforeach; ?>
  <?php endforeach; ?>
</ul>
                <div class="form-check mt-3">
                  <input class="form-check-input" type="radio" name="selected_cluster" id="option_<?php echo $groupIndex; ?>" value="<?php echo $peMaId; ?>" required>
                  <label class="form-check-label" for="option_<?php echo $groupIndex; ?>">Välj detta alternativ</label>
                </div>
              </div>
            </div>
        <?php 
            $groupIndex++;
            $i++;
            if ($i % 2 === 0) {
              echo '</div>';
            }
          endforeach;
        ?>
      <?php endif; ?>
      
      <!-- Dolda fält -->
      <input type="hidden" name="profile_code" value="<?php echo htmlspecialchars($profileCode); ?>">
      <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answerId); ?>">
      
      <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary">Bekräfta val</button>
      </div>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
