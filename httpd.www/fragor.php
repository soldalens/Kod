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
        /* Grundläggande styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #214A81;
            color: #F2F2F2;
            margin: 0;
            padding: 0;
        }
        .container {
            background-color: #163A5F;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            margin-top: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #FCD169;
        }
        .btn-primary {
            background-color: #214A81;
            border-color: #214A81;
            color: #F2F2F2;
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #163A5F;
            border-color: #163A5F;
        }
        .form-label {
            font-weight: bold;
        }
        table {
            background-color: #214A81;
            border-radius: 8px;
            color: #F2F2F2;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #102A47;
        }
        table tbody tr:nth-child(even) {
            background-color: #163A5F;
        }
        table tbody tr:nth-child(odd) {
            background-color: #214A81;
        }
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
        textarea {
            width: 100%;
            min-height: 50px;
            resize: both;
            overflow: auto;
            box-sizing: border-box;
        }
        /* Språkvalstoggle */
        .language-toggle {
            margin: 10px;
            text-align: center;
        }
        .language-toggle select {
            width: 200px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Språkvalstoggle -->
    <div class="language-toggle">
        <label for="languageSelect" style="margin-right: 5px;">Välj språk / Select Language:</label>
        <select id="languageSelect" class="form-select" style="width: 200px; display: inline-block;" onchange="setLanguage(this.value)">
            <option value="sv">Svenska</option>
            <option value="en">English</option>
        </select>
    </div>

    <!-- Navigationsknappar -->
    <div class="navbar" style="justify-content: center;">
        <button class="btn btn-primary mb-3" onclick="showAddQuestionForm()">Lägg till fråga</button>
    </div>

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
        // Global variabel för valt språk (sv = svenska, en = english)
        
        let languageId = 'sv';
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('language')) {
            languageId = urlParams.get('language');
            // Sätt dropdownens värde så att rätt språk visas
            document.getElementById('languageSelect').value = languageId;
        }

        function setLanguage(lang) {
            window.location.href = window.location.protocol + "//" + window.location.host + window.location.pathname + "?language=" + lang;
        }

        let questions = []; // Frågor hämtas här

        // När vi hämtar textdata skickas språket med
        function fetchQuestions() {
            fetch(`admin_fetch_questions.php?language=${languageId}`)
                .then(response => response.json())
                .then(data => {
                    questions = data.map(q => ({
                        ...q,
                        isActive: parseInt(q.isActive, 10),
                    }));
                    console.log('Fetched questions:', questions);
                    renderQuestions();
                })
                .catch(error => console.error('Error fetching questions:', error));
        }

        function renderQuestions() {
            const table = document.getElementById('questionsTable');
            table.innerHTML = '';
            const totalQuestions = questions.length;
        
            questions.sort((a, b) => a.order - b.order)
                .forEach((q, index) => {
                    const answers = q.answers.map(a => `${a.text} (${a.mbti_trait})`).join('<br>');
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${q.text}</td>
                            <td>${q.value}</td>
                            <td>
                                <select class="form-select form-select-sm" onchange="updateOrderNumber(${q.id}, this.value)">
                                    ${Array.from({ length: totalQuestions }, (_, i) => {
                                        const order = i + 1;
                                        return `<option value="${order}" ${parseInt(order, 10) === parseInt(q.order, 10) ? 'selected' : ''}>${order}</option>`;
                                    }).join('')}
                                </select>
                            </td>
                            <td>${answers}</td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editQuestion(${q.id})">Redigera</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteQuestion(${q.id})">Radera</button>
                                <button class="btn btn-${q.isActive === 1 ? 'secondary' : 'success'} btn-sm" onclick="toggleActive(${q.id}, ${q.isActive === 1 ? 0 : 1})">
                                    ${q.isActive === 1 ? 'Inaktivera' : 'Aktivera'}
                                </button>
                            </td>
                        </tr>
                    `;
                    table.innerHTML += row;
                });
        }

        function showAddQuestionForm() {
            document.getElementById('questionForm').style.display = 'block';
            document.getElementById('formTitle').innerText = 'Lägg till ny fråga';
            document.getElementById('addQuestionForm').reset();
            document.getElementById('answers').innerHTML = '';
        }

        function hideQuestionForm() {
            document.getElementById('questionForm').style.display = 'none';
        }

        function addAnswer() {
            const answersDiv = document.getElementById('answers');
            const index = answersDiv.children.length;
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

        // Vid sparande av fråga (lägg till ny) skickas languageId med, då det handlar om textdata
        function saveQuestion() {
            const questionText = document.getElementById('questionText').value;
            const questionValue = document.getElementById('questionValue').value;
            const orderNumber = document.getElementById('orderNumber').value;
            const answers = Array.from(document.getElementById('answers').children).map(answerDiv => ({
                text: answerDiv.querySelector('input').value,
                response_id: answerDiv.querySelector('select').value,
                multiplier: answerDiv.querySelector('select:nth-of-type(2)').value
            }));

            fetch('admin_save_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text: questionText,
                    value: questionValue,
                    order: orderNumber,
                    answers: answers,
                    language: languageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchQuestions();
                    hideQuestionForm();
                } else {
                    alert('Ett fel uppstod vid sparandet av frågan!');
                }
            });
        }

        function editQuestion(questionID) {
            questionID = String(questionID);
            const question = questions.find(q => String(q.id) === questionID);
            if (!question) {
                alert('Frågan kunde inte hittas');
                return;
            }
            document.getElementById('questionText').value = question.text;
            document.getElementById('questionValue').value = question.value;
            document.getElementById('orderNumber').value = question.order;
            document.getElementById('answers').innerHTML = '';

            question.answers.forEach((answer, index) => {
                const answerHtml = `
                    <div class="input-group mb-2" data-answer-index="${index}">
                        <textarea class="form-control" placeholder="Svarsalternativ text [vän]">${answer.text}</textarea>
                        <select class="form-select" style="width: 120px; font-size: 14px;">
                            <option value="1" ${answer.response_id == 1 ? 'selected' : ''}>Extroversion</option>
                            <option value="2" ${answer.response_id == 2 ? 'selected' : ''}>Introversion</option>
                            <option value="3" ${answer.response_id == 3 ? 'selected' : ''}>Sensing</option>
                            <option value="4" ${answer.response_id == 4 ? 'selected' : ''}>Intuition</option>
                            <option value="5" ${answer.response_id == 5 ? 'selected' : ''}>Thinking</option>
                            <option value="6" ${answer.response_id == 6 ? 'selected' : ''}>Feeling</option>
                            <option value="7" ${answer.response_id == 7 ? 'selected' : ''}>Judging</option>
                            <option value="8" ${answer.response_id == 8 ? 'selected' : ''}>Perceiving</option>
                        </select>
                        <select class="form-select ms-2" style="width: 120px;">
                            <option value="0.8" ${answer.multiplier == 0.8 ? 'selected' : ''}>0.8</option>
                            <option value="1.0" ${answer.multiplier == 1.0 ? 'selected' : ''}>1.0</option>
                            <option value="1.2" ${answer.multiplier == 1.2 ? 'selected' : ''}>1.2</option>
                            <option value="1.4" ${answer.multiplier == 1.4 ? 'selected' : ''}>1.4</option>
                            <option value="1.6" ${answer.multiplier == 1.6 ? 'selected' : ''}>1.6</option>
                            <option value="1.8" ${answer.multiplier == 1.8 ? 'selected' : ''}>1.8</option>
                            <option value="2.0" ${answer.multiplier == 2.0 ? 'selected' : ''}>2.0</option>
                            <option value="2.2" ${answer.multiplier == 2.2 ? 'selected' : ''}>2.2</option>
                        </select>
                        <button type="button" class="btn btn-danger btn-sm ms-2" onclick="removeAnswer(${index})">Ta bort svarsalternativ</button>
                    </div>
                `;
                document.getElementById('answers').innerHTML += answerHtml;
            });

            document.getElementById('questionForm').style.display = 'block';
            document.getElementById('formTitle').innerText = `Redigera fråga ${question.order}, (QId: ${questionID})`;

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
                event.target.style.height = 'auto';
                event.target.style.height = `${event.target.scrollHeight}px`;
            }
        });

        // Observera: vid ändring av ordernummer, aktivering/inaktivering och radering skickas inte med languageId
        function updateOrderNumber(questionID, newOrder) {
            fetch('admin_update_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: parseInt(questionID, 10),
                    newOrder: parseInt(newOrder, 10)
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Frågans ordning uppdaterades');
                    fetchQuestions();
                } else {
                    alert('Ett fel uppstod: ' + data.message);
                }
            })
            .catch(error => console.error('Error updating order number:', error));
        }

        function toggleActive(questionID, newStatus) {
            fetch('admin_toggle_active.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id: questionID, 
                    isActive: newStatus
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Frågan har ${newStatus === 1 ? 'aktiverats' : 'inaktiverats'}`);
                    fetchQuestions();
                } else {
                    alert('Ett fel uppstod: ' + data.message);
                }
            })
            .catch(error => console.error('Error toggling active state:', error));
        }

        function saveEditedQuestion(questionID) {
            const questionText = document.getElementById('questionText').value;
            const questionValue = document.getElementById('questionValue').value;
            const orderNumber = document.getElementById('orderNumber').value;
            const answers = Array.from(document.getElementById('answers').children).map(answerDiv => ({
                text: answerDiv.querySelector('textarea').value,
                response_id: answerDiv.querySelector('select').value,
                multiplier: answerDiv.querySelector('select:nth-of-type(2)').value
            }));

            fetch('admin_update_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: questionID,
                    text: questionText,
                    value: questionValue,
                    order: orderNumber,
                    answers: answers,
                    language: languageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Frågan har uppdaterats');
                    fetchQuestions();
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

        // Raderingsfunktion – skickar inte med languageId
        function deleteQuestion(questionID) {
            if (confirm("Är du säker på att du vill radera frågan?")) {
                fetch('admin_delete_question.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: questionID
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Frågan raderades');
                        fetchQuestions();
                    } else {
                        alert('Ett fel uppstod: ' + data.message);
                    }
                })
                .catch(error => console.error('Error deleting question:', error));
            }
        }

        // Initial hämtningskörning
        fetchQuestions();
    </script>
</body>
</html>
