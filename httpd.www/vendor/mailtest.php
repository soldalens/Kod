<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Ladda in PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
echo "Scriptet startar...\n";
require 'vendor/autoload.php'; // Säkerställ att autoload.php finns i din 'vendor'-mapp

// Skapa ett nytt PHPMailer-objekt
$mail = new PHPMailer(true);

try {
    // Serverinställningar
    $mail->isSMTP();                                 // Använd SMTP
    $mail->Host = 'mailout.one.com';                 // SMTP-server för One.com
    $mail->SMTPAuth = true;                          // Aktivera SMTP-autentisering
    $mail->Username = 'niklas@vgrad.se';             // Din One.com e-postadress
    $mail->Password = 'Hockey01!';                   // Ditt lösenord
    $mail->SMTPSecure = 'tls';                       // Använd TLS-kryptering
    $mail->Port = 587;                               // Port 587 för TLS
    $mail->CharSet = 'UTF-8';                        // Sätt teckenkodning till UTF-8

    // Avsändare och mottagare
    $mail->setFrom('info@verkningsgrad.se', 'Niklas från Verkningsgrad');
    $mail->addAddress('niklas.pettersson@proutveckling.se', 'Niklas Pettersson'); 

    // Innehåll i mejlet
    $mail->isHTML(true);                             // Skicka mejlet som HTML
    $mail->Subject = 'Inbjudan till Personlighetsanalys';
    $mail->Body    = '<p>Hej Niklas Pettersson,</p>
                      <p>Du är inbjuden att fylla i en <b>personlighetsanalys</b>.</p>
                      <p>Klicka på länken nedan för att börja:</p>
                      <p><a href="https://verkningsgrad.se/survey.php">Börja här</a></p>
                      <p>Med vänliga hälsningar,<br>Verkningsgrad Team</p>';
    $mail->AltBody = "Hej Niklas Pettersson,\n\nDu är inbjuden att fylla i en personlighetsanalys.\nBörja här: https://verkningsgrad.se/survey.php\n\nMed vänliga hälsningar,\nVerkningsgrad Team";

    // Skicka mejlet
    $mail->send();
    echo 'Inbjudan har skickats!';
} catch (Exception $e) {
    echo "Kunde inte skicka mejlet. Felmeddelande: {$mail->ErrorInfo}";
}
?>
