<?php
session_start();

require 'db_connect.php';

// Inloggningslogik
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = trim($_POST['userid']);
        $password = trim($_POST['password']);

        // Hämta användare och lösenordshash från databasen
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

    // Visa inloggningsformulär om användaren inte är inloggad
    ?>
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
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrera Frågor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

   <style>
        /* Body och grundläggande stil */
        body {
            font-family: Arial, sans-serif;
            background-color: #214A81; /* Mörkblå bakgrund */
            color: #F2F2F2; /* Ljus text */
            margin: 0;
            padding: 0;
        }

        /* Container för innehållet */
        .container {
            background-color: #163A5F; /* Lite ljusare blå för innehållet */
            padding: 20px;
            border-radius: 8px; /* Rundade hörn */
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); /* Skugga */
            margin-top: 20px;
        }

        /* Rubrik */
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #FCD169; /* Guldliknande färg för rubriken */
        }

        /* Knappar */
        .btn-primary {
            background-color: #214A81; /* Anpassad bakgrundsfärg */
            border-color: #214A81; /* Anpassad kantfärg */
            color: #F2F2F2; /* Ljus text */
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #163A5F; /* Ljusare hover */
            border-color: #163A5F;
        }

        /* Formulär */
        .form-label {
            font-weight: bold;
        }

        /* Tabell */
        table {
            background-color: #214A81; /* Blå bakgrund för tabellen */
            border-radius: 8px;
            color: #F2F2F2;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #102A47; /* Kantfärg */
        }

        table tbody tr:nth-child(even) {
            background-color: #163A5F; /* Ljusare blå för jämna rader */
        }

        table tbody tr:nth-child(odd) {
            background-color: #214A81; /* Standard blå för udda rader */
        }

        /* Stil för knappar i navigationen */
        .navbar {
            padding: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;

        }

        .navbar .btn {
            color: #F2F2F2 !important;
            background-color: #214A81 !important;
            border-color: #214A81 !important;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 8px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .navbar .btn:hover {
            background-color: #163A5F !important;
            border-color: #163A5F !important;
        }
        /* Gör textrutan dynamiskt bredare och högre */
        textarea {
            width: 100%; /* Full bredd */
            min-height: 50px; /* Standard höjd */
            resize: both; /* Tillåter ändring av storlek både horisontellt och vertikalt */
            overflow: auto; /* Visar scrollbars om innehållet blir för stort */
            box-sizing: border-box; /* Gör att padding inkluderas i bredd/höjd */
        }


    </style>

</head>
<body>
    
    <!-- Navigationsknappar -->
 

        <button class="btn btn-primary mb-3" onclick="showAddQuestionForm()">Lägg till fråga</button>

        <!-- Formulär för att lägga till/redigera frågor -->
        <div id="questionForm" class="card mb-3" style="display: none;">
            <div class="card-body">
                <h5 id="formTitle">Lägg till ny fråga</h5>
                <form id="addQuestionForm">
                    <div class="mb-3">
                        <label for="questionText" class="form-label">Frågetext</label>
                        <textarea id="questionText" class="form-control" rows="3" placeholder="Ange frågetext, använd [vän] som placeholder"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="questionValue" class="form-label">Poäng</label>
                        <input type="number" id="questionValue" class="form-control" placeholder="Ange poäng för frågan">
                    </div>
                    <div class="mb-3">
                        <label for="orderNumber" class="form-label">Frågans ordning</label>
                        <input type="number" id="orderNumber" class="form-control" placeholder="Ange frågans ordning">
                    </div>
                    <div class="mb-3">
                        <label for="answers" class="form-label">Svarsalternativ</label>
                        <div id="answers">
                            <!-- Dynamiska svarsalternativ -->
                        </div>
                        <button type="button" class="btn btn-secondary mt-2" onclick="addAnswer()">Lägg till svarsalternativ</button>
                    </div>
                    <button type="button" class="btn btn-success" onclick="saveQuestion()">Spara fråga</button>
                    <button type="button" class="btn btn-danger" onclick="hideQuestionForm()">Avbryt</button>
                </form>
            </div>
        </div>

        <!-- Lista över frågor -->
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Frågetext</th>
                    <th>Poäng</th>
                    <th>Ordning</th>
                    <th>Svarsalternativ</th>
                    <th>Åtgärder</th>
                </tr>
            </thead>
            <tbody id="questionsTable"></tbody>
        </table>


    <script>
        let questions = []; // Frågor hämtas här

        function fetchQuestions() {
            fetch('admin_fetch_questions.php')
                .then(response => response.json())
                .then(data => {
                    // Konvertera isActive till ett nummer
                    questions = data.map(q => ({
                        ...q,
                        isActive: parseInt(q.isActive, 10), // Se till att isActive är ett nummer
                    }));
                    console.log('Fetched questions:', questions); // Debug-logg
                    renderQuestions(); // Rendera tabellen igen
                })
                .catch(error => {
                    console.error('Error fetching questions:', error);
                });
        }

        // Visa frågor i tabellen
        
        function renderQuestions() {
            const table = document.getElementById('questionsTable');
            table.innerHTML = ''; // Rensa tabellen
        
            const totalQuestions = questions.length; // Antal frågor
        
            questions
                .sort((a, b) => a.order - b.order) // Sortera frågor efter OrderNumber
                .forEach((q, index) => {
                    console.log(`Rendering question: ID=${q.id}, Text=${q.text}, Order=${q.order}, Active=${q.isActive}`); // Debug-logg
                    console.log(`Question ID=${q.id}, isActive=${q.isActive}, Button Text=${q.isActive === 1 ? 'Inaktivera' : 'Aktivera'}`);
        
                    const answers = q.answers.map(a => `${a.text} (${a.mbti_trait})`).join('<br>');
        
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${q.text}</td>
                            <td>${q.value}</td>
                            <td>
                                <select class="form-select form-select-sm" 
                                        onchange="updateOrderNumber(${q.id}, this.value)">
                                    ${Array.from({ length: totalQuestions }, (_, i) => {
                                        const order = i + 1; // Skapa dropdown med värden från 1 till [antal frågor]
                                        return `<option value="${order}" ${
                                            parseInt(order, 10) === parseInt(q.order, 10) ? 'selected' : ''
                                        }>${order}</option>`;
                                    }).join('')}
                                </select>
                            </td>
                            <td>${answers}</td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editQuestion(${q.id})">Redigera</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteQuestion(${q.id})">Radera</button>
                                <button class="btn btn-${q.isActive === 1 ? 'secondary' : 'success'} btn-sm" 
                                        onclick="toggleActive(${q.id}, ${q.isActive === 1 ? 0 : 1})">
                                    ${q.isActive === 1 ? 'Inaktivera' : 'Aktivera'}
                                </button>
                            </td>
                        </tr>
                    `;
                    table.innerHTML += row;
                });
        }





        // Visa formuläret för att lägga till/redigera frågor
        function showAddQuestionForm() {
            document.getElementById('questionForm').style.display = 'block';
            document.getElementById('formTitle').innerText = 'Lägg till ny fråga';
            document.getElementById('addQuestionForm').reset();
            document.getElementById('answers').innerHTML = '';
        }

        // Dölj formuläret
        function hideQuestionForm() {
            document.getElementById('questionForm').style.display = 'none';
        }

        // Lägg till nytt svarsalternativ
        function addAnswer() {
            const answersDiv = document.getElementById('answers');
            const index = answersDiv.children.length; // Antal nuvarande svarsalternativ
            const answerHtml = `
                <div class="input-group mb-2" data-answer-index="${index}">
                    <input type="text" class="form-control" placeholder="Svarsalternativ text [vän]">
                    <select class="form-select">
                        <option value="1">Extroversion</option>
                        <option value="2">Introversion</option>
                        <option value="3">Sensing</option>
                        <option value="4">Intuition</option>
                        <option value="5">Thinking</option>
                        <option value="6">Feeling</option>
                        <option value="7">Judging</option>
                        <option value="8">Perceiving</option>
                    </select>
                    <select class="form-select ms-2" style="width: 120px;">  
                        <option value="0.8">0.8</option>
                        <option value="1.0" selected>1.0</option>
                        <option value="1.2">1.2</option>
                        <option value="1.4">1.4</option>
                        <option value="1.6">1.6</option>
                        <option value="1.8">1.8</option>
                        <option value="2.0">2.0</option>
                        <option value="2.2">2.2</option>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm ms-2" onclick="removeAnswer(${index})">Ta bort svarsalternativ</button>
                </div>
            `;
            answersDiv.innerHTML += answerHtml;
        }


        // Spara fråga
        function saveQuestion() {
            const questionText = document.getElementById('questionText').value;
            const questionValue = document.getElementById('questionValue').value;
            const orderNumber = document.getElementById('orderNumber').value;
            const answers = Array.from(document.getElementById('answers').children).map(answerDiv => {
                return {
                    text: answerDiv.querySelector('input').value,
                    response_id: answerDiv.querySelector('select').value,
                    multiplier: answerDiv.querySelector('select:nth-of-type(2)').value
                };
            });

            fetch('admin_save_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text: questionText,
                    value: questionValue,
                    order: orderNumber,
                    answers: answers
                })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      fetchQuestions(); // Uppdatera listan
                      hideQuestionForm();
                  } else {
                      alert('Ett fel uppstod vid sparandet av frågan!');
                  }
              });
        }

        // Initial hämtningskörning
        fetchQuestions();
    </script>
</body>
</html>

<script>

function editQuestion(questionID) {
    questionID = String(questionID); // Konvertera ID till sträng
    const question = questions.find(q => String(q.id) === questionID);
    const orderNumber = question.order

    if (!question) {
        alert('Frågan kunde inte hittas');
        console.log('Available questions:', questions);
        return;
    }

    console.log('Found question:', question);

    // Fyll i Frågetext
    document.getElementById('questionText').value = question.text;

    // Fyll i övriga fält
    document.getElementById('questionValue').value = question.value;
    document.getElementById('orderNumber').value = question.order;
    document.getElementById('answers').innerHTML = '';

    // Lägg till svarsalternativ
    question.answers.forEach((answer, index) => {
        const answerHtml = `
            <div class="input-group mb-2" data-answer-index="${index}">
                <textarea 
                    class="form-control" 
                    placeholder="Svarsalternativ text [vän]">${answer.text}</textarea>
                <select 
                    class="form-select" 
                    style="width: 120px; font-size: 14px;">
                    <option value="1" ${answer.response_id == 1 ? 'selected' : ''}>Extroversion</option>
                    <option value="2" ${answer.response_id == 2 ? 'selected' : ''}>Introversion</option>
                    <option value="3" ${answer.response_id == 3 ? 'selected' : ''}>Sensing</option>
                    <option value="4" ${answer.response_id == 4 ? 'selected' : ''}>Intuition</option>
                    <option value="5" ${answer.response_id == 5 ? 'selected' : ''}>Thinking</option>
                    <option value="6" ${answer.response_id == 6 ? 'selected' : ''}>Feeling</option>
                    <option value="7" ${answer.response_id == 7 ? 'selected' : ''}>Judging</option>
                    <option value="8" ${answer.response_id == 8 ? 'selected' : ''}>Perceiving</option>
                </select>
                <select 
                    class="form-select ms-2" 
                    style="width: 120px;">
                    <option value="0.8" ${answer.multiplier == 0.8 ? 'selected' : ''}>0.8</option>
                    <option value="1.0" ${answer.multiplier == 1.0 ? 'selected' : ''}>1.0</option>
                    <option value="1.2" ${answer.multiplier == 1.2 ? 'selected' : ''}>1.2</option>
                    <option value="1.4" ${answer.multiplier == 1.4 ? 'selected' : ''}>1.4</option>
                    <option value="1.6" ${answer.multiplier == 1.6 ? 'selected' : ''}>1.6</option>
                    <option value="1.8" ${answer.multiplier == 1.8 ? 'selected' : ''}>1.8</option>
                    <option value="2.0" ${answer.multiplier == 2.0 ? 'selected' : ''}>2.0</option>
                    <option value="2.2" ${answer.multiplier == 2.2 ? 'selected' : ''}>2.2</option>
                </select>
                <button 
                    type="button" 
                    class="btn btn-danger btn-sm ms-2" 
                    onclick="removeAnswer(${index})">
                    Ta bort svarsalternativ
                </button>
            </div>
        `;
        document.getElementById('answers').innerHTML += answerHtml;
    });

    // Visa formuläret
    document.getElementById('questionForm').style.display = 'block';
    document.getElementById('formTitle').innerText = `Redigera fråga ${orderNumber}, (QId: ${questionID})`;

    // Hantera sparande av ändringar
    document.querySelector('.btn-success').onclick = function () {
        saveEditedQuestion(questionID);
    };
}




function removeAnswer(index) {
    const answerDiv = document.querySelector(`[data-answer-index="${index}"]`);
    if (answerDiv) {
        answerDiv.remove();
    }
}

document.addEventListener('input', function (event) {
    if (event.target.tagName.toLowerCase() === 'textarea') {
        event.target.style.height = 'auto'; // Återställ höjden
        event.target.style.height = `${event.target.scrollHeight}px`; // Sätt höjden baserat på innehållet
    }
});


function updateOrderNumber(questionID, newOrder) {
    console.log(`Dropdown value: ${newOrder}`); // Kontrollera värdet på "this.value"
    console.log(`Updating question ID=${questionID} to new OrderNumber=${newOrder}`);

    fetch('admin_update_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: parseInt(questionID, 10), // Konvertera till nummer
            newOrder: parseInt(newOrder, 10) // Konvertera till nummer
        }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Frågans ordning uppdaterades');
                fetchQuestions(); // Uppdatera listan
            } else {
                alert('Ett fel uppstod: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating order number:', error);
        });
}


function toggleActive(questionID, newStatus) {
    fetch('admin_toggle_active.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: questionID, isActive: newStatus }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Frågan har ${newStatus === 1 ? 'aktiverats' : 'inaktiverats'}`);
                fetchQuestions(); // Uppdatera listan
            } else {
                alert('Ett fel uppstod: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error toggling active state:', error);
        });
}


function saveEditedQuestion(questionID) {
    const questionText = document.getElementById('questionText').value;
    const questionValue = document.getElementById('questionValue').value;
    const orderNumber = document.getElementById('orderNumber').value;

    // Samla alla svarsalternativ som fortfarande finns i formuläret
    const answers = Array.from(document.getElementById('answers').children).map(answerDiv => {
        return {
            text: answerDiv.querySelector('textarea').value, // Ändrat från 'input' till 'textarea'
            response_id: answerDiv.querySelector('select').value,
            multiplier: answerDiv.querySelector('select:nth-of-type(2)').value // Multiplikator
        };
    });

    // Skicka uppdaterade data till servern
    fetch('admin_update_question.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: questionID,
            text: questionText,
            value: questionValue,
            order: orderNumber,
            answers: answers
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Frågan har uppdaterats');
            fetchQuestions(); // Uppdatera frågelistan
            hideQuestionForm();
        } else {
            alert('Ett fel uppstod vid uppdatering av frågan');
        }
    })
    .catch(error => {
        console.error('Error updating question:', error);
        alert('Ett tekniskt fel uppstod vid uppdatering av frågan.');
    });
}


</script>
