<?php
/**
 * Handle contact form submissions.
 */

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$recipientAddress = 'gospelbaracael@gmail.com';
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Send a response back to the client as JSON for AJAX requests or as a simple
 * HTML page for regular POST submissions.
 */
function respond(bool $success, string $message, bool $isAjaxRequest): void
{
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
        return;
    }

    http_response_code($success ? 200 : 400);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?php echo $success ? 'Message sent' : 'Message failed'; ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><a href="contact.php">Return to the contact page</a></p>
    </body>
    </html>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', $isAjaxRequest);
    exit;
}

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim((string) $_POST['phone']) : '';
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Please complete all required fields before submitting the form.', $isAjaxRequest);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please provide a valid email address.', $isAjaxRequest);
    exit;
}

$subject = sprintf('New inquiry from %s', $name);

$escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$escapedEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$escapedPhone = htmlspecialchars($phone !== '' ? $phone : 'Not provided', ENT_QUOTES, 'UTF-8');
$escapedMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New inquiry</title>
</head>
<body>
    <h2>New inquiry from the parish website</h2>
    <p><strong>Name:</strong> {$escapedName}</p>
    <p><strong>Email:</strong> {$escapedEmail}</p>
    <p><strong>Phone:</strong> {$escapedPhone}</p>
    <p><strong>Message:</strong><br>{$escapedMessage}</p>
</body>
</html>
HTML;

$altBodyLines = [
    'New inquiry from the parish website',
    '',
    'Name: ' . $name,
    'Email: ' . $email,
    'Phone: ' . ($phone !== '' ? $phone : 'Not provided'),
    '',
    'Message:',
    $message,
];

$smtpUsername = 'gospelbaracael@gmail.com';
$smtpPassword = 'nbawqssfjeovyaxv';
$senderAddress = $smtpUsername;
$senderName = 'St. John the Baptist Parish Inquiries';

if ($smtpUsername === 'yourgmail@gmail.com' || $smtpPassword === 'your_app_password') {
    error_log('Contact form mailer is using placeholder SMTP credentials. Update CONTACT_SMTP_USERNAME and CONTACT_SMTP_PASSWORD.');
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom($senderAddress, $senderName);
    $mail->addAddress($recipientAddress);

    if ($email !== '') {
        if ($name !== '') {
            $mail->addReplyTo($email, $name);
        } else {
            $mail->addReplyTo($email);
        }
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = implode(PHP_EOL, $altBodyLines);

    $mail->send();

    respond(true, 'Thank you! Your message has been sent successfully.', $isAjaxRequest);
    exit;
} catch (PHPMailerException $mailerException) {
    error_log('Contact form email failed: ' . $mailerException->getMessage());
} catch (Throwable $mailerError) {
    error_log('Contact form email encountered an unexpected error: ' . $mailerError->getMessage());
}

respond(false, 'We could not send your message right now. Please try again later.', $isAjaxRequest);
