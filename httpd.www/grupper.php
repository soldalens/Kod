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

 //---------------------------------------------------------------- HTML - Inloggningsruta -->


$sql2 = "SELECT UserId, Name FROM Users";
$result2 = $conn->query($sql2);

$users = [];
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $users[] = $row;
    }
}

    // ---------------------------------------------------------------- 16 rutor




    // ---------------------------------------------------------------- Skapa grupp

    // Hantera skapande av grupp
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
        $groupName = trim($_POST['group_name']);

        if (empty($groupName)) {
            echo json_encode(['success' => false, 'message' => 'Gruppnamn kan inte vara tomt.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO Groups (GroupName) VALUES (?)");
        $stmt->bind_param('s', $groupName);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Gruppen skapades framgångsrikt.', 'GroupId' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kunde inte skapa gruppen. Gruppnamnet måste vara unikt.']);
        }

        $stmt->close();
        exit;
    }
    
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

// Hantera borttagning av grupper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_Groups'])) {
    $groupIds = json_decode($_POST['group_ids'], true);

    if (empty($groupIds) || !is_array($groupIds)) {
        echo json_encode(['success' => false, 'message' => 'Inga grupper valda för borttagning.']);
        exit;
    }

    // Kontrollera om grupperna används i SurveyHeaders
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $stmt = $conn->prepare("SELECT DISTINCT GroupName FROM Groups WHERE GroupId IN ($placeholders)");
    $types = str_repeat('i', count($groupIds));
    $stmt->bind_param($types, ...$groupIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $groupNames = [];
    while ($row = $result->fetch_assoc()) {
        $groupNames[] = $row['GroupName'];
    }
    $stmt->close();

    if (!empty($groupNames)) {
        $placeholders = implode(',', array_fill(0, count($groupNames), '?'));
        $stmt = $conn->prepare("SELECT GroupName FROM SurveyHeaders WHERE GroupName IN ($placeholders)");
        $types = str_repeat('s', count($groupNames));
        $stmt->bind_param($types, ...$groupNames);
        $stmt->execute();
        $result = $stmt->get_result();

        $usedGroups = [];
        while ($row = $result->fetch_assoc()) {
            $usedGroups[] = $row['GroupName'];
        }
        $stmt->close();

        // Ta bort dubbletter från $usedGroups
        $usedGroups = array_unique($usedGroups);

        if (!empty($usedGroups)) {
            echo json_encode([
                'success' => false,
                'message' => 'Vissa grupper kan inte tas bort eftersom du har analysresultat knutna till grupperna. Flytta gärna analysresultaten från dessa grupper först: ' . implode(', ', $usedGroups),
            ]);
            exit;
        }
    }

    // Om ingen grupp används, fortsätt med borttagning
    $stmt = $conn->prepare("DELETE FROM Groups WHERE GroupId IN ($placeholders)");
    $stmt->bind_param($types, ...$groupIds);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Markerade grupper togs bort.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort markerade grupper.']);
    }

    $stmt->close();
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

<!---------------------------------------------------------------- GRUPPER -->

    <div class="container wider-container mt-2">
        <h3>Hantera grupper</h3>
        <!-- Form för att skapa en ny grupp -->
        <div class="mb-3">
            <form id="createGroupForm">

                <div class="input-group">
                    <input type="text" id="groupName" class="form-control" placeholder="Ange gruppnamn" required>
                    <button type="submit" class="btn btn-primary">Lägg till grupp</button>
                </div>
            </form>
        </div>
    
        <!-- Lista över grupper -->
        <div>
             <table class="table table-striped small-text-table styled-table">
                    <thead>
                        <tr>
                            <th>Gruppnamn</th>
                            <th>Välj</th>
                        </tr>
                    </thead>
                    <tbody id="groupTableBody">
                        <!-- Dynamiska grupper kommer att fyllas här -->
                    </tbody>
                </table>
            <div class="d-flex justify-content-between">
                <button type="button" id="deleteSelectedGroups" class="btn btn-danger mt-3">Ta bort markerade grupper</button>
                <button type="button" id="showGroupOutcome" class="btn btn-primary">Visa Grupputfall</button>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="mbtiModal" tabindex="-1" aria-labelledby="mbtiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="print-header">
                    PeMa - Perfect Match - Personlighetsanalys
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="mbtiModalLabel">Grupputfall</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Stäng"></button>
                </div>
                <div class="modal-body">
                    <div id="mbtiGrid" class="row">
                        <!-- Här genereras 16 rutor dynamiskt -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="printModal" class="btn btn-primary">Skriv ut</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Stäng</button>
                </div>
                <div class="print-footer">
                    https://verkningsgrad.se/tjanster/personlighetsanalys
                </div>
            </div>
        </div>
    </div>



    
<!---------------------------------------------------------------- SKRIPTS -->
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

#mbtiGrid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.mbti-box {
    flex: 1 0 calc(25% - 10px); /* 4 rutor per rad med mellanrum */
    min-height: 150px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: #f9f9f9;
    text-align: center;
    padding: 10px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.mbti-box strong {
    font-size: 1.2em;
    margin-bottom: 10px;
}

.mbti-box p {
    margin: 0;
}

.mbti-box p span {
    font-weight: bold;
}

.mbti-box p span.green {
    color: green;
}

.mbti-box p span.red {
    color: red;
}

@media print {
    body {
        color: #000;
    }

    @page {
        size: A4 portrait;
        margin: 20mm;
    }

@media print {
    body:before {
        content: "PeMa - Perfect Match - Personlighetsanalys" !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 30px !important;
        background-color: #ffffff !important;
        color: #002060 !important;
        text-align: center !important;
        font-size: 12px !important;
        line-height: 30px !important;
        z-index: 9999 !important;
    }

    body:after {
        content: "https://verkningsgrad.se/tjanster/personlighetsanalys" !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 20px !important;
        background-color: #ffffff !important;
        color: #002060 !important;
        text-align: center !important;
        font-size: 12px !important;
        line-height: 20px !important;
        z-index: 9999 !important;
    }

    .print-content {
        margin-top: 50px;
        margin-bottom: 30px;
    }
}


    .avoid-page-break {
        page-break-inside: avoid;
    }

    .mbti-box p span.green {
        color: green !important;
        font-weight: bold;
    }

    .mbti-box p span.red {
        color: red !important;
        font-weight: bold;
    }

    #mbtiGrid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin: 0 auto;
    }

    .mbti-box {
        height: 200px;
        border: 1px solid #000;
        background-color: #fff;
        text-align: center;
        padding: 10px;
        box-sizing: border-box;
        page-break-inside: avoid;
    }
}

    </style>

  <script>   
  
   //--------------------------------------------------------------------------------------------------16 rutor
   
const mbtiBoxes = document.querySelectorAll(".mbti-box");
mbtiBoxes.forEach((box) => {
    const numberOfNames = box.querySelectorAll("p").length;
    if (numberOfNames > 4) {
        box.style.height = `${150 + (numberOfNames - 4) * 20}px`; // Lägg till höjd för varje extra namn
    }
});   

  
document.getElementById("showGroupOutcome").addEventListener("click", () => {
    const selectedGroups = Array.from(document.querySelectorAll(".group-checkbox:checked")).map(
        (checkbox) => {
            const groupName = checkbox.closest("tr").querySelector("td:first-child").textContent.trim();
            return { id: parseInt(checkbox.dataset.id, 10), name: groupName };
        }
    );

    console.log("Selected Groups:", selectedGroups);

    if (selectedGroups.length === 0) {
        alert("Välj minst en grupp för att visa utfallet.");
        return;
    }

    // Använd första gruppens namn för rubriken
    const groupNameForTitle = selectedGroups[0].name;
    document.getElementById("mbtiModalLabel").textContent = `Grupputfall - ${groupNameForTitle}`;

    // Skicka GroupId till backend
    const selectedGroupIds = selectedGroups.map(group => group.id);
    fetch("get_PeMa_data.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ groupIds: selectedGroupIds }),
    })
        .then((response) => {
            console.log("Response Status:", response.status);
            return response.text(); // Använd text() för felsökning
        })
        .then((text) => {
            console.log("Raw Response Text:", text); // Kontrollera svaret från backend
            return JSON.parse(text); // Försök att parsa JSON manuellt
        })
        .then((data) => {
            console.log("Parsed Data:", data);
            if (data.success) {
                renderMBTIChart(data.data);
                const modal = new bootstrap.Modal(document.getElementById("mbtiModal"));
                modal.show();
            } else {
                alert(data.message || "Ett fel inträffade.");
            }
        })
        .catch((error) => {
            console.error("Error fetching group data:", error);
        });
});


function renderMBTIChart(data) {
    const grid = document.getElementById("mbtiGrid");

    // Rensa tidigare innehåll
    grid.innerHTML = "";

    // MBTI-rutor (layout)
    const types = [
        "ISTJ", "ISFJ", "INFJ", "INTJ",
        "ISTP", "ISFP", "INFP", "INTP",
        "ESTP", "ESFP", "ENFP", "ENTP",
        "ESTJ", "ESFJ", "ENFJ", "ENTJ"
    ];

    types.forEach((type) => {
        // Skapa rutan
        const cell = document.createElement("div");
        cell.classList.add("mbti-box");
        cell.dataset.type = type;

        // Lägg till personlighetstyp som rubrik
        const typeTitle = document.createElement("strong");
        typeTitle.textContent = type;
        cell.appendChild(typeTitle);

        // Filtrera data för denna personlighetstyp
        const matchingData = data.filter(
            (item) =>
                item.PersonalityType === type || item.CorrectedPersonalityType === type
        );

        // Lägg till personer för denna typ
        matchingData.forEach((item) => {
            const nameElement = document.createElement("p");

            if (!item.CorrectedPersonalityType) {
                // Ingen CorrectedPersonalityType -> Grön färg
                nameElement.innerHTML = `<span class="green">${item.firstName} ${item.lastInitial}</span>`;
            } else if (item.CorrectedPersonalityType === type) {
                // CorrectedPersonalityType matchar rutan -> Grön färg
                nameElement.innerHTML = `<span class="green">${item.firstName} ${item.lastInitial}</span>`;
            } else {
                // CorrectedPersonalityType finns men matchar inte -> Röd färg
                nameElement.innerHTML = `<span class="red">${item.firstName} ${item.lastInitial}</span>`;
            }

            cell.appendChild(nameElement);
        });

        // Lägg till rutan i grid
        grid.appendChild(cell);
    });
}

    //--------------------------------------------------------------------------------------------------Skriv ut
  
document.getElementById("printModal").addEventListener("click", () => {
    const originalContent = document.body.innerHTML; // Spara originalinnehållet
    const gridContent = document.getElementById("mbtiGrid").outerHTML; // Hämta rutnätets innehåll
    
    const selectedGroups = Array.from(document.querySelectorAll(".group-checkbox:checked")).map(
    (checkbox) => checkbox.closest("tr").querySelector("td:first-child").textContent.trim()
    );
    const groupName = selectedGroups.length > 0 ? selectedGroups[0] : "Okänd Grupp";

    // Temporär HTML för utskrift
    document.body.innerHTML = `
        <html>
        <head>
            <br>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    color: #000;
                }
                @media print {
                    body:before {
                        content: "PeMa - Perfect Match - Personlighetsanalys";
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        height: 30px;
                        background-color: #ffffff;
                        color: #002060;
                        text-align: center;
                        font-size: 12px;
                        line-height: 30px;
                        z-index: 9999;
                    }
                    body:after {
                        content: "https://verkningsgrad.se/tjanster/personlighetsanalys";
                        position: fixed;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        height: 20px;
                        background-color: #ffffff;
                        color: #002060;
                        text-align: center;
                        font-size: 12px;
                        line-height: 20px;
                        z-index: 9999;
                    }
                    .print-content {
                        margin-top: 50px;
                        margin-bottom: 30px;
                    }
                }
                #mbtiGrid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 10px;
                    width: 100%;
                    margin: 0 auto;
                    page-break-inside: avoid;
                }
                .mbti-box {
                    height: 200px;
                    border: 1px solid #000;
                    background-color: #fff;
                    text-align: center;
                    padding: 10px;
                    box-sizing: border-box;
                    page-break-inside: avoid;
                }
                .mbti-box strong {
                    font-size: 1.2em;
                    margin-bottom: 10px;
                }
                .mbti-box p {
                    margin: 0;
                    font-size: 0.9em;
                    word-wrap: break-word;
                }
                .mbti-box p span.green {
                    color: green !important;
                    font-weight: bold;
                }
                .mbti-box p span.red {
                    color: red !important;
                    font-weight: bold;
                }
                @page {
                    size: A4 portrait;
                    margin: 1cm;
                }
            </style>
        </head>
        <body>
            <br>
            <h2 style="text-align: center;">Grupputfall - ${groupName}</h2>
            <br>
            ${gridContent}
        </body>
        </html>
    `;

    // Öppna utskriftsdialog
    window.print();

    // Återställ originalinnehållet efter utskrift
    document.body.innerHTML = originalContent;
    location.reload(); // Ladda om sidan för att återställa händelser
});

  
  
    //--------------------------------------------------------------------------------------------------Skapa en ny grupp
    document.getElementById('createGroupForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const groupName = document.getElementById('groupName').value.trim();
    
        if (!groupName) {
            alert("Gruppnamn kan inte vara tomt!");
            return;
        }
    
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `create_group=true&group_name=${encodeURIComponent(groupName)}`,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    addGroupToTable(data.GroupId, groupName);
                    document.getElementById('groupName').value = ''; // Rensa fältet
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    });
    
    //--------------------------------------------------------------------------------------------------Lägg till en ny grupp
    
    // Lägg till en grupp i trädstrukturen
        function addGroupToTable(groupId, groupName) {
            const groupTableBody = document.getElementById('groupTableBody'); // Gruppens tabellkropp
            const row = document.createElement('tr'); // Skapa en ny rad
        
            // Lägg till HTML-innehåll för raden
            row.innerHTML = `
                <td>${groupName}</td>
                <td>
                    <input type="checkbox" class="form-check-input group-checkbox" data-id="${groupId}">
                </td>
            `;
            row.dataset.groupId = groupId; // Sätt ett data-attribut för grupp-ID (om det behövs senare)
            
            // Lägg till raden i tabellen
            groupTableBody.appendChild(row);
        }
    
        //--------------------------------------------------------------------------------------------------Hämta alla grupper från servern och lägg till i trädet
        document.addEventListener('DOMContentLoaded', function () {
            // Hämta grupper från servern
            fetch('?fetch_Groups=true')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const groupTableBody = document.getElementById('groupTableBody');
                        groupTableBody.innerHTML = ''; // Töm tidigare innehåll
        
                        // Lägg till varje grupp som en ny rad i tabellen
                        data.Groups.forEach(group => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${group.GroupName}</td>
                                <td>
                                    <input type="checkbox" class="form-check-input group-checkbox" data-id="${group.GroupId}">
                                </td>
                            `;
                            groupTableBody.appendChild(row);
                        });
                    } else {
                        alert('Kunde inte hämta grupper: ' + data.message);
                    }
                })
                .catch(error => console.error('Error fetching groups:', error));
        });

    
        //--------------------------------------------------------------------------------------------------Ta bort markerade grupper

        document.getElementById('deleteSelectedGroups').addEventListener('click', function () {
            const selectedCheckboxes = document.querySelectorAll('.group-checkbox:checked');
            const groupIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.dataset.id);
        
            if (groupIds.length === 0) {
                alert('Inga grupper valda för borttagning.');
                return;
            }
        
            // Skicka en begäran till servern för att ta bort valda grupper
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `delete_Groups=true&group_ids=${encodeURIComponent(JSON.stringify(groupIds))}`,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Ta bort de markerade raderna från tabellen
                        selectedCheckboxes.forEach(checkbox => checkbox.closest('tr').remove());
                    } else {
                        alert('Kunde inte ta bort grupper: ' + data.message);
                    }
                })
                .catch(error => console.error('Error deleting groups:', error));
        });
        

 </script>
 
</div>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

   //-------------------------------------------------------------------------------------------------- VÄLJ EN RAD


   //-------------------------------------------------------------------------------------------------- TA BORT RAD


   //-------------------------------------------------------------------------------------------------- VISA DETALJER - RÄTT KOD?!?!?
   





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
