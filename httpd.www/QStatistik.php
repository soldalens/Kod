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

// Logga ut
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_unset();
    session_destroy();
    header("Location: Qstatistik.php");
    exit();
}

// ---------------------------------------------------------------- Statistik och filtreringssida
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9; /* Ljus bakgrund för bättre kontrast */
            color: #333; /* Textfärg */
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 28px;
            color: #214A81; /* Mörkblå rubrikfärg */
            font-weight: bold;
            text-align: left;
            margin-bottom: 20px;
        }

        .container {
            max-width: 90%;
            margin: 20px auto;
            background-color: #ffffff; /* Vit bakgrund för behållare */
            padding: 20px;
            border-radius: 10px; /* Rundade hörn */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Subtil skugga */
        }

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .filter-container select {
            height: 150px; /* Höjd för dropdown-menyer */
            font-size: 13px; /* Mindre fontstorlek */
            color: #333;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: bold;
            color: #214A81; /* Mörkblå för tydliga etiketter */
            margin-bottom: 5px;
            display: block;
        }

        #resetFilters {
            margin-top: 20px;
            background-color: #214A81; /* Samma som övriga knappar */
            color: #ffffff; 
            font-size: 13px; 
            font-weight: normal;
            border: none; 
            border-radius: 5px; 
            padding: 7px 12px; /* Samma padding som andra knappar */
            transition: background-color 0.3s;
            margin-right: auto; /* Flytta till vänster */
        }

        #resetFilters:hover {
            background-color: #214A81; /* Ljusare hover-effekt */
        }

        .btn-group .btn {
            margin-top: 20px;
            background-color: #214A81;
            color: #ffffff;
            font-size: 13px;
            border: none;
            border-radius: 5px;
            padding: 7px 12px; /* Knappens inre avstånd */
            margin-right: 3px; /* Mellanrum mellan knappar */
            transition: background-color 0.3s;
        }

        .btn-group .btn:hover {
            background-color: #163A5F; /* Mörkare hover-färg */
        }

        th {
            background-color: #214A81;
            color: #ffffff;
            font-size: 13px;
            text-align: left;
            padding: 10px;
        }

        th[data-sort]:hover {
            background-color: #163A5F; /* Lägger till hover-effekt för sorterbara rubriker */
            cursor: pointer;
        }

        .table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden; /* För rundade hörn */
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9; /* Ljus bakgrund för jämna rader */
        }

        .table tbody tr:nth-child(odd) {
            background-color: #e9e9e9; /* Ljusare grå för udda rader */
        }

        .table tbody td {
            padding: 3px;
            font-size: 13px;
            color: #333;
            border: 1px solid #ddd;
        }

        .table tbody tr:hover {
            background-color: #d9e6f2; /* Hover-effekt på rader */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Statistik</h1>
        <form id="filterForm" class="mb-3">
            <div class="filter-container">
                <!-- Dropdown-menygrupper -->
                <div class="filter-group">
                    <label for="personalityType">Typ:</label>
                    <select id="personalityType" name="personalityType[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="name">Namn:</label>
                    <select id="name" name="name[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="education">Utbildning:</label>
                    <select id="education" name="education[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="group">Grupp:</label>
                    <select id="group" name="group[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="gender">Kön:</label>
                    <select id="gender" name="gender[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="age">Ålder:</label>
                    <select id="age" name="age[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="orderNumber">Ordernummer:</label>
                    <select id="orderNumber" name="orderNumber[]" class="form-select" multiple></select>
                </div>
                <div class="filter-group">
                    <label for="responseDescr">Svar:</label>
                    <select id="responseDescr" name="responseDescr[]" class="form-select" multiple></select>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" id="resetFilters" class="btn">Rensa filter</button>
                <div class="btn-group">
                    <button type="button" class="btn view-selector" data-view="question">Per fråga</button>
                    <button type="button" class="btn view-selector" data-view="personality">Per personlighetstyp</button>
                    <button type="button" class="btn view-selector" data-view="response">Per svar</button>
                </div>
            </div>   
        </form>

        <table class="table table-bordered" id="statsTable">
            <thead>
                <tr>
                    <th data-sort="OrderNumber">#</th>
                    <th data-sort="QuestionTextSnippet">Fråga</th>
                    <th data-sort="AvgTimeDifference">Sn. Tid</th>
                    <th data-sort="StdDevTimeDifference">Std.Av.</th>
                    <th data-sort="CountTimeDifference">Antal</th>
                    <th data-sort="Outliers">>Tid</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>



<script>
$(document).ready(function () {
    let filterTimeout;
    let sortColumn = "OrderNumber"; // Standardkolumn att sortera
    let sortOrder = "ASC"; // Standardriktning
    let currentView = "question"; // Standardvy (Per fråga)

    // Funktion för att hämta och fylla filteralternativ
    function fetchFilters() {
        $.ajax({
            url: "fetch_filters.php",
            method: "GET",
            data: $("#filterForm").serialize(),
            dataType: "json", // Förvänta JSON-svar
            success: function (filters) {
                const currentValues = {
                    personalityType: $("#personalityType").val(),
                    name: $("#name").val(),
                    education: $("#education").val(),
                    group: $("#group").val(),
                    gender: $("#gender").val(),
                    age: $("#age").val(),
                    orderNumber: $("#orderNumber").val(),
                    responseDescr: $("#responseDescr").val()
                };

                // Uppdatera dropdown-menyer
                $("#personalityType").html(filters.personalityType || "").val(currentValues.personalityType);
                $("#name").html(filters.name || "").val(currentValues.name);
                $("#education").html(filters.education || "").val(currentValues.education);
                $("#group").html(filters.group || "").val(currentValues.group);
                $("#gender").html(filters.gender || "").val(currentValues.gender);
                $("#age").html(filters.age || "").val(currentValues.age);
                $("#orderNumber").html(filters.orderNumber || "").val(currentValues.orderNumber);
                $("#responseDescr").html(filters.responseDescr || "").val(currentValues.responseDescr);
            },
            error: function (xhr, status, error) {
                console.error("Fel vid hämtning av filter:", xhr.responseText || error);
            }
        });
    }

    // Funktion för att hämta statistik och uppdatera tabellen
    function fetchStats() {
        $.ajax({
            url: "fetch_stats.php",
            method: "GET",
            data: $("#filterForm").serialize() + `&sortColumn=${sortColumn}&sortOrder=${sortOrder}&view=${currentView}`,
            dataType: "json", // Förvänta JSON-svar
            success: function (response) {
                if (response.rows) {
                    let headers = "";

                    // Uppdatera tabellrubriker baserat på aktuell vy
                    if (currentView === "question") {
                        headers = `
                            <tr>
                                <th data-sort="OrderNumber">#</th>
                                <th data-sort="QuestionTextSnippet">Fråga</th>
                                <th data-sort="AnswerTextSnippet">Svar</th>
                                <th data-sort="AvgTimeDifference">Sn. Tid</th>
                                <th data-sort="StdDevTimeDifference">Std.Av.</th>
                                <th data-sort="CountTimeDifference">Antal</th>
                                <th data-sort="Outliers">>Tid</th>
                            </tr>`;
                    } else if (currentView === "personality") {
                        headers = `
                            <tr>
                                <th data-sort="PersonalityType">Personlighetstyp</th>
                                <th data-sort="AvgTimeDifference">Sn. Tid</th>
                                <th data-sort="StdDevTimeDifference">Std.Av.</th>
                                <th data-sort="CountTimeDifference">Antal</th>
                                <th data-sort="Outliers">>Tid</th>
                            </tr>`;
                    } else if (currentView === "response") {
                        headers = `
                            <tr>
                                <th data-sort="ResponseDescr">Svar</th>
                                <th data-sort="AvgTimeDifference">Sn. Tid</th>
                                <th data-sort="StdDevTimeDifference">Std.Av.</th>
                                <th data-sort="CountTimeDifference">Antal</th>
                                <th data-sort="Outliers">>Tid</th>
                            </tr>`;
                    }

                    $("#statsTable thead").html(headers);

                    // Rendera rader
                    const rowsHtml = response.rows
                        .map(row => {
                            return `<tr>${Object.values(row)
                                .map(value => `<td>${value}</td>`)
                                .join("")}</tr>`;
                        })
                        .join("");

                    $("#statsTable tbody").html(rowsHtml || "<tr><td colspan='6'>Inga data att visa.</td></tr>");
                } else {
                    console.warn("Ogiltigt JSON-svar: rows saknas", response);
                    $("#statsTable tbody").html("<tr><td colspan='6'>Inga data att visa.</td></tr>");
                }
            },
            error: function (xhr, status, error) {
                console.error("Fel vid hämtning av statistik:", xhr.responseText || error);
                $("#statsTable tbody").html("<tr><td colspan='6'>Fel vid hämtning av data.</td></tr>");
            }
        });
    }

    // Hantera vy-knappklick
    $(".view-selector").click(function () {
        currentView = $(this).data("view");
        $(".view-selector").removeClass("btn-success").addClass("btn-primary");
        $(this).removeClass("btn-primary").addClass("btn-success");
        fetchStats();
    });

    // Sortera tabellen
    $("#statsTable").on("click", "th[data-sort]", function () {
        const clickedColumn = $(this).data("sort");
        sortOrder = clickedColumn === sortColumn && sortOrder === "ASC" ? "DESC" : "ASC";
        sortColumn = clickedColumn;
        fetchStats();
    });

    // Initiera hämtning av filter och statistik vid sidladdning
    fetchFilters();
    fetchStats();

    // Uppdatera vid filterändring
    $("#filterForm select").change(function () {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            fetchFilters();
            fetchStats();
        }, 2000);
    });

    // Rensa filter
    $("#resetFilters").click(function () {
        $("#filterForm select").val([]);
        fetchFilters();
        fetchStats();
    });
});
</script>


</body>
</html>
