<?php
session_start();
// Aktivera felrapportering
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Databasinställningar
$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

// Anslut till databasen
$conn = new mysqli($servername, $username, $password, $database);

// Kontrollera anslutningen
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

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

// Hantera GET-förfrågan för att hämta inbjudningar
$searchQuery = "";
$includeUsed = isset($_GET['includeUsed']) && $_GET['includeUsed'] === 'true';
$whereClauses = [];
$params = [];
$types = "";

// Bygg sökvillkor
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = "%" . trim($_GET['search']) . "%";
    $whereClauses[] = "(Name LIKE ? OR Email LIKE ? OR GroupName LIKE ? OR InvitedBy LIKE ?)";
    $params = array_fill(0, 4, $searchQuery);
    $types .= "ssss";
}

if (!$includeUsed) {
    $whereClauses[] = "Used = 0";
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Bygg SQL-fråga
$sql = "
    SELECT InviteId, Name, Email, InviteCode, InvitedBy, GroupName, InviteTime, Used, Started, StartedTime
    FROM SurveyInvites
    $whereSQL
    ORDER BY InviteId DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hantera Inbjudningar</title>
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
<body>
<div class="container wider-container mt-2">
    <h3>Hantera inbjudningar</h3>

    <!-- Sökformulär -->
    <form method="get" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Sök efter namn, mejl, grupp eller admin"
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="submit" class="btn btn-primary">Sök</button>
        </div>
        <div class="form-check mt-2">
            <input type="checkbox" name="includeUsed" id="includeUsed" class="form-check-input" value="true" 
                <?php echo $includeUsed ? 'checked' : ''; ?>>
            <label for="includeUsed" class="form-check-label">Inkludera använda</label>
        </div>
    </form>

    <!-- Resultattabell -->
<table class="table table-striped small-text-table styled-table">
    <thead>
    <tr>
        <th>Namn</th>
        <th>Mejl</th>
        <th>Kod</th>
        <th>Admin</th>
        <th>Grupp</th>
        <th>Tid</th>
        <th>Nyttjad</th>
        <th>Start</th> <!-- Ny kolumn -->
        <th>Ta bort</th>
    </tr>
    </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $link = "https://proanalys.se/proanalys?name=" . urlencode($row['Name']) . 
                        "&email=" . urlencode($row['Email']) . 
                        "&code=" . urlencode($row['InviteCode']) . 
                        "&group=" . urlencode($row['GroupName']);
            ?>
                <tr data-link="<?= $link ?>" onclick="copyToClipboard(event, this)" style="cursor: pointer;">
                    <td><?php echo htmlspecialchars($row['Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Email']); ?></td>
                    <td><?php echo htmlspecialchars($row['InviteCode']); ?></td>
                    <td><?php echo htmlspecialchars($row['InvitedBy']); ?></td>
                    <td><?php echo htmlspecialchars($row['GroupName']); ?></td>
                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($row['InviteTime']))); ?></td>
                    <td><?php echo $row['Used'] ? 'Ja' : 'Nej'; ?></td>
                    <td title="<?= htmlspecialchars($row['StartedTime']) ?>">
                        <?php echo htmlspecialchars($row['Started']); ?>
                    </td>
                    <td>
                        <input type="checkbox" class="delete-checkbox" data-id="<?php echo $row['InviteId']; ?>">
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center">Inga resultat att visa.</td>
            </tr>
        <?php endif; ?>
        </tbody>
</table> 

<script>

function copyToClipboard(event, row) {
    // Kolla om klicket skedde på en checkbox eller dess barn
    if (event.target.tagName === 'INPUT' && event.target.type === 'checkbox') {
        return; // Avsluta funktionen om det är en checkbox
    }
    
    const link = row.getAttribute('data-link');
    navigator.clipboard.writeText(link).then(() => {
        alert("Länken har kopierats till urklipp!");
    }).catch(err => {
        console.error("Kunde inte kopiera länken:", err);
    });
}

</script>


    <button id="deleteButton" class="btn btn-danger mt-3">Ta bort markerade</button>
</div>

<script>

      document.getElementById("deleteButton").addEventListener("click", () => {
        const selectedIds = Array.from(document.querySelectorAll(".delete-checkbox:checked"))
            .map(checkbox => checkbox.getAttribute("data-id"));
    
        if (selectedIds.length === 0) {
            alert("Välj minst en inbjudning att ta bort.");
            return;
        }
    
        if (!confirm("Är du säker på att du vill ta bort de valda inbjudningarna?")) {
            return;
        }
    
        fetch("delete_invites.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ deleteIds: JSON.stringify(selectedIds) })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Serverfel");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Uppdatera sidan
                } else {
                    alert(`Fel vid borttagning: ${data.message}`);
                }
            })
            .catch(error => {
                console.error("Fel vid borttagning:", error);
                alert("Ett fel uppstod vid borttagning.");
            });
    });

</script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
