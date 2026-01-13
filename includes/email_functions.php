<?php
// includes/email_functions.php

function send_password_reset_email($email, $reset_link) {
    // Per ora implementiamo un sistema base
    // In produzione, integra con un servizio email come PHPMailer, SendGrid, etc.
    
    $subject = "Recupero Password - Influencer Marketplace";
    
    $message = "
    <html>
    <head>
        <title>Recupero Password</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .button { background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Recupero Password</h2>
            <p>Hai richiesto di reimpostare la tua password. Clicca sul link qui sotto per procedere:</p>
            <p><a href='$reset_link' class='button'>Reimposta Password</a></p>
            <p>Se il pulsante non funziona, copia e incolla questo link nel tuo browser:</p>
            <p>$reset_link</p>
            <p><strong>Il link scadrà tra 24 ore.</strong></p>
            <p>Se non hai richiesto il recupero password, ignora questa email.</p>
        </div>
    </body>
    </html>
    ";
    
    // Headers per email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@influencer-marketplace.com" . "\r\n";
    
    // In produzione, sostituisci mail() con un vero servizio SMTP
    return mail($email, $subject, $message, $headers);
}

// Funzione per inviare conferma cambio password
function send_password_changed_email($email) {
    $subject = "Password Modificata - Influencer Marketplace";
    
    $message = "
    <html>
    <head>
        <title>Password Modificata</title>
    </head>
    <body>
        <h2>Password Modificata con Successo</h2>
        <p>La tua password è stata modificata correttamente.</p>
        <p>Se non sei stato tu a effettuare questa modifica, contatta immediatamente l'assistenza.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@influencer-marketplace.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}
?>