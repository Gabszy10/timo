<?php
/**
 * Handle contact form submissions.
 */

$recipient = 'gospelbaracael@gmail.com';
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

$body = <<<HTML
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

$headers = "From: St. John the Baptist Parish <no-reply@stjohnbaptistparish.local>\r\n";
$headers .= "Reply-To: {$escapedEmail}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$sent = mail($recipient, $subject, $body, $headers);

if ($sent) {
    respond(true, 'Thank you! Your message has been sent successfully.', $isAjaxRequest);
    exit;
}

respond(false, 'We could not send your message right now. Please try again later.', $isAjaxRequest);
