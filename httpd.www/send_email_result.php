<?php
// Inkludera PHPMailers bibliotek
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Anslut till databasen
$servername = "proanalys.se.mysql";
$username = "proanalys_seproanalys";
$password = "MFFDortmund9!";
$database = "proanalys_seproanalys";

$conn = new mysqli($servername, $username, $password, $database);

// Ställ in rätt teckenkodning för databaskopplingen
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Hämta data från URL:en (t.ex. via GET-parameter)
if (isset($_GET['AnswerId'])) {
    $answerId = intval($_GET['AnswerId']); // Säkerställ att det är ett heltal

    // Steg 1: Hämta InviteCode, GroupName, och PersonalityType från SurveyHeaders baserat på AnswerId
    $stmt = $conn->prepare("SELECT InviteCode, GroupName, PersonalityType, Name, Email FROM SurveyHeaders WHERE AnswerId = ?");
    $stmt->bind_param("i", $answerId);
    $stmt->execute();
    $stmt->bind_result($inviteCode, $groupName, $personalityType, $userName, $userEmail);
    $stmt->fetch();
    $stmt->close();

    if ($inviteCode) {
        // Steg 2: Hämta InvitedBy (UserId) från SurveyInvites baserat på InviteCode
        $stmt = $conn->prepare("SELECT InvitedBy FROM SurveyInvites WHERE InviteCode = ?");
        $stmt->bind_param("s", $inviteCode);
        $stmt->execute();
        $stmt->bind_result($invitedBy);
        $stmt->fetch();
        $stmt->close();

        if ($invitedBy) {
            // Steg 3: Hämta Name och Email från Users baserat på InvitedBy (UserId)
            $stmt = $conn->prepare("SELECT Name, Email, FirstName FROM Users WHERE UserId = ?");
            $stmt->bind_param("s", $invitedBy);
            $stmt->execute();
            $stmt->bind_result($invitorName, $invitorEmail, $invitorfirstname);
            $stmt->fetch();
            $stmt->close();

            if ($invitorName && $invitorEmail) {
                // Skicka e-post
                $mail = new PHPMailer(true);

                try {
                    // Serverinställningar
                $mail->Host = 'mailout.one.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'inbjudan@proanalys.se';
                $mail->Password = 'MFFDortmund9!';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true); // Använd HTML
                
                    // Avsändare och mottagare
                    $mail->setFrom('inbjudan@proanalys.se', 'ProAnalys Admin');
                    $mail->addAddress($invitorEmail); // Dynamiskt hämtad mejladress

                    // Innehåll
                    $mail->isHTML(true);
                    $mail->Subject = 'Personlighetsanalys genomförd';
                    $mail->Body = "
                        <p>Hej $invitorfirstname!</p>
                        <p>Följande person har fyllt i personlighetsanalysen:</p>
                        <p><strong>$userName</strong> med mejladress <strong>$userEmail</strong> från gruppen <strong>$groupName</strong>.</p>
                        <p>Personlighetstypen är förmodligen: <strong>$personalityType</strong>.</p>
                        <p>Du kan söka fram resultatet här: <a href='https://proanalys.se/admin' target='_blank'>https://proanalys.se/admin</a></p>
                        <p>Vänliga hälsningar<br>
                        Din vänliga personlighetsanalysrobot som är ISTJ... tror jag. :-)</p>
                    ";

                    // Skicka e-post
                    $mail->send();
                    echo "E-post skickad till $invitorEmail!";
                } catch (Exception $e) {
                    echo "E-post kunde inte skickas. Fel: {$mail->ErrorInfo}";
                }
            } else {
                echo "Ingen mottagare hittades i Users-tabellen.";
            }
        } else {
            echo "Ingen InvitedBy hittades för InviteCode.";
        }
    } else {
        echo "Ingen InviteCode hittades för AnswerId.";
    }
} else {
    echo "Ingen AnswerId angiven.";
}

// Stäng databaskopplingen
$conn->close();
?>
