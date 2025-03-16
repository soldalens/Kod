<?php
session_start();
    
    // ---------------------------------------------------------------- Aktivera

    require 'db_connect.php';
    
    // ---------------------------------------------------------------- Inloggning PHP

    if (!isset($_SESSION['logged_in'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = trim($_POST['userid']);
            $password = trim($_POST['password']);
    
            // Fetch user and password hash
            $stmt = $conn->prepare("SELECT Password FROM Users WHERE UserId = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->bind_result($hashedPassword);
            $stmt->fetch();
            $stmt->close();
    
            if (!$hashedPassword) {
                $error = "Fel användarnamn eller lösenord!";
            } elseif (password_verify($password, $hashedPassword)) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $userId;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "Fel användarnamn eller lösenord!";
            }
    }

?>

    <!---------------------------------------------------------------- HTML - Inloggningsruta -->

    <!DOCTYPE html>
    
    <html lang="en">
    <head>
       
        <meta charset="UTF-8">
        <title>Logga in</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                font-family: Arial, sans-serif;  
                background-color: #214A81;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                color: #F2F2F2;
            }
            .login-container {
                background-color: #163A5F;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
                width: 350px;
            }
            .login-container h2 {
                text-align: center;
                margin-bottom: 20px;
            }
            .form-control {
                margin-bottom: 15px;
            }
            .btn-primary {
                width: 100%;
                background-color: #214A81;
                border-color: #214A81;
            }
            .btn-primary:hover {
                background-color: #163A5F;
            }
            .error {
                color: red;
                text-align: center;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Logga in</h2>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="text" name="userid" class="form-control" placeholder="Användar-ID" required>
                <input type="password" name="password" class="form-control" placeholder="Lösenord" required>
                <button type="submit" class="btn btn-primary">Logga in</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    // Förstör sessionen och omdirigera användaren
    session_unset(); // Rensa alla session-variabler
    session_destroy(); // Förstör sessionen
    header("Location: administration.php"); // Omdirigera till inloggningssidan
    exit();
}

    // --------------------------------------------------------------------------------------------- START PÅ PLP

    // ---------------------------------------------------------------- Tom Sökfråga

$searchQuery = "";
$result = null; // Se till att variabeln alltid är definierad

// Kontrollera om en sökfråga har skickats
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = "%" . trim($_GET['search']) . "%";
        
        // Förbered SQL för att söka i tabellen SurveyHeaders
        $stmt = $conn->prepare("
            SELECT AnswerId, Name, Email, BestPerson, SubmissionTime, Gender, Age, Education, GroupName, Company, InviteCode, ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent, PersonalityType, CorrectedPersonalityType 
            FROM SurveyHeaders 
            WHERE Name LIKE ? 
               OR Email LIKE ? 
               OR GroupName LIKE ? 
               OR PersonalityType LIKE ? 
               OR CorrectedPersonalityType LIKE ? 
            ORDER BY SubmissionTime DESC
        ");
        $stmt->bind_param("sssss", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery);
        $stmt->execute();
        $result = $stmt->get_result(); // Hämta resultaten
    } else {
        // Om ingen sökning gjordes, visa en tom tabell
        $result = $conn->query("SELECT * FROM SurveyHeaders WHERE 1 = 0");
    }
    
 // ---------------------------------------------------------------- Hämta users   

$sql2 = "SELECT UserId, Name FROM Users";
$result2 = $conn->query($sql2);

$users = [];
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $users[] = $row;
    }
}

 // ---------------------------------------------------------------- Hämta språk   

$sqlLanguages = "SELECT LanguageId, LanguageCode, LanguageDescr FROM Languages";
$resultLanguages = $conn->query($sqlLanguages);
$languages = [];
if ($resultLanguages->num_rows > 0) {
    while ($row = $resultLanguages->fetch_assoc()) {
        $languages[] = $row;
    }
}
    
 // ---------------------------------------------------------------- Ladda extrakod    

    require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PhpOffice\PhpSpreadsheet\IOFactory;
    
// ---------------------------------------------------------------- Ladda upp excelfil


// Hantera uppladdning av Excel-fil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];

    try {
        // Läs Excel-filen
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Ta bort första raden (rubrikraden)
        array_shift($rows);

        $response = [];

        // Iterera över raderna
        foreach ($rows as $index => $row) {
            // Skippa tomma rader
            if (empty(trim($row[0])) || empty(trim($row[1]))) {
                continue;
            }

            if ($index === 0) {
                // Första posten fyller i de redan befintliga UI-fälten
                $response[] = [
                    'type' => 'update',
                    'name' => htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'),
                    'email' => htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8')
                ];
            } else {
                // Skapa nya fält för ytterligare poster
                $response[] = [
                    'type' => 'add',
                    'name' => htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'),
                    'email' => htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8')
                ];
            }
        }

        // Returnera data till klienten som JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $response]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit();
}

// ---------------------------------------------------------------- Mejlinbjudningar

// Hantera inbjudningar med PHPMailer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_multiple'])) {
    $inviteNames = $_POST['invite_name'];
    $selectGroup = $_POST['selectGroup'];
    $inviteEmails = $_POST['invite_email'];
    $inviteBy = $_POST['inviteBy']; // Vald avsändare
    $selectedTemplateName = $_POST['selected_template_name']; // Vald mallnamn

    $successMessages = [];
    $errorMessages = [];

    // Hämta avsändarens e-post och namn från databasen
    $stmtSender = $conn->prepare("SELECT Email, Name FROM Users WHERE UserId = ?");
    $stmtSender->bind_param("s", $inviteBy);
    $stmtSender->execute();
    $stmtSender->bind_result($senderEmail, $senderName);
    $stmtSender->fetch();
    $stmtSender->close();

    // Kontrollera om avsändaren finns
    if (empty($senderEmail)) {
        $errorMessages[] = "Ogiltig avsändare vald. Kontrollera formuläret.";
    } else {
        foreach ($inviteNames as $index => $inviteName) {
            $inviteEmail = $inviteEmails[$index];
            $inviteCode = bin2hex(random_bytes(4)); // Generera en 8-teckens kod
            
            $mail = new PHPMailer(true);
            $stockholmTime = new DateTime("now", new DateTimeZone("Europe/Stockholm"));
            $currentDateTime = $stockholmTime->format('Y-m-d H:i:s');
            
            // Uppdatera SQL för att inkludera InviteTime
            $stmt = $conn->prepare("INSERT INTO SurveyInvites (Name, Email, InviteCode, GroupName, InvitedBy, InviteTime) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $inviteName, $inviteEmail, $inviteCode, $selectGroup, $inviteBy, $currentDateTime);
            if ($stmt->execute())
            
                // Lägg till inline-bilder
                $mail->AddEmbeddedImage(__DIR__ . '/onewebmedia/ProAnalys_small.png', 'pa_logo_id', 'pa_small.png');
                
                // Innan du skickar e-post, hämta det valda språket:
                $selectedLanguage = isset($_POST['inviteLanguage']) ? $_POST['inviteLanguage'] : 'Swedish';

            // Skapa länken
            if ($selectedLanguage === 'English') {
                $linkText = "Your personal access link";
            } else {
                $linkText = "Personlig länk";
            }
            
            $länk = "<a href='https://proanalys.se/proanalys?name=" . urlencode($inviteName) .
                "&email=" . urlencode($inviteEmail) . "&code=" . urlencode($inviteCode) . "&group=" . urlencode($selectGroup) . "'>" . $linkText . "</a>";
            
                // Definiera inline-bild-HTML
                $pa_logo = '<p style="text-align: left; margin-top: 0px;">
                              <img src="cid:pa_logo_id" alt="ProAnalys Logo" style="width: 180px; height: auto;">
                           </p>';

            // Hämta mejltexten från localStorage via JavaScript (den skickas med i POST-data)
            $emailBody = $_POST['email_body'];
            
            $firstName = explode(' ', $inviteName)[0];

            // Ersätt variabler i mejlkroppen
            $processedEmailBody = nl2br(str_replace(
                ['[förnamn]', '[länk]', '[pa_logo]'], // Variabler i mejltexten
                [$firstName, $länk, $pa_logo],          // Ersätt med värden
                $emailBody
            ));

            // Skicka mejlet
        
            try {
                $mail->isSMTP();
                $mail->Host = 'mailout.one.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'inbjudan@proanalys.se';
                $mail->Password = 'MFFDortmund9!';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true); // Använd HTML

                // Sätt avsändare baserat på användarens val
                $mail->setFrom('inbjudan@proanalys.se', $senderName);
                $mail->addReplyTo($senderEmail, $senderName);
                $mail->addAddress($inviteEmail, $inviteName);
                
                // Sätt ämnesraden beroende på valt språk
                if ($selectedLanguage === 'English') {
                    $mail->Subject = "Invitation to ProAnalys – a qualitative personality analysis";
                } else {
                    // För Swedish eller övriga alternativ
                    $mail->Subject = "Inbjudan till ProAnalys – en kvalitativ personlighetsanalys";
                }
                
                $mail->Body = $processedEmailBody;

                $mail->send();
                $successMessages[] = "Inbjudan har skickats till $inviteEmail!";
            } catch (Exception $e) {
                $errorMessages[] = "Kunde inte skicka inbjudan till $inviteEmail. Fel: {$mail->ErrorInfo}";
            }
        }
    }

    // Visa meddelanden
    if (!empty($successMessages)) {
        $successMsg = implode("<br>", $successMessages);
    }
    if (!empty($errorMessages)) {
        $errorMsg = implode("<br>", $errorMessages);
    }
}


    // ---------------------------------------------------------------- Sök grupp
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_Dyn_Groups'])) {
        // Hämta query-parametern från GET-förfrågan
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
        try {
            // Om ingen query anges, returnera en tom lista
            if (empty($query)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'Groups' => []]);
                exit;
            }
    
            // Förbered SQL för filtrering
            $stmt = $conn->prepare("SELECT GroupId, GroupName FROM Groups WHERE GroupName LIKE ? ORDER BY GroupName ASC");
            $likeQuery = '%' . $query . '%';
            $stmt->bind_param('s', $likeQuery);
    
            // Utför SQL-frågan
            $stmt->execute();
            $result = $stmt->get_result();
            $Groups = [];
    
            // Hämta resultaten
            while ($row = $result->fetch_assoc()) {
                $Groups[] = $row;
            }
    
            // Returnera som JSON
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'Groups' => $Groups]);
    
            $stmt->close();
        } catch (Exception $e) {
            // Hantera fel
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Fel vid hämtning av grupper.', 'error' => $e->getMessage()]);
        }
        exit;
    }
    



    // --------------------------------------------------------------------------------------------- SLUT PÅ PLP
    
    // --------------------------------------------------------------------------------------------- HUVUDSIDA - LÄNGST UPP och CSS
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Personlighetsanalys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .icon-btn {
        background: none;       /* Ingen bakgrund */
        border: none;           /* Ingen ram */
        outline: none !important; /* Ta bort fokusram */
        box-shadow: none !important; /* Ingen box-skugga */
        padding: 0;             /* Ingen utfyllnad */
        cursor: pointer;        /* Pekhand för klickbarhet */
        font-size: 10px;        /* Justera storlek på ikonen */
        color: inherit;         /* Ärva textfärgen från omgivande element */
    }
    
    .icon-btn:hover,
    .icon-btn:focus {
        background: none;       /* Ingen bakgrund vid hover */
        box-shadow: none;       /* Ingen skugga vid hover */
        outline: none;          /* Ingen fokusram vid tangentbordsfokus */
    }
    
    .table .icon-btn {
        margin: 0 auto;         /* Centrerar ikonen i cellen */
        display: flex;          /* Flex för att centrera innehållet */
        align-items: center;
        justify-content: center;
    }
    .modal-body {
    color: #000; /* Mörk textfärg */
    }
    .modal-title {
    color: #000; /* Mörk textfärg för rubriken */
    }
    
    .wider-container {
    max-width: 90%; /* Justera till önskad bredd (procent eller px) */
    margin: auto;   /* Centrerar containern */
    }
    
    body {
    font-family: Arial, sans-serif;
    background-color: #214A81; /* Blå bakgrundsfärg för hela sidan */
    color: #F2F2F2; /* Ljus textfärg för att matcha */
    margin: 0;
    padding: 0;
    }
    
    .container {
        background-color: #214A81; /* Mörkare blå för innehållet */
        padding: 20px;
        border-radius: 8px; /* Rundade hörn för innehållscontainern */
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); /* Liten skugga */
    }
    .styled-table {
        margin: 20px 0;
        background-color: #214A81; /* Blå bakgrund */
        border-radius: 8px; /* Rundade hörn */
        color: #F2F2F2; /* Textfärg */
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); /* Skugga */
        overflow: hidden; /* För att hålla hörnen rundade */
    }
    
    .styled-table thead {
        background-color: #163A5F; /* Mörkare blå för rubriken */
        color: #F2F2F2;
    }
    
    .styled-table th, .styled-table td {
        padding: 10px;
        border: 1px solid #102A47; /* Mörkare kantlinjer */
    }
    
    .styled-table tbody tr:nth-child(even) {
        background-color: #163A5F; /* Ljusare blå bakgrund för jämna rader */
    }
    
    .styled-table tbody tr:nth-child(odd) {
        background-color: #214A81; /* Huvudfärg för udda rader */
    }
    .small-text-table {
        font-size: 12px; /* Anpassa storleken som du vill */
    }
        body { 
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #F2F2F2;
            margin: 0;
            padding: 0px; 
        }
        h1 {
            text-align: center;
            margin-bottom: 0px;
            color: #007bff;
        }
        .form-label {
            font-weight: bold;
        }
        .question {
            margin-bottom: 8px;
            padding: 15px;
            background-color: #214A81;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .hidden {
            display: none;
        }

        /* Style the label for spacing and cursor */
        .form-check-label {
            cursor: pointer;
        }
        
        /* Style the radio button itself */
        input[type="radio"] {
            appearance: none; /* Remove default styling */
            width: 20px;
            height: 20px;
            border: 2px solid #214A81; /* Custom border color */
            border-radius: 50%; /* Makes it circular */
            outline: none;
            transition: all 0.1s ease-in-out;
        }
        
        /* Add custom styling for checked state */
        input[type="radio"]:checked {
            background-color: #FCD169; /* Fill color when checked */
            border-color: #F29345;
        }
        /* Change normal color of btn-primary */
        .btn-primary {
            background-color: #214A81 !important; /* Custom background color */
            border-color: #214A81 !important;    /* Custom border color */
            color: #F2F2F2 !important;          /* Custom text color */
        }
        
        /* Change hover color of btn-primary */
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #163A5F !important; /* Darker shade on hover */
            border-color: #163A5F !important;
            color: #FFFFFF !important;            /* Ensure readable text */
        }
        
        /* Active (pressed) state */
        .btn-primary:active {
            background-color: #102A47 !important; /* Even darker shade when pressed */
            border-color: #102A47 !important;
            color: #F2F2F2 !important;

}

    </style>
    
</head>

    <!---------------------------------------------------------------- Sökformulär och resultattabell -->


<!---------------------------------------------------------------- INBJUDNINGSFORMULÄR -->


    <!-- Inbjudningsformulär -->
        <div class="container wider-container mt-2">
            <h2>Bjud in nya användare</h2>

            <div>
                <!-- <label for="emailBody">Mejltext:</label> -->
                <textarea id="emailBody" class="form-control" placeholder="Skriv ditt mejl här, eller ladda en befintlig mall. Du kan använda [förnamn] och [länk] i mejlet..." rows="8"></textarea>
                <div class="spacing-group">
                     <input id="emailTemplateName" type="text" class="form-control mt-2" placeholder="Ange ett namn för din mejlmall">
                </div>
                <div class="d-flex justify-content-between">
                    <button id="loadEmailTemplate" class="btn btn-primary mt-2">Ladda mejlmall</button>
                    <button id="saveEmailTemplate" class="btn btn-primary mt-2">Spara mejlmall</button>
                </div>
                    <!-- Horisontell linje -->
                <hr style="border-top: 2px solid #F2F2F2; margin: 20px 0;">
            </div>
            
            <!-- Modal för att välja en mejlmall -->
            <div id="emailTemplateModal" class="modal fade" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Välj en mejlmall</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Stäng"></button>
                        </div>
                        <div class="modal-body">
                            <ul id="emailTemplateList" class="list-group">
                                <!-- Mallar laddas här -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($successMsg)): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php elseif (isset($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php endif; ?>
            
        <form method="post" id="inviteForm">
            <input type="hidden" id="selectedTemplateName" name="selected_template_name">
            <input type="hidden" id="emailBodyContent" name="email_body">
            <!-- Formulär för att bjuda in flera personer -->
            <form method="post" id="inviteForm">
                <div id="inviteContainer">
                     <div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="selectGroup" class="form-label">Välj grupp som inbjudningen ska kopplas emot:</label> 
                                <input type="text" id="selectGroup" name="selectGroup" class="form-control" placeholder="Skriv för att söka grupp..." autocomplete="off">
                                <ul id="groupSuggestions" class="list-group mt-2" style="display: none;">
                                <!-- Förslag på grupper kommer att läggas till här dynamiskt -->
                                </ul>
                            </div>
                        
                            <div class="col-md-6">
                                <label for="inviteBy" class="form-label">Välj avsändare av inbjudan:</label>
                                <select name="inviteBy" id="inviteBy" class="form-control">
                                    <option value="">Välj avsändare...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['UserId']; ?>"><?php echo htmlspecialchars($user['Name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
    
                            <div class="col-md-6">
                                <label for="inviteLanguage" class="form-label">Välj språk för inbjudan:</label>
                                <select name="inviteLanguage" id="inviteLanguage" class="form-control" required>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?php echo $lang['LanguageDescr']; ?>">
                                            <?php echo htmlspecialchars($lang['LanguageDescr']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>                       
                        </div>
                                             <!-- Horisontell linje -->
                <hr style="border-top: 2px solid #F2F2F2; margin: 20px 0;">
                    </div>
                    <div class="row mb-2 invite-row align-items-center">
                        <div class="col-md-5">
                            <div class="spacing-group">
                                <input type="text" name="invite_name[]" class="form-control" placeholder="Ange namn" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="spacing-group">
                                <input type="email" name="invite_email[]" class="form-control" placeholder="Ange e-postadress" required>
                            </div>
                        </div>
                        <div class="col-md-5 text-end">
                            <div class="spacing-group">
                                <button type="button" class="btn btn-primary mt-2" style="display: none;">Ta bort</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" id="addRow" class="btn btn-primary mt-2">Lägg till fler</button>
                    <button type="submit" name="invite_multiple" class="btn btn-primary mt-2">Skicka inbjudningar</button>
                </div>
            </form>
        </form>
            <div class="mt-3">
                    <form id="uploadExcelForm" enctype="multipart/form-data">
                        <div class="spacing-group">
                            <input type="file" id="excelFile" accept=".xlsx, .xls" class="form-control w-50" /> 
                        </div>
                        <button id="uploadExcel" class="btn btn-primary">Ladda upp mottagare</button>
                    </form>
        </div>
        </div>
    </div>
    

    <style>
    .tree-list {
        list-style-type: none;
        padding-left: 20px;
    }
    
    .tree-list li {
        position: relative;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .tree-list li .expand-icon {
        display: inline-block;
        width: 20px;
        cursor: pointer;
    }
    
    .tree-list li .expand-icon:after {
        content: "+";
        display: inline-block;
        margin-right: 5px;
        color: #007bff;
    }
    
    .tree-list li.expanded .expand-icon:after {
        content: "-";
    }
    
    .tree-list .nested {
        display: none;
        padding-left: 20px;
    }
    
    .tree-list li.expanded > .nested {
        display: block;
    }
    
    .tree-list .group-checkbox {
        margin-left: 10px;
    }
    </style>
    
<!---------------------------------------------------------------- SKRIPTS -->

 
</div>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


     <script>    
     
    //-------------------------------------------------------------------------------------------------- DYNAMISK GRUPPSÖK?!
     
let availableGroups = []; // Tillgängliga grupper från servern
let isGroupSelected = false; // Spårar om en grupp valdes från listan
let isInteractingWithSuggestions = false; // Spårar om användaren interagerar med förslagslistan

const selectGroupInput = document.getElementById('selectGroup');
const groupSuggestions = document.getElementById('groupSuggestions');

// Hantera input för att visa förslag
selectGroupInput.addEventListener('input', function () {
    const query = this.value.trim();
    isGroupSelected = false; // Återställ status för gruppval
    isInteractingWithSuggestions = false; // Återställ interaktionsstatus

    if (!query) {
        groupSuggestions.style.display = 'none';
        return;
    }

    fetch(`?fetch_Dyn_Groups=true&query=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            groupSuggestions.innerHTML = ''; // Töm tidigare förslag
            availableGroups = []; // Töm tidigare grupper

            if (data.success && data.Groups.length > 0) {
                // Uppdatera tillgängliga grupper
                availableGroups = data.Groups.map(group => group.GroupName);

                // Skapa listan med förslag
                data.Groups.forEach(group => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.textContent = group.GroupName;
                    li.dataset.groupId = group.GroupId;

                    li.addEventListener('mousedown', function () {
                        // Markera att användaren interagerar med listan
                        isInteractingWithSuggestions = true;
                        selectGroupInput.value = group.GroupName;
                        selectGroupInput.dataset.groupId = group.GroupId;
                        isGroupSelected = true; // Markera gruppen som vald
                        groupSuggestions.style.display = 'none'; // Dölj förslagen
                    });

                    groupSuggestions.appendChild(li);
                });
                groupSuggestions.style.display = 'block'; // Visa förslagen
            } else {
                groupSuggestions.style.display = 'none'; // Dölj om inga resultat hittas
            }
        })
        .catch(error => {
            console.error('Error fetching groups:', error);
        });
});

// Validera vid "blur"-händelse
selectGroupInput.addEventListener('blur', function () {
    setTimeout(() => {
        // Kontrollera om användaren interagerade med förslagslistan
        if (isInteractingWithSuggestions) {
            isInteractingWithSuggestions = false; // Återställ interaktionsstatus
            return;
        }

        const groupName = this.value.trim();

        // Om inget val gjorts och gruppen inte finns, visa fel
        if (!isGroupSelected && groupName && !availableGroups.includes(groupName)) {
            alert("Gruppen finns inte. Vänligen välj en giltig grupp.");
            this.value = ''; // Töm fältet
        }
    }, 150); // Kort fördröjning för att hantera klick
});

// Dölj förslagslistan om användaren klickar utanför
document.addEventListener('click', function (event) {
    if (!groupSuggestions.contains(event.target) && event.target !== selectGroupInput) {
        groupSuggestions.style.display = 'none';
    }
});



    </script>




<!-- Din JavaScript för klickbara rader -->
<script>

    //-------------------------------------------------------------------------------------------------- LÄS IN EXCELFIL

document.getElementById('uploadExcel').addEventListener('click', async function (e) {
    e.preventDefault();
    const fileInput = document.getElementById('excelFile');
    const file = fileInput.files[0];

    if (!file) {
        alert('Vänligen välj en Excel-fil.');
        return;
    }

    // Läs in Excel-filen med SheetJS (xlsx)
    const reader = new FileReader();
    reader.onload = function (e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });

        // Förutsätt att första arket är relevant
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];

        // Konvertera till JSON och hoppa över första raden
        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false }).slice(1);

        if (jsonData.length === 0) {
            alert('Filen är tom eller saknar data.');
            return;
        }

        // Referens till UI:t
        const inviteContainer = document.getElementById('inviteContainer');
        const inviteNameInputs = document.querySelectorAll('input[name="invite_name[]"]');
        const inviteEmailInputs = document.querySelectorAll('input[name="invite_email[]"]');

        // Fyll i befintliga fält först (första raden)
        jsonData.forEach((row, index) => {
            const [name, email] = row;

            if (!name || !email) return; // Hoppa över tomma rader

            if (index === 0) {
                // Fyll i de befintliga fälten
                if (inviteNameInputs.length > 0) inviteNameInputs[0].value = name;
                if (inviteEmailInputs.length > 0) inviteEmailInputs[0].value = email;
            } else {
                // Lägg till nya rader i UI:t
                const newRow = document.createElement('div');
                newRow.classList.add('row', 'mb-2', 'invite-row', 'align-items-center');
                newRow.innerHTML = `
                    <div class="col-md-5">
                        <input type="text" name="invite_name[]" class="form-control" placeholder="Ange namn" value="${name}" required>
                    </div>
                    <div class="col-md-5">
                        <input type="email" name="invite_email[]" class="form-control" placeholder="Ange e-postadress" value="${email}" required>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-danger remove-row">Ta bort</button>
                    </div>
                `;
                inviteContainer.appendChild(newRow);
            }
        });

        // Lägg till funktionalitet för `Ta bort`-knappar
        attachRemoveEvents();
    };

    reader.readAsArrayBuffer(file);
});

    //-------------------------------------------------------------------------------------------------- TA BORT-KNAPP-FUNKTION ?!??!?!?!?!?! (och fyll rader i tabellen)


// Funktion för att lägga till funktionalitet till "Ta bort"-knappar
function attachRemoveEvents() {
    const removeButtons = document.querySelectorAll('.remove-row');
    removeButtons.forEach(button => {
        button.addEventListener('click', function () {
            this.closest('.invite-row').remove();
        });
    });
}

// Anropa funktionen för befintliga rader
attachRemoveEvents();

    //-------------------------------------------------------------------------------------------------- MEJL NÅT ?!?!?!?


document.getElementById('inviteForm').addEventListener('submit', function (event) {
    // Kontrollera om en mall är vald
    const selectedTemplateName = localStorage.getItem('selectedTemplateName'); // Hämtar namnet på den valda mallen
    const emailTemplate = localStorage.getItem(selectedTemplateName); // Hämtar mejltexten från den valda mallen
    const selectedGroup = document.getElementById('selectGroup').value.trim();
    const selectedSender = document.getElementById('inviteBy').value.trim();

    if (!selectedTemplateName || !emailTemplate) {
        alert('Du måste välja eller spara en mejlmall innan du skickar inbjudningar!');
        event.preventDefault(); // Förhindra formuläret från att skickas
        return;
    }
    
    // Kontrollera att grupp och avsändare är valda
    if (!selectedGroup) {
        alert('Vänligen välj en grupp innan du skickar inbjudningar.');
        event.preventDefault(); // Förhindra att formuläret skickas
        return;
    }

    if (!selectedSender) {
        alert('Vänligen välj en avsändare innan du skickar inbjudningar.');
        event.preventDefault(); // Förhindra att formuläret skickas
        return;
    }

    // Lägg till mallens namn som ett dolt fält i formuläret
    const templateNameField = document.createElement('input');
    templateNameField.type = 'hidden';
    templateNameField.name = 'selected_template_name';
    templateNameField.value = selectedTemplateName;
    this.appendChild(templateNameField);

    // Lägg till mejlmallen som ett dolt fält i formuläret
    const emailBodyField = document.createElement('input');
    emailBodyField.type = 'hidden';
    emailBodyField.name = 'email_body';
    emailBodyField.value = emailTemplate;
    this.appendChild(emailBodyField);

    console.log('Skickar formulär med:', {
        selectedTemplateName,
        emailTemplate,
    });
});

    //-------------------------------------------------------------------------------------------------- REMMAD GAMMAL KOD?!??!?


// Uppdatera formulärets dolda fält innan det skickas
//document.getElementById('inviteForm').addEventListener('submit', function (event) {
//    const selectedTemplateName = document.getElementById('emailTemplateName').value;
//    const emailBody = document.getElementById('emailBody').value;

//    if (!selectedTemplateName || !emailBody) {
//        alert('Du måste välja en mejlmall och fylla i mejltexten.');
//        event.preventDefault(); // Stoppa formuläret från att skickas
//        return;
//    }
//
//    // Fyll i de dolda fälten
//    document.getElementById('selectedTemplateName').value = selectedTemplateName;
//    document.getElementById('emailBodyContent').value = emailBody;
//});


   //-------------------------------------------------------------------------------------------------- LADDA MEJLMALL


// Ladda mejlmallar från servern och uppdatera localStorage
document.getElementById('loadEmailTemplate').addEventListener('click', function () {
    fetch('load_email_templates.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const emailTemplateList = document.getElementById('emailTemplateList');
                emailTemplateList.innerHTML = ''; // Rensa befintlig lista

                data.templates.forEach(template => {
                    localStorage.setItem(template.template_name, template.email_body); // Spara mallen
                });

                // Visa mallar i listan
                data.templates.forEach(template => {
                    const listItem = document.createElement('li');
                    listItem.classList.add('list-group-item', 'list-group-item-action');
                    listItem.textContent = template.template_name;

                    listItem.addEventListener('click', function () {
                        // Sätt mejlkroppen i textarea
                        document.getElementById('emailBody').value = localStorage.getItem(template.template_name);

                        // Spara vald mall i localStorage
                        localStorage.setItem('selectedTemplateName', template.template_name);
                        console.log(`Vald mall: ${template.template_name}`);

                        // Stäng modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('emailTemplateModal'));
                        modal.hide();
                    });

                    emailTemplateList.appendChild(listItem);
                });

                const modal = new bootstrap.Modal(document.getElementById('emailTemplateModal'));
                modal.show();
            } else {
                alert('Kunde inte ladda mejlmallar.');
            }
        })
        .catch(error => console.error('Fel vid laddning:', error));
});


   //-------------------------------------------------------------------------------------------------- SPARA MEJLMALL

// Spara mejlmall till server och localStorage
document.getElementById('saveEmailTemplate').addEventListener('click', function () {
    const emailBody = document.getElementById('emailBody').value;
    const templateName = document.getElementById('emailTemplateName').value;

    if (!templateName) {
        alert('Du måste ange ett namn för att spara mejltexten.');
        return;
    }

    if (!emailBody) {
        alert('Mejltexten kan inte vara tom.');
        return;
    }

    // Kontrollera om mallen redan finns i localStorage
    if (localStorage.getItem(templateName)) {
        const overwrite = confirm(`En mall med namnet "${templateName}" finns redan i localStorage. Vill du skriva över den?`);
        if (!overwrite) {
            return;
        }
    }
    
   //-------------------------------------------------------------------------------------------------- SPARA MEJLMALL TILL LOCAL STORAGE


    // Spara till localStorage
    localStorage.setItem(templateName, emailBody);
    localStorage.setItem('selectedTemplateName', templateName); // Uppdatera vald mall
    console.log(`Mallen "${templateName}" sparades i localStorage.`);

    // Skicka begäran till servern för att spara mallen
    fetch('save_email_template.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ templateName, emailBody }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Mejlmallen sparades till servern.');
                document.getElementById('emailTemplateName').value = ''; // Rensa namnfältet
            } else if (data.exists) {
                // Hantera om mallen redan finns på servern
                const overwrite = confirm(`En mall med namnet "${templateName}" finns redan på servern. Vill du skriva över den?`);
                if (overwrite) {
                    // Skicka igen med "overwrite: true"
                    fetch('save_email_template.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ templateName, emailBody, overwrite: true }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Mejlmallen uppdaterades på servern.');
                            } else {
                                alert('Kunde inte uppdatera mejlmallen på servern.');
                            }
                        })
                        .catch(error => console.error('Fel vid uppdatering:', error));
                }
            } else {
                alert(`Kunde inte spara mejlmallen: ${data.message}`);
            }
        })
        .catch(error => console.error('Fel vid sparning:', error));
});

   //-------------------------------------------------------------------------------------------------- KLICKBAR RAD - GAMMAL KOD?!?!??!?!?!

/*
document.addEventListener("DOMContentLoaded", function () {
    const rows = document.querySelectorAll(".clickable-row");

    rows.forEach(row => {
        row.addEventListener("click", function () {
            // Hämta data från data-* attributen
            const answerId = this.dataset.answerid || "N/A";
            const name = this.dataset.name || "N/A";
            const email = this.dataset.email || "N/A";
            const bestPerson = this.dataset.bestperson || "N/A";
            const submissionTime = this.dataset.submissiontime || "N/A";
            const gender = this.dataset.gender || "N/A";
            const age = this.dataset.age || "N/A";
            const education = this.dataset.education || "N/A";
            const company = this.dataset.company || "N/A";
            const extroversion = this.dataset.extroversion || "0";
            const sensing = this.dataset.sensing || "0";
            const thinking = this.dataset.thinking || "0";
            const judging = this.dataset.judging || "0";
            const personality = this.dataset.personality || "N/A";

            // Uppdatera modalen med all data
            document.getElementById("modalContent").innerHTML = `
                <p><strong>AnswerId:</strong> ${answerId}</p>
                <p><strong>Namn:</strong> ${name}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Bästa person:</strong> ${bestPerson}</p>
                <p><strong>Submission Time:</strong> ${submissionTime}</p>
                <p><strong>Kön:</strong> ${gender}</p>
                <p><strong>Ålder:</strong> ${age}</p>
                <p><strong>Utbildning:</strong> ${education}</p>
                <p><strong>Företag:</strong> ${company}</p>
                <p><strong>Extroversion:</strong> ${extroversion}%</p>
                <p><strong>Sensing:</strong> ${sensing}%</p>
                <p><strong>Thinking:</strong> ${thinking}%</p>
                <p><strong>Judging:</strong> ${judging}%</p>
                <p><strong>Personlighetstyp:</strong> ${personality}</p>
            `;

            // Visa modalen
            const detailsModal = new bootstrap.Modal(document.getElementById("detailsModal"));
            detailsModal.show();
        });
    });
});
*/

   //-------------------------------------------------------------------------------------------------- LÄGG TILL NYA INBJUDNINGAR


document.addEventListener("DOMContentLoaded", function () {
    const inviteContainer = document.getElementById("inviteContainer");
    const addRowButton = document.getElementById("addRow");

    addRowButton.addEventListener("click", function () {
        const newRow = document.createElement("div");
        newRow.classList.add("row", "mb-2", "invite-row", "align-items-center");
        newRow.innerHTML = `
            <div class="col-md-5">
                <input type="text" name="invite_name[]" class="form-control" placeholder="Ange namn" required>
            </div>
            <div class="col-md-5">
                <input type="email" name="invite_email[]" class="form-control" placeholder="Ange e-postadress" required>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-danger remove-row">Ta bort</button>
            </div>
        `;
        inviteContainer.appendChild(newRow);
        attachRemoveEvents();
    });

    function attachRemoveEvents() {
        document.querySelectorAll(".remove-row").forEach(button => {
            button.addEventListener("click", function () {
                this.closest(".invite-row").remove();
            });
        });
    }
    attachRemoveEvents();
});


</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

   //-------------------------------------------------------------------------------------------------- VÄLJ EN RAD


<script>

    document.addEventListener("DOMContentLoaded", () => {
    const selectedRows = new Set();

    // Handle row selection
    document.querySelectorAll(".row-checkbox").forEach(checkbox => {
        checkbox.addEventListener("change", function () {
            const id = this.dataset.id;
            if (this.checked) {
                selectedRows.add(id);
            } else {
                selectedRows.delete(id);
            }
        });
    });
    
   //-------------------------------------------------------------------------------------------------- ÖPPNA BYT GRUPP-POPUP

/*
     Open Change Group Modal
     document.getElementById("changeGroupButton").addEventListener("click", () => {
        if (selectedRows.size === 0) return alert("Välj minst en rad.");
         const modal = new bootstrap.Modal(document.getElementById("changeGroupModal"));
         modal.show();
     });
    
   //-------------------------------------------------------------------------------------------------- SÖKGROUPP i MODALEN

    // Handle group search
    document.getElementById('modalSelectGroup').addEventListener('input', function () {
        const query = this.value.trim();
    
        if (query.length === 0) {
            document.getElementById('modalGroupSuggestions').style.display = 'none';
            return;
        }
    
        fetch(`?fetch_Dyn_Groups=true&query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                const suggestions = document.getElementById('modalGroupSuggestions');
                suggestions.innerHTML = ''; // Töm tidigare förslag
    
                if (data.success && data.Groups.length > 0) {
                    data.Groups.forEach(group => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.textContent = group.GroupName;
                        li.dataset.groupId = group.GroupId;
    
                        li.addEventListener('click', function () {
                            document.getElementById('modalSelectGroup').value = group.GroupName;
                            document.getElementById('modalSelectGroup').dataset.groupId = group.GroupId;
                            suggestions.style.display = 'none';
                        });
    
                        suggestions.appendChild(li);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            })
            .catch(error => console.error('Error fetching groups:', error));
    });

   //-------------------------------------------------------------------------------------------------- BEKRÄFTA BYT GRUPP

    // Confirm Group Change
    document.getElementById("changeGroupConfirm").addEventListener("click", () => {
        const groupName = document.getElementById("modalSelectGroup").value;
        if (!groupName) return alert("Ange en grupp.");

        console.log("Selected Rows:", Array.from(selectedRows));
        console.log("Group Name:", groupName);
        
        console.log("Skickar till servern:", { ids: Array.from(selectedRows), groupName });
        
        fetch("update_group.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json", // Viktigt!
            },
            body: JSON.stringify({
                ids: Array.from(selectedRows).map(Number), // Konvertera till heltal
                groupName: document.getElementById("modalSelectGroup").value.trim(),
            }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Serverfel: ${response.status}`);
                }
                return response.json(); // Försök att läsa JSON
            })
            .then(data => {
                if (data.success) {
                    alert("Gruppen uppdaterades framgångsrikt!");
                    location.reload(); // Uppdatera sidan
                } else {
                    alert(`Fel: ${data.message}`);
                }
            })
            .catch(error => {
                console.error("Fetch Error:", error);
                alert("Ett fel uppstod vid uppdatering av gruppen.");
            });

    });
    
   //-------------------------------------------------------------------------------------------------- ÖPPNA BYT TYP-POPUP

    // Open Change Type Modal
    document.getElementById("changeTypeButton").addEventListener("click", () => {
        if (selectedRows.size === 0) return alert("Välj minst en rad.");
        const modal = new bootstrap.Modal(document.getElementById("changeTypeModal"));
        modal.show();
    });
    
   //-------------------------------------------------------------------------------------------------- BEKRÄFTA BYT TYP


    // Confirm Type Change
    document.getElementById("confirmTypeChange").addEventListener("click", () => {
        const personalityType = document.getElementById("typeDropdown").value;

        fetch("update_type.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids: Array.from(selectedRows), personalityType })
        }).then(() => location.reload());
    });

   //-------------------------------------------------------------------------------------------------- TA BORT RAD

    // Delete Rows
    document.getElementById("deleteButton").addEventListener("click", () => {
        if (selectedRows.size === 0) return alert("Välj minst en rad.");
        if (!confirm("Är du säker på att du vill ta bort?")) return;
    
        console.log("Selected Rows (Before Sending):", Array.from(selectedRows)); // Debug-logg
    
        fetch("delete_rows.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids: Array.from(selectedRows) }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Serverfel: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Server Response:", data); // Debug-logg
                if (data.success) {
                    alert("Raderna togs bort framgångsrikt!");
                    location.reload(); // Uppdatera sidan
                } else {
                    alert(`Fel: ${data.message}`);
                }
            })
            .catch(error => {
                console.error("Fetch Error:", error);
                alert("Ett fel uppstod vid borttagning av rader.");
            });
    });

});

*/



   //-------------------------------------------------------------------------------------------------- VISA DETALJER - RÄTT KOD?!?!?




document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".details-btn").forEach(button => {
        button.addEventListener("click", function () {
            // Hämta data från knappen
            const answerId = this.getAttribute('data-answerid');
            document.getElementById("detail-name").textContent = this.getAttribute('data-name') || "N/A";
            document.getElementById("detail-email").textContent = this.getAttribute('data-email') || "N/A";
            document.getElementById("detail-bestperson").textContent = this.getAttribute('data-bestperson') || "N/A";
            document.getElementById("detail-submissiontime").textContent = this.getAttribute('data-submissiontime') || "N/A";
            document.getElementById("detail-gender").textContent = this.getAttribute('data-gender') || "N/A";
            document.getElementById("detail-age").textContent = this.getAttribute('data-age') || "N/A";
            document.getElementById("detail-education").textContent = this.getAttribute('data-education') || "N/A";
            document.getElementById("detail-groupname").textContent = this.getAttribute('data-groupname') || "N/A";
            document.getElementById("detail-company").textContent = this.getAttribute('data-company') || "N/A";
            document.getElementById("detail-invitecode").textContent = this.getAttribute('data-invitecode') || "N/A";
            document.getElementById("detail-extroversionpercent").textContent = this.getAttribute('data-extroversionpercent') || "0";
            document.getElementById("detail-sensingpercent").textContent = this.getAttribute('data-sensingpercent') || "0";
            document.getElementById("detail-thinkingpercent").textContent = this.getAttribute('data-thinkingpercent') || "0";
            document.getElementById("detail-judgingpercent").textContent = this.getAttribute('data-judgingpercent') || "0";
            document.getElementById("detail-personalitytype").textContent = this.getAttribute('data-personalitytype') || "N/A";
            document.getElementById("detail-correctedpersonalitytype").textContent = this.getAttribute('data-correctedpersonalitytype') || "N/A";

            // Ladda innehåll i iframe om det finns ett AnswerId
            const iframe = document.getElementById('detailsFrame');
            iframe.src = answerId ? `https://proanalys.se/Result_details.php?AnswerId=${encodeURIComponent(answerId)}` : '';

            // Visa modalen
            const detailsModal = new bootstrap.Modal(document.getElementById("detailsModal"));
            detailsModal.show();
        });
    });
});



    
</script>


</body>
</html>

<?php
// Stäng anslutning och statement om de är inställda
if (isset($stmt) && $stmt !== null) {
    $stmt->close();
}

if (isset($conn) && $conn !== null) {
    $conn->close();
}
?>
