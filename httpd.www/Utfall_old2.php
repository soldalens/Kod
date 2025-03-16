<?php
session_start();
    
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
        $result = $conn->query("SELECT AnswerId, Name, Email, BestPerson, SubmissionTime, Gender, Age, Education, GroupName, Company, InviteCode, ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent, PersonalityType, CorrectedPersonalityType
FROM SurveyHeaders
ORDER BY SubmissionTime DESC
LIMIT 30");
    }
    
  // ---------------------------------------------------------------- Sök senaste  
    
if (isset($_GET['latest']) && $_GET['latest'] === 'true') {
    $stmt = $conn->prepare("
        SELECT AnswerId, Name, Email, BestPerson, SubmissionTime, Gender, Age, Education, GroupName, Company, InviteCode, ExtroversionPercent, SensingPercent, ThinkingPercent, JudgingPercent, PersonalityType, CorrectedPersonalityType 
        FROM SurveyHeaders 
        ORDER BY SubmissionTime DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $result = $stmt->get_result(); // Hämta de senaste resultaten
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
    
 // ---------------------------------------------------------------- Ladda extrakod    
    
// ---------------------------------------------------------------- Ladda upp excelfil


// ---------------------------------------------------------------- Mejlinbjudningar

    
    // ---------------------------------------------------------------- Hämta grupp

    // Hantera hämtning av grupper
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_Groups'])) {
        $result = $conn->query("SELECT GroupId, GroupName FROM Groups ORDER BY created_at DESC");
        $Groups = [];

        while ($row = $result->fetch_assoc()) {
            $Groups[] = $row;
        }

        echo json_encode(['success' => true, 'Groups' => $Groups]);
        exit;
    }
    
    // ---------------------------------------------------------------- Ta bort grupp - AAA

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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Personlighetsanalys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>

    @media print {
        body {
            margin: 0;
            padding: 10mm;
            font-size: 12px;
        }
        ::before {
            content: "ProAnalys - Professionell Personlighetsanalys";
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 50px; /* Höjden på sidhuvudet */
            background-color: #ffffff; /* Bakgrundsfärg */
            color: #002060;
            text-align: center;
            font-size: 12px;
            line-height: 50px; /* Centrerar texten vertikalt */
            font-weight: normal;
            z-index: 9999; /* Se till att det ligger ovanpå allt annat */
        }
    
        /* Sidfot */
        ::after {
            content: "https://proanalys.se/proanalys";
            display: block;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background-color: #ffffff; /* Bakgrundsfärg */
            color: #002060;
            text-align: center;
            font-size: 12px;
            line-height: 30px;
            font-weight: normal;
        }
        .line-break {
            margin-top: 20px; /* Avstånd före radbrytningen */
            margin-bottom: 20px; /* Avstånd efter radbrytningen */
        }
        .btn-close {
        display: none !important; /* Dölj stängningsknappen i utskriften */
        }
        .page-break {
            page-break-after: always; /* Tvingar en sidbrytning efter elementet */
        }
        .modal-content {
            background-color: white;
            padding: 10mm;
            box-shadow: none; /* Ta bort skuggor */
        }
        br.print-only {
                display: block !important;
            }
        .progress-bar {
            -webkit-print-color-adjust: exact; /* Bevara färger */
            color-adjust: exact; /* För moderna webbläsare */
        }
        .modal-header {
                display: flex;
                justify-content: center !important; /* Centrera innehållet horisontellt */
                align-items: bottom;     /* Centrera innehållet vertikalt */
                background-color: white; /* Förhindra annan bakgrund */
            }
            .modal-header h3 {
                color: black;          /* Svart text */
                font-size: 20px;       /* Storlek 20 */
                margin: 0;             /* Ta bort eventuell marginal */
            }
            .modal-header h2 {
                color: black;          /* Svart text */
                font-size: 20px;       /* Storlek 20 */
                margin: 0;             /* Ta bort eventuell marginal */
            }
        
        /* Färger för staplar */
        .bg-extrovert-dark { background-color: #FF9C00 !important; color: black !important; }
        .bg-extrovert-light { background-color: #FFE2C5 !important; }

        .bg-sensing-dark { background-color: #00B100 !important; color: white !important; }
        .bg-sensing-light { background-color: #E6FFE6 !important; }

        .bg-thinking-dark { background-color: #00E2EF !important; color: black !important; }
        .bg-thinking-light { background-color: #D5FFFF !important; }

        .bg-judging-dark { background-color: #4F57FF !important; color: white !important; }
        .bg-judging-light { background-color: #D4EEFF !important; }

        /* Anpassa innehållet för A4 */
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        .progress-container {
            margin-bottom: 10px;
        }

        .modal-footer {
            display: none; /* Dölj onödiga kontroller i utskrift */
        }

        .modal-header {
            text-align: center;
            border-bottom: none;
        }
    }

    br.print-only {
        display: none;
    }
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
    .custom-modal {
        font-family: Arial, sans-serif;
    }
    
    .custom-modal-body {
        color: #333; /* Endast påverkan på modalens textfärg */
    }
    
    .custom-modal-footer button {
        background-color: #214A81;
        color: #ffffff;
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
    
    .centered-title {
        text-align: center !important;
        width: 100%; /* Se till att rubriken tar upp hela bredden */
        margin: 0 auto; /* Centrera horisontellt */
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
.modal-content table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.modal-content th, .modal-content td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.modal-content th {
    background-color: #F2F2F2;
    color: #214A81;
    font-weight: bold;
}


    </style>
    
</head>

    <!---------------------------------------------------------------- Sökformulär och resultattabell -->

<body>
<div class="container wider-container mt-2">
    
    <h2>Hantera analyser</h2>

<!-- Sökformulär -->
<form method="get" id="searchForm" class="mb-4">
    <div class="input-group">
        <input type="text" name="search" id="searchInput" class="form-control" 
               placeholder="Sök efter namn, mejl, grupp, eller personlighetstyp..." 
               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        <button type="submit" class="btn btn-primary">Sök</button>
        <button type="button" id="latestButton" class="btn btn-secondary">Senaste</button>
    </div>
</form>


    <!-- Resultattabell -->
<h2>Resultat</h2>
<table class="table table-striped small-text-table styled-table">
    <thead>
        <tr>
            <th>🔍</th>
            <th>📋</th>
            <th>Namn</th>
            <th>Mejl</th>
            <th>Grupp</th>
            <th>Typ</th>
            <th>Korr. Typ</th>
            <th>Datum</th>
            <th>Välj</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <button class="icon-btn details-btn" 
                            data-answerid="<?php echo htmlspecialchars($row['AnswerId']); ?>"
                            data-name="<?php echo htmlspecialchars($row['Name']); ?>"
                            data-email="<?php echo htmlspecialchars($row['Email']); ?>"
                            data-bestperson="<?php echo htmlspecialchars($row['BestPerson'] ?? ''); ?>"
                            data-submissiontime="<?php echo htmlspecialchars($row['SubmissionTime']); ?>"
                            data-gender="<?php echo htmlspecialchars($row['Gender'] ?? ''); ?>"
                            data-age="<?php echo htmlspecialchars($row['Age'] ?? ''); ?>"
                            data-education="<?php echo htmlspecialchars($row['Education'] ?? ''); ?>"
                            data-groupname="<?php echo htmlspecialchars($row['GroupName']); ?>"
                            data-company="<?php echo htmlspecialchars($row['Company'] ?? ''); ?>"
                            data-invitecode="<?php echo htmlspecialchars($row['InviteCode'] ?? ''); ?>"
                            data-extroversionpercent="<?php echo htmlspecialchars($row['ExtroversionPercent'] ?? 0); ?>"
                            data-sensingpercent="<?php echo htmlspecialchars($row['SensingPercent'] ?? 0); ?>"
                            data-thinkingpercent="<?php echo htmlspecialchars($row['ThinkingPercent'] ?? 0); ?>"
                            data-judgingpercent="<?php echo htmlspecialchars($row['JudgingPercent'] ?? 0); ?>"
                            data-personalitytype="<?php echo htmlspecialchars($row['PersonalityType'] ?? ''); ?>"
                            data-correctedpersonalitytype="<?php echo htmlspecialchars($row['CorrectedPersonalityType'] ?? ''); ?>">
                            🔍
                        </button>
                    </td>
                    <td>
                        <button class="icon-btn answers-btn" 
                            data-answerid="<?php echo htmlspecialchars($row['AnswerId']); ?>">
                            📋
                        </button>
                    </td>
                    <td><?php echo htmlspecialchars($row['Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Email']); ?></td>
                    <td><?php echo htmlspecialchars($row['GroupName']); ?></td>
                    <td><?php echo htmlspecialchars($row['PersonalityType']); ?></td>
                    <td><?php echo htmlspecialchars($row['CorrectedPersonalityType'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($row['SubmissionTime']))); ?></td>
                    <td>
                        <input type="checkbox" class="row-checkbox" data-id="<?php echo htmlspecialchars($row['AnswerId']); ?>">
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center">Inga resultat att visa</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div>
    <button id="deleteButton" class="btn btn-primary">Ta bort analys</button>
    <button id="changeGroupButton" class="btn btn-primary">Ändra grupp</button>
    <button id="changeTypeButton" class="btn btn-primary">Ändra typ</button>
</div>

    <!---------------------------------------------------------------- Detaljer i POP-UP -->

<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content custom-modal">
            <!-- Header -->
            <div class="modal-header" style="background-color: #214A81; color: #ffffff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <h5 class="modal-title" id="detailsModalLabel">
                    <h3><span id="detail-name"></span></h4>
                </h5>
                <button type="button" class="btn-close" style="filter: invert(100%);" data-bs-dismiss="modal" aria-label="Stäng"></button>
            </div>

            <!-- Body -->
            <div class="modal-body custom-modal-body" style="background-color: #f8f9fa; color: #333;">
                <div id="modalContent" style="padding: 15px;">
                    <p>Laddar innehåll...</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer custom-modal-footer" style="background-color: #e9ecef; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #214A81; color: #ffffff; border-color: #214A81;">
                    Stäng
                </button>
                <button id="printButton" type="button" class="btn btn-primary" style="background-color: #214A81; color: #ffffff; border-color: #214A81;">
                    Skriv ut
                </button>
            </div>
        </div>
    </div>
</div>




    <!---------------------------------------------------------------- Frågesvar - POP-UP -->

<div class="modal fade" id="answersModal" tabindex="-1" aria-labelledby="answersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="answersModalLabel">Frågor och svar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="answersModalContent">
                <!-- Frågesvar laddas här -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Stäng</button>
            </div>
        </div>
    </div>
</div>


    <!---------------------------------------------------------------- ÄNDRA GRUPP - POP-UP -->

<!-- Modal för att ändra grupp -->
<div id="changeGroupModal" class="modal fade" tabindex="-1" aria-labelledby="changeGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeGroupModalLabel">Ändra Grupp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Stäng"></button>
            </div>
            <div class="modal-body">
                <label for="modalSelectGroup" class="form-label">Välj ny grupp:</label>
                <input type="text" id="modalSelectGroup" class="form-control" placeholder="Skriv för att söka grupp..." autocomplete="off">
                <ul id="modalGroupSuggestions" class="list-group mt-2" style="display: none;">
                    <!-- Dynamiska gruppförslag -->
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                <button type="button" id="changeGroupConfirm" class="btn btn-primary">Byt</button>
            </div>
        </div>
    </div>
</div>

    <!---------------------------------------------------------------- ÄNDRA TYP - POP-UP -->

<!-- Change Type Modal -->
<div class="modal fade" id="changeTypeModal" tabindex="-1" aria-labelledby="changeTypeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeLabel">Ändra Typ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Stäng"></button>
            </div>
            <div class="modal-body">
                <select id="typeDropdown" class="form-select">
                    <option>INTJ</option>
                    <option>INTP</option>
                    <option>ENTJ</option>
                    <option>ENTP</option>
                    <option>INFJ</option>
                    <option>INFP</option>
                    <option>ENFJ</option>
                    <option>ENFP</option>
                    <option>ISTJ</option>
                    <option>ISFJ</option>
                    <option>ESTJ</option>
                    <option>ESFJ</option>
                    <option>ISTP</option>
                    <option>ISFP</option>
                    <option>ESTP</option>
                    <option>ESFP</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                <button type="button" class="btn btn-primary" id="confirmTypeChange">Byt</button>
            </div>
        </div>
    </div>
</div>

<!---------------------------------------------------------------- DETALJLER?!?!?!?!?!?!?!?!??!?!?!?!?!?! -->

<!-- Bootstrap Modal för detaljer -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-center" id="detailsModalLabel">Detaljerad Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalContent">
        <!-- Dynamiskt innehåll läggs här -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Stäng</button>
      </div>
    </div>
  </div>
</div>
</div> 

<!---------------------------------------------------------------- INBJUDNINGSFORMULÄR -->

<!---------------------------------------------------------------- GRUPPER -->

<!---------------------------------------------------------------- SKRIPTS -->

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>  
  
<script>   

// ---------------------------------------------------------------------------------------SKRIV UT



document.addEventListener("DOMContentLoaded", function () {
    const printButton = document.getElementById("printButton");

    if (printButton) {
        printButton.addEventListener("click", function () {
            const modalContent = document.querySelector(".modal-content");

            if (!modalContent) {
                alert("Modalinnehåll hittades inte.");
                return;
            }

            // Spara originalinnehållet
            const originalContent = document.body.innerHTML;

            // Temporär HTML för utskrift
            document.body.innerHTML = `
                <html>
                <head>
                    <title>Utskrift</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        ${document.querySelector("style").textContent} /* Lägg till inline-stilar */
                        @media print {
                            body {
                                margin: 0;
                                padding: 10mm;
                            }
                            .modal-content {
                                box-shadow: none;
                                border: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="modal-content">
                        ${modalContent.innerHTML}
                    </div>
                </body>
                </html>
            `;

            // Öppna utskriftsdialog
            window.print();

            // Återställ originalinnehållet och ladda om sidan
            document.body.innerHTML = originalContent;
            location.reload(); // Ladda om sidan för att återställa händelser
        });
    }
});






// ---------------------------------------------------------------------------------------SÖK DE 100 SENASTE

document.getElementById('latestButton').addEventListener('click', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('latest', 'true'); // Lägg till eller uppdatera parametern
    url.searchParams.delete('search'); // Ta bort eventuell sökparameter
    window.location.href = url.toString(); // Ladda om sidan med den nya URL:en
});


 </script>
 
</div>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!----------------------------------------------------------------------------------------------------------- Svarsalternativ -->

<script>

document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("answersModal");
    const modalLabel = document.getElementById("answersModalLabel");
    const modalContent = document.getElementById("answersModalContent");

    // Funktion för att hämta och uppdatera modalens innehåll och rubrik
    const fetchAndUpdateModalContent = (url) => {
        modalContent.innerHTML = "<p>Laddar...</p>"; // Visa laddningsindikator

        fetch(url)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.text();
            })
            .then((data) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, "text/html");

                // Hämta rubriken från svaret och uppdatera modalens rubrik
                const newTitle = doc.querySelector("h5.modal-title");
                if (newTitle) {
                    modalLabel.textContent = newTitle.textContent; // Uppdatera rubriken
                }

                // Hämta och sätt modalens innehåll
                const table = doc.querySelector("table");
                if (table) {
                    modalContent.innerHTML = "";
                    modalContent.appendChild(table);
                } else {
                    modalContent.innerHTML = "<p>Inga data att visa.</p>";
                }
            })
            .catch((error) => {
                console.error("Fel vid hämtning av modalens innehåll:", error);
                modalContent.innerHTML = "<p>Ett fel uppstod vid hämtning av innehållet.</p>";
            });
    };

    // Öppna modal och ladda innehåll
    document.querySelectorAll(".answers-btn").forEach((button) => {
        button.addEventListener("click", () => {
            const answerId = button.getAttribute("data-answerid");

            if (!answerId) {
                alert("Ett fel uppstod: AnswerId saknas.");
                return;
            }

            const url = `fetch_answers.php?AnswerId=${encodeURIComponent(answerId)}`;
            modal.setAttribute("data-answerid", answerId); // Spara AnswerId i modalens attribut

            fetchAndUpdateModalContent(url); // Ladda innehåll
            const answersModal = new bootstrap.Modal(modal);
            answersModal.show(); // Visa modal
        });
    });

    // Event delegation för sorteringslänkar
    modalContent.addEventListener("click", (event) => {
        const target = event.target;

        if (target.tagName === "A" && target.classList.contains("sort-link")) {
            event.preventDefault();

            const answerId = modal.getAttribute("data-answerid");
            const sort = target.getAttribute("data-sort");
            const direction = target.getAttribute("data-direction");

            if (!sort || !direction || !answerId) {
                alert("Ett fel uppstod: Ofullständiga parametrar för sortering.");
                return;
            }

            const url = `fetch_answers.php?AnswerId=${encodeURIComponent(answerId)}&sort=${encodeURIComponent(sort)}&direction=${encodeURIComponent(direction)}`;
            fetchAndUpdateModalContent(url); // Uppdatera innehåll
        }
    });
});

    
    
</script>


     <script>    
     
    //-------------------------------------------------------------------------------------------------- DYNAMISK GRUPPSÖK?!
    
    /* document.getElementById('selectGroup').addEventListener('input', function () {
        const query = this.value.trim();
    
        fetch(`?fetch_Dyn_Groups=true&query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                const groupSuggestions = document.getElementById('groupSuggestions');
                groupSuggestions.innerHTML = ''; // Töm tidigare förslag
    
                if (data.success && data.Groups.length > 0) {
                    // Skapa listan med förslag
                    data.Groups.forEach(group => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.textContent = group.GroupName;
                        li.dataset.groupId = group.GroupId;
    
                        li.addEventListener('click', function () {
                            // När användaren klickar på ett förslag
                            document.getElementById('selectGroup').value = group.GroupName;
                            document.getElementById('selectGroup').dataset.groupId = group.GroupId;
                            groupSuggestions.style.display = 'none';
                        });
    
                        groupSuggestions.appendChild(li);
                    });
                    groupSuggestions.style.display = 'block';
                } else {
                    // Om inga resultat hittas
                    groupSuggestions.style.display = 'none'; // Dölj listan
                }
            })
            .catch(error => {
                console.error('Error fetching groups:', error);
            });
    }); */

    
    </script>




<!-- Din JavaScript för klickbara rader -->




<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>




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


    // Open Change Group Modal
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

</script>




<script>

//--------------------------------------------------------------------------------Hämta detaljer!

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".details-btn").forEach(button => {
        button.addEventListener("click", async function () {
            // Hämta data från knappen
            const answerId = this.getAttribute('data-answerid');

            // Uppdatera rubriken i modalen
            const detailName = document.getElementById("detail-name");
            if (detailName) {
                detailName.textContent = this.getAttribute('data-name') || "N/A";
            }

            // Hämta innehållet från result_details.php om AnswerId finns
            const modalContent = document.getElementById("modalContent");
            if (modalContent) {
                if (answerId) {
                    try {
                        const response = await fetch(`https://proanalys.se/Result_details.php?AnswerId=${encodeURIComponent(answerId)}`);
                        if (response.ok) {
                            const htmlContent = await response.text();
                            modalContent.innerHTML = htmlContent; // Sätt innehållet i modalens kropp
                        } else {
                            modalContent.innerHTML = "<p>Det gick inte att hämta detaljerna. Kontrollera AnswerId.</p>";
                        }
                    } catch (error) {
                        modalContent.innerHTML = "<p>Ett fel inträffade vid hämtningen av detaljerna.</p>";
                    }
                } else {
                    modalContent.innerHTML = "<p>Ingen data tillgänglig för det valda ID:t.</p>";
                }
            }

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

