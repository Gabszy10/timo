<?php
session_start();

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/reservation_repository.php';
require_once __DIR__ . '/includes/sms_notifications.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

const ADMIN_LOGIN_ACTION = 'login';
const ADMIN_STATUS_UPDATE_ACTION = 'update_status';
const ADMIN_ANNOUNCEMENT_CREATE_ACTION = 'create_announcement';
const ADMIN_ANNOUNCEMENT_TOGGLE_ACTION = 'toggle_announcement';
const ADMIN_ANNOUNCEMENT_DELETE_ACTION = 'delete_announcement';
const ADMIN_DEFAULT_SECTION = 'overview';

/**
 * Sanitize a requested section name.
 */
function sanitize_admin_section(?string $section): string
{
    $allowedSections = ['overview', 'reservations', 'schedule', 'announcements'];
    if ($section === null) {
        return ADMIN_DEFAULT_SECTION;
    }

    $normalized = strtolower(trim($section));
    if ($normalized === '') {
        return ADMIN_DEFAULT_SECTION;
    }

    return in_array($normalized, $allowedSections, true) ? $normalized : ADMIN_DEFAULT_SECTION;
}

function build_admin_section_url(string $section): string
{
    $normalized = sanitize_admin_section($section);
    return $normalized === ADMIN_DEFAULT_SECTION
        ? 'admin.php'
        : 'admin.php?section=' . urlencode($normalized);
}

/**
 * Ensure the announcements table exists before interacting with it.
 */
function ensure_announcements_table_exists(): void
{
    $connection = get_db_connection();
    $query = 'CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            body TEXT NOT NULL,
            image_path VARCHAR(255) NULL,
            show_on_home TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if (!mysqli_query($connection, $query)) {
        $error = 'Unable to verify announcements table: ' . mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $columnResult = mysqli_query($connection, "SHOW COLUMNS FROM announcements LIKE 'image_path'");
    if ($columnResult === false) {
        $error = 'Unable to verify announcements columns: ' . mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $hasImageColumn = mysqli_num_rows($columnResult) > 0;
    mysqli_free_result($columnResult);

    if (!$hasImageColumn) {
        $alterQuery = 'ALTER TABLE announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER body';
        if (!mysqli_query($connection, $alterQuery)) {
            $error = 'Unable to update announcements table: ' . mysqli_error($connection);
            mysqli_close($connection);
            throw new Exception($error);
        }
    }

    mysqli_close($connection);
}

function get_announcement_upload_directory(): string
{
    return __DIR__ . '/uploads/announcements';
}

function ensure_announcement_upload_directory_exists(): void
{
    $directory = get_announcement_upload_directory();
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new Exception('Unable to create announcement uploads directory.');
    }
}

function process_announcement_image_upload(?array $file): ?string
{
    if ($file === null) {
        return null;
    }

    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new Exception('Failed to upload announcement image.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid announcement image upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new Exception('Unsupported image type. Please upload a JPG, PNG, GIF, or WEBP file.');
    }

    ensure_announcement_upload_directory_exists();

    $extension = $allowedMimeTypes[$mimeType];
    $fileName = uniqid('announcement_', true) . '.' . $extension;
    $destinationDirectory = get_announcement_upload_directory();
    $destinationPath = $destinationDirectory . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        throw new Exception('Unable to save announcement image.');
    }

    return 'uploads/announcements/' . $fileName;
}

function delete_announcement_image(string $relativePath): void
{
    $normalizedPath = ltrim($relativePath, '/');
    $fullPath = __DIR__ . '/' . $normalizedPath;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

/**
 * Store a new announcement.
 */
function create_announcement(string $title, string $body, bool $showOnHome, ?string $imagePath): void
{
    $connection = get_db_connection();

    $query = 'INSERT INTO announcements (title, body, image_path, show_on_home) VALUES (?, ?, ?, ?)';
    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare announcement insert: ' . mysqli_error($connection));
    }

    $show = $showOnHome ? 1 : 0;
    mysqli_stmt_bind_param($statement, 'sssi', $title, $body, $imagePath, $show);

    if (!mysqli_stmt_execute($statement)) {
        $error = 'Unable to save announcement: ' . mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);
}

/**
 * Fetch announcements ordered by creation time.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_announcements(): array
{
    $connection = get_db_connection();
    $query = 'SELECT id, title, body, image_path, show_on_home, created_at FROM announcements ORDER BY created_at DESC';
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        $error = 'Unable to fetch announcements: ' . mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $announcements = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }

    mysqli_free_result($result);
    mysqli_close($connection);

    return $announcements;
}

/**
 * Toggle an announcement visibility flag.
 */
function update_announcement_visibility(int $announcementId, bool $showOnHome): void
{
    if ($announcementId <= 0) {
        throw new InvalidArgumentException('Invalid announcement selected.');
    }

    $connection = get_db_connection();
    $query = 'UPDATE announcements SET show_on_home = ? WHERE id = ?';
    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare announcement update: ' . mysqli_error($connection));
    }

    $show = $showOnHome ? 1 : 0;
    mysqli_stmt_bind_param($statement, 'ii', $show, $announcementId);

    if (!mysqli_stmt_execute($statement)) {
        $error = 'Unable to update announcement: ' . mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);
}

/**
 * Permanently delete an announcement.
 */
function delete_announcement(int $announcementId): void
{
    if ($announcementId <= 0) {
        throw new InvalidArgumentException('Invalid announcement selected.');
    }

    $connection = get_db_connection();
    $imageQuery = 'SELECT image_path FROM announcements WHERE id = ?';
    $imageStatement = mysqli_prepare($connection, $imageQuery);

    if ($imageStatement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare announcement lookup: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($imageStatement, 'i', $announcementId);

    $imagePath = null;

    if (mysqli_stmt_execute($imageStatement)) {
        mysqli_stmt_bind_result($imageStatement, $imagePath);
        mysqli_stmt_fetch($imageStatement);
    } else {
        $error = 'Unable to locate announcement: ' . mysqli_stmt_error($imageStatement);
        mysqli_stmt_close($imageStatement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    mysqli_stmt_close($imageStatement);

    $query = 'DELETE FROM announcements WHERE id = ?';
    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare announcement delete: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($statement, 'i', $announcementId);

    if (!mysqli_stmt_execute($statement)) {
        $error = 'Unable to delete announcement: ' . mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);

    if ($imagePath !== null && $imagePath !== '') {
        delete_announcement_image($imagePath);
    }
}

function build_reservation_status_update_messaging(string $status): array
{
    $normalized = strtolower(trim($status));

    $messages = [
        'approved' => [
            'subject' => 'Your reservation has been approved',
            'heading' => 'Your reservation is approved',
            'intro' => 'Great news! Your reservation request has been approved and is now on our schedule.',
            'next_steps' => 'Please review the details below and reach out if anything needs to be adjusted before your event.',
        ],
        'declined' => [
            'subject' => 'Update on your reservation request',
            'heading' => 'We have an update on your reservation',
            'intro' => 'Thank you for your patience. After reviewing your request we are unable to confirm the reservation as submitted.',
            'next_steps' => 'Please review the details below and reply to this email if you have questions or would like to discuss alternative arrangements.',
        ],
        'pending' => [
            'subject' => 'Reservation status update',
            'heading' => 'Your reservation is pending review',
            'intro' => 'We wanted to let you know that your reservation has been moved back to pending while we double-check a few details.',
            'next_steps' => 'We will follow up with another update soon. If you can provide additional information, please reply to this email.',
        ],
    ];

    return $messages[$normalized] ?? [
        'subject' => 'Reservation status update',
        'heading' => 'We have an update on your reservation',
        'intro' => 'We wanted to share a quick update regarding your reservation request.',
        'next_steps' => 'Please review the details below and let us know if you have any questions.',
    ];
}

/**
 * Render a consistently styled table row for reservation summary emails.
 */
function render_reservation_email_detail_row(string $label, string $value, bool $valueContainsHtml = false): string
{
    $trimmedValue = $valueContainsHtml ? trim(strip_tags($value)) : trim($value);
    if ($trimmedValue === '') {
        return '';
    }

    $labelCell = '<td style="padding:16px 24px; border-bottom:1px solid #e2e8f0; width:38%; font-size:12px;'
        . ' letter-spacing:0.08em; text-transform:uppercase; color:#64748b; font-weight:700; vertical-align:top;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';

    $valueCellStyles = 'padding:16px 24px; border-bottom:1px solid #e2e8f0; font-size:15px; color:#0f172a;'
        . ' font-weight:600; line-height:1.6;';
    $valueCellContent = $valueContainsHtml
        ? $value
        : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    if ($valueContainsHtml) {
        $valueCellStyles .= ' font-weight:500;';
    }

    $valueCell = '<td style="' . $valueCellStyles . '">' . $valueCellContent . '</td>';

    return '<tr>' . $labelCell . $valueCell . '</tr>';
}

/**
 * Provide styling accents for reservation status update emails.
 *
 * @return array{
 *     hero_gradient: string,
 *     badge_bg: string,
 *     badge_color: string,
 *     badge_label: string,
 *     primary_card_bg: string,
 *     primary_card_color: string,
 *     secondary_card_bg: string,
 *     secondary_card_color: string,
 *     cta_gradient: string
 * }
 */
function build_reservation_status_email_theme(string $status): array
{
    $normalized = strtolower(trim($status));

    $themes = [
        'approved' => [
            'hero_gradient' => 'linear-gradient(135deg,#15803d 0%,#22c55e 50%,#4ade80 100%)',
            'badge_bg' => 'rgba(34,197,94,0.18)',
            'badge_color' => '#166534',
            'badge_label' => 'Approved',
            'primary_card_bg' => '#dcfce7',
            'primary_card_color' => '#14532d',
            'secondary_card_bg' => '#bbf7d0',
            'secondary_card_color' => '#166534',
            'cta_gradient' => 'linear-gradient(135deg,#15803d,#22c55e)',
        ],
        'declined' => [
            'hero_gradient' => 'linear-gradient(135deg,#b91c1c 0%,#ef4444 55%,#f87171 100%)',
            'badge_bg' => 'rgba(248,113,113,0.18)',
            'badge_color' => '#991b1b',
            'badge_label' => 'Declined',
            'primary_card_bg' => '#fee2e2',
            'primary_card_color' => '#7f1d1d',
            'secondary_card_bg' => '#fecaca',
            'secondary_card_color' => '#991b1b',
            'cta_gradient' => 'linear-gradient(135deg,#dc2626,#ef4444)',
        ],
        'pending' => [
            'hero_gradient' => 'linear-gradient(135deg,#4338ca 0%,#6366f1 55%,#60a5fa 100%)',
            'badge_bg' => 'rgba(99,102,241,0.18)',
            'badge_color' => '#3730a3',
            'badge_label' => 'Pending',
            'primary_card_bg' => '#eef2ff',
            'primary_card_color' => '#312e81',
            'secondary_card_bg' => '#e0f2fe',
            'secondary_card_color' => '#0c4a6e',
            'cta_gradient' => 'linear-gradient(135deg,#4f46e5,#2563eb)',
        ],
    ];

    return $themes[$normalized] ?? $themes['pending'];
}

function send_reservation_status_update_email(array $reservation, string $status): void
{
    $recipientEmail = isset($reservation['email']) ? trim((string) $reservation['email']) : '';
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $messaging = build_reservation_status_update_messaging($status);

    $theme = build_reservation_status_email_theme($status);

    $customerName = isset($reservation['name']) ? trim((string) $reservation['name']) : '';
    $greetingName = $customerName !== '' ? htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') : 'there';

    $emailValue = isset($reservation['email']) ? trim((string) $reservation['email']) : '';
    $phoneValue = isset($reservation['phone']) ? trim((string) $reservation['phone']) : '';
    $eventTypeValue = isset($reservation['event_type']) ? trim((string) $reservation['event_type']) : '';
    $preferredDateValue = isset($reservation['preferred_date']) ? trim((string) $reservation['preferred_date']) : '';
    $preferredTimeValue = isset($reservation['preferred_time']) ? trim((string) $reservation['preferred_time']) : '';

    $rawNotes = isset($reservation['notes']) ? (string) $reservation['notes'] : '';
    $trimmedNotes = trim($rawNotes);
    $notesHtml = '<em>No additional notes provided.</em>';
    $notesText = 'No additional notes provided.';

    if ($trimmedNotes !== '') {
        $notesHtml = nl2br(htmlspecialchars($rawNotes, ENT_QUOTES, 'UTF-8'));
        $plain = preg_replace("/(\r\n|\r|\n)/", PHP_EOL, strip_tags($rawNotes));
        $plain = trim((string) $plain);
        if ($plain === '') {
            $plain = 'No additional notes provided.';
        }
        $notesText = $plain;
    }

    $statusLabel = ucfirst(strtolower($status));
    $statusDisplay = $statusLabel !== '' ? $statusLabel : 'Status update';
    $statusBadge = '<span style="display:inline-block; padding:8px 16px; background-color:' . $theme['badge_bg']
        . '; color:' . $theme['badge_color'] . '; font-size:12px; letter-spacing:0.1em; text-transform:uppercase;'
        . ' font-weight:700; border-radius:999px;">' . htmlspecialchars($theme['badge_label'], ENT_QUOTES, 'UTF-8') . '</span>';

    $preferredDateDisplay = $preferredDateValue !== '' ? $preferredDateValue : 'To be confirmed';
    $preferredTimeDisplay = $preferredTimeValue !== '' ? $preferredTimeValue : 'To be confirmed';

    $summaryRows = '';
    $summaryRows .= render_reservation_email_detail_row('Name', $customerName !== '' ? $customerName : 'Not provided');
    $summaryRows .= render_reservation_email_detail_row('Email', $emailValue !== '' ? $emailValue : 'Not provided');
    $summaryRows .= render_reservation_email_detail_row('Phone', $phoneValue !== '' ? $phoneValue : 'Not provided');
    $summaryRows .= render_reservation_email_detail_row('Event type', $eventTypeValue !== '' ? $eventTypeValue : 'Not specified');
    $summaryRows .= render_reservation_email_detail_row('Preferred date', $preferredDateDisplay);
    $summaryRows .= render_reservation_email_detail_row('Preferred time', $preferredTimeDisplay);
    $summaryRows .= render_reservation_email_detail_row('Current status', $statusDisplay);
    $summaryRows .= render_reservation_email_detail_row('Notes', $notesHtml, true);

    $summaryTable = '<div style="border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; margin:0 0 24px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . '<tr><td colspan="2" style="padding:18px 24px; background-color:#f8fafc; font-size:12px; letter-spacing:0.08em;'
        . ' text-transform:uppercase; color:#475569; font-weight:700;">Reservation summary</td></tr>'
        . $summaryRows
        . '</table>'
        . '</div>';

    $infoCards = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">'
        . '<tr>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:' . $theme['primary_card_bg'] . '; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:' . $theme['primary_card_color']
        . '; font-weight:700;">Reservation status</div>'
        . '<div style="margin-top:12px; font-size:20px; font-weight:700; color:' . $theme['primary_card_color'] . ';">'
        . htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p style="margin:12px 0 0; font-size:14px; line-height:1.5; color:' . $theme['primary_card_color']
        . '; opacity:0.82;">' . htmlspecialchars($messaging['next_steps'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:' . $theme['secondary_card_bg'] . '; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:' . $theme['secondary_card_color']
        . '; font-weight:700;">Event timing</div>'
        . '<div style="margin-top:12px; font-size:16px; font-weight:700; color:' . $theme['secondary_card_color'] . ';">'
        . 'Date: ' . htmlspecialchars($preferredDateDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-top:6px; font-size:16px; font-weight:700; color:' . $theme['secondary_card_color'] . ';">'
        . 'Time: ' . htmlspecialchars($preferredTimeDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p style="margin:12px 0 0; font-size:14px; line-height:1.5; color:' . $theme['secondary_card_color']
        . '; opacity:0.82;">Let us know if these details need to be adjusted.</p>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>';

    $smtpUsername = 'gospelbaracael@gmail.com';
    $smtpPassword = 'nbawqssfjeovyaxv';
    $senderAddress = $smtpUsername;
    $senderName = 'St. John the Baptist Parish Reservations';

    $mail = new PHPMailer(true);
    $notificationMessage = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . htmlspecialchars($messaging['subject'], ENT_QUOTES, 'UTF-8') . '</title>'
        . '<style type="text/css">@media screen and (max-width:520px){.stack-column{display:block!important;width:100%!important;max-width:100%!important;}}</style>'
        . '</head>'
        . '<body style="margin:0; background-color:#f5f7fb; font-family:\'Segoe UI\', Arial, sans-serif; color:#1f2937;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fb;">'
        . '<tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="max-width:600px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 24px 60px rgba(15,23,42,0.18);">'
        . '<tr><td style="background:' . $theme['hero_gradient'] . '; padding:36px 32px; color:#ffffff;">'
        . '<div style="font-size:12px; letter-spacing:0.22em; text-transform:uppercase; opacity:0.85;">Reservation update</div>'
        . '<div style="margin-top:12px; font-size:26px; font-weight:700;">' . htmlspecialchars($messaging['heading'], ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p style="margin:18px 0 0; font-size:16px; line-height:1.6; opacity:0.92;">' . htmlspecialchars($messaging['intro'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div style="margin-top:24px;">' . $statusBadge . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:32px 32px 16px; color:#334155;">'
        . '<p style="margin:0 0 18px; font-size:16px; line-height:1.6;">Hello ' . $greetingName . ',</p>'
        . '<p style="margin:0 0 18px; font-size:16px; line-height:1.6;">' . htmlspecialchars($messaging['next_steps'], ENT_QUOTES, 'UTF-8') . '</p>'
        . $infoCards
        . $summaryTable
        . '<p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#475569;">If any detail looks incorrect or you need more assistance, simply reply to this email and we will be happy to help.</p>'
        . '<p style="margin:24px 0 32px; text-align:center;">'
        . '<a href="mailto:' . htmlspecialchars($senderAddress, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; padding:14px 28px; background:' . $theme['cta_gradient'] . '; color:#ffffff; text-decoration:none; border-radius:999px; font-weight:700; letter-spacing:0.05em;">Reply to the reservations desk</a>'
        . '</p>'
        . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#475569;">We appreciate you choosing St. John the Baptist Parish.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 32px 32px; background-color:#f8fafc; text-align:center; font-size:12px; color:#94a3b8;">St. John the Baptist Parish &bull; Reservation desk</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';

    $altBodyLines = [
        $messaging['heading'],
        '',
        'Hello ' . ($customerName !== '' ? $customerName : 'there') . ',',
        $messaging['intro'],
        $messaging['next_steps'],
        '',
        'Status: ' . $statusDisplay,
        'Preferred date: ' . $preferredDateDisplay,
        'Preferred time: ' . $preferredTimeDisplay,
        '',
        'Name: ' . ($customerName !== '' ? $customerName : 'Not provided'),
        'Email: ' . ($emailValue !== '' ? $emailValue : 'Not provided'),
        'Phone: ' . ($phoneValue !== '' ? $phoneValue : 'Not provided'),
        'Event type: ' . ($eventTypeValue !== '' ? $eventTypeValue : 'Not specified'),
        'Notes: ' . $notesText,
        '',
        'Reply to this email if you need any assistance.',
    ];

    if ($smtpUsername === 'yourgmail@gmail.com' || $smtpPassword === 'your_app_password') {
        error_log('Reservation status update mailer is using placeholder SMTP credentials. Update RESERVATION_SMTP_USERNAME and RESERVATION_SMTP_PASSWORD.');
    }

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($senderAddress, $senderName);
        if ($customerName !== '') {
            $mail->addAddress($recipientEmail, $customerName);
        } else {
            $mail->addAddress($recipientEmail);
        }

        $mail->isHTML(true);
        $mail->Subject = $messaging['subject'];
        $mail->Body = $notificationMessage;
        $mail->AltBody = implode(PHP_EOL, $altBodyLines);

        $mail->send();
    } catch (PHPMailerException $mailerException) {
        error_log('Reservation status update email failed: ' . $mailerException->getMessage());
    } catch (Throwable $mailerError) {
        error_log('Reservation status update encountered an unexpected error: ' . $mailerError->getMessage());
    }

    if ($phoneValue !== '') {
        $scheduleSummary = $preferredDateDisplay;
        if ($preferredTimeDisplay !== '' && strcasecmp($preferredTimeDisplay, 'To be confirmed') !== 0) {
            $scheduleSummary .= ' at ' . $preferredTimeDisplay;
        }

        $customerSmsName = $customerName !== '' ? $customerName : 'there';
        $statusLabel = $statusDisplay !== '' ? $statusDisplay : ucfirst(strtolower($status));
        $smsMessage = sprintf(
            'Hi %s, your reservation status is now %s. Schedule: %s. Please check your email for details. - St. John the Baptist Parish',
            $customerSmsName,
            $statusLabel,
            $scheduleSummary
        );

        send_sms_notification($phoneValue, $smsMessage);
    }
}

$loginError = '';
$submittedUsername = '';

function admin_credentials_are_valid(string $username, string $password): bool
{
    return $username === 'admin' && $password === 'admin';
}

// logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

$flashSuccess = $_SESSION['admin_flash_success'] ?? '';
$flashError = $_SESSION['admin_flash_error'] ?? '';
unset($_SESSION['admin_flash_success'], $_SESSION['admin_flash_error']);

$currentSection = sanitize_admin_section($_GET['section'] ?? null);

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectSection = sanitize_admin_section($_POST['redirect_section'] ?? $currentSection);

    if ($action === ADMIN_LOGIN_ACTION) {
        $submittedUsername = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (admin_credentials_are_valid($submittedUsername, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $submittedUsername;
            header('Location: ' . build_admin_section_url($redirectSection));
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }

    if ($action === ADMIN_STATUS_UPDATE_ACTION) {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            $_SESSION['admin_flash_error'] = 'You must be logged in to perform this action.';
            header('Location: admin.php');
            exit;
        }

        $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';

        try {
            if ($reservationId === false || $reservationId === null) {
                throw new InvalidArgumentException('Invalid reservation selected.');
            }

            update_reservation_status($reservationId, $status);
            try {
                $reservation = fetch_reservation_by_id($reservationId);
                if (is_array($reservation)) {
                    $reservation['status'] = $status;
                    send_reservation_status_update_email($reservation, $status);
                }
            } catch (Throwable $emailException) {
                error_log('Unable to send reservation status update notification: ' . $emailException->getMessage());
            }
            $_SESSION['admin_flash_success'] = 'Reservation status updated successfully.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash_error'] = $exception->getMessage();
        }

        header('Location: ' . build_admin_section_url('reservations'));
        exit;
    }

    if ($action === ADMIN_ANNOUNCEMENT_CREATE_ACTION) {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            $_SESSION['admin_flash_error'] = 'You must be logged in to perform this action.';
            header('Location: ' . build_admin_section_url(ADMIN_DEFAULT_SECTION));
            exit;
        }

        $title = trim($_POST['announcement_title'] ?? '');
        $body = trim($_POST['announcement_body'] ?? '');
        $showOnHome = isset($_POST['announcement_show']) && $_POST['announcement_show'] === '1';

        if ($title === '' || $body === '') {
            $_SESSION['admin_flash_error'] = 'Please provide both a title and message for the announcement.';
            header('Location: ' . build_admin_section_url('announcements'));
            exit;
        }

        $imagePath = null;

        try {
            ensure_announcements_table_exists();
            $imagePath = process_announcement_image_upload($_FILES['announcement_image'] ?? null);
            create_announcement($title, $body, $showOnHome, $imagePath);
            $_SESSION['admin_flash_success'] = 'Announcement published successfully.';
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                delete_announcement_image($imagePath);
            }
            $_SESSION['admin_flash_error'] = $exception->getMessage();
        }

        header('Location: ' . build_admin_section_url('announcements'));
        exit;
    }

    if ($action === ADMIN_ANNOUNCEMENT_TOGGLE_ACTION) {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            $_SESSION['admin_flash_error'] = 'You must be logged in to perform this action.';
            header('Location: ' . build_admin_section_url(ADMIN_DEFAULT_SECTION));
            exit;
        }

        $announcementId = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
        $showOnHome = ($_POST['show_on_home'] ?? '') === '1';

        if ($announcementId === false || $announcementId === null) {
            $_SESSION['admin_flash_error'] = 'Invalid announcement selected.';
            header('Location: ' . build_admin_section_url('announcements'));
            exit;
        }

        try {
            ensure_announcements_table_exists();
            update_announcement_visibility($announcementId, $showOnHome);
            $_SESSION['admin_flash_success'] = 'Announcement visibility updated.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash_error'] = $exception->getMessage();
        }

        header('Location: ' . build_admin_section_url('announcements'));
        exit;
    }

    if ($action === ADMIN_ANNOUNCEMENT_DELETE_ACTION) {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            $_SESSION['admin_flash_error'] = 'You must be logged in to perform this action.';
            header('Location: ' . build_admin_section_url(ADMIN_DEFAULT_SECTION));
            exit;
        }

        $announcementId = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);

        if ($announcementId === false || $announcementId === null) {
            $_SESSION['admin_flash_error'] = 'Invalid announcement selected.';
            header('Location: ' . build_admin_section_url('announcements'));
            exit;
        }

        try {
            ensure_announcements_table_exists();
            delete_announcement($announcementId);
            $_SESSION['admin_flash_success'] = 'Announcement deleted successfully.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash_error'] = $exception->getMessage();
        }

        header('Location: ' . build_admin_section_url('announcements'));
        exit;
    }
}

$isLoggedIn = ($_SESSION['admin_logged_in'] ?? false) === true;
$reservations = $isLoggedIn ? fetch_reservations() : [];
$reservationFilterRangeInput = filter_input(INPUT_GET, 'range', FILTER_SANITIZE_SPECIAL_CHARS);
if ($reservationFilterRangeInput === false) {
    $reservationFilterRangeInput = null;
}

$reservationFilterDateInput = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
if ($reservationFilterDateInput === false) {
    $reservationFilterDateInput = null;
}

$scheduleDateInput = filter_input(INPUT_GET, 'schedule_date', FILTER_SANITIZE_SPECIAL_CHARS);
if ($scheduleDateInput === false) {
    $scheduleDateInput = null;
}

$reservationFilterResult = [
    'reservations' => $reservations,
    'range' => 'all',
    'date' => null,
    'description' => 'Showing all reservations.',
];

if ($isLoggedIn) {
    $reservationFilterResult = apply_reservation_time_filter(
        $reservations,
        $reservationFilterRangeInput,
        $reservationFilterDateInput
    );
}

$filteredReservations = $reservationFilterResult['reservations'];
$reservationFilterRange = $reservationFilterResult['range'];
$reservationFilterDate = $reservationFilterResult['date'];
$reservationFilterDescription = $reservationFilterResult['description'];
$hasActiveReservationFilter = $reservationFilterRange !== 'all';

$announcements = [];

if ($isLoggedIn) {
    try {
        ensure_announcements_table_exists();
        $announcements = fetch_announcements();
    } catch (Throwable $exception) {
        $flashError = $flashError !== '' ? $flashError : $exception->getMessage();
    }
}

$scheduleDate = null;
$scheduleDateValue = '';
$scheduleDateDescription = '';
$scheduleDateHeading = '';
$dailyScheduleReservations = [];
$scheduleEventCounts = [];
$scheduleReservationCount = 0;

if ($isLoggedIn) {
    $today = new DateTimeImmutable('today');
    $scheduleDate = $today;

    $trimmedScheduleDate = $scheduleDateInput !== null ? trim($scheduleDateInput) : '';
    if ($trimmedScheduleDate !== '') {
        $parsedScheduleDate = DateTimeImmutable::createFromFormat('Y-m-d', $trimmedScheduleDate);
        if ($parsedScheduleDate instanceof DateTimeImmutable) {
            $parseErrors = DateTimeImmutable::getLastErrors();
            if ($parseErrors === false || ($parseErrors['warning_count'] === 0 && $parseErrors['error_count'] === 0)) {
                $scheduleDate = $parsedScheduleDate;
            }
        }
    }

    $scheduleDateValue = $scheduleDate->format('Y-m-d');
    $isTodaySchedule = $scheduleDate->format('Y-m-d') === $today->format('Y-m-d');
    $scheduleDateDescription = $scheduleDate->format('l, F j, Y');
    $scheduleDateHeading = $isTodaySchedule
        ? 'Today · ' . $scheduleDate->format('F j, Y')
        : $scheduleDate->format('l · F j, Y');

    $scheduleStart = $scheduleDate->setTime(0, 0, 0);
    $scheduleEnd = $scheduleDate->setTime(23, 59, 59);

    $approvedReservations = array_filter(
        $reservations,
        static function (array $reservation): bool {
            return strtolower((string) ($reservation['status'] ?? '')) === 'approved';
        }
    );

    $dailyScheduleReservations = filter_reservations_by_date_window(
        array_values($approvedReservations),
        $scheduleStart,
        $scheduleEnd
    );

    usort(
        $dailyScheduleReservations,
        static function (array $left, array $right): int {
            $leftValue = reservation_time_sort_value($left['preferred_time'] ?? null);
            $rightValue = reservation_time_sort_value($right['preferred_time'] ?? null);

            if ($leftValue === $rightValue) {
                $leftName = strtolower(trim((string) ($left['name'] ?? '')));
                $rightName = strtolower(trim((string) ($right['name'] ?? '')));
                return $leftName <=> $rightName;
            }

            return $leftValue <=> $rightValue;
        }
    );

    foreach ($dailyScheduleReservations as $scheduledReservation) {
        $eventType = trim((string) ($scheduledReservation['event_type'] ?? ''));
        if ($eventType === '') {
            $eventType = 'Unspecified';
        }

        $scheduleEventCounts[$eventType] = ($scheduleEventCounts[$eventType] ?? 0) + 1;
    }

    ksort($scheduleEventCounts, SORT_NATURAL | SORT_FLAG_CASE);
    $scheduleReservationCount = count($dailyScheduleReservations);
}

/**
 * Render a bootstrap styled badge for the reservation status.
 */
function render_status_badge(string $status): string
{
    $badgeClasses = [
        'pending' => 'badge badge-warning',
        'approved' => 'badge badge-success',
        'declined' => 'badge badge-danger',
    ];

    $normalizedStatus = strtolower($status);
    $class = $badgeClasses[$normalizedStatus] ?? 'badge badge-secondary';
    $label = ucfirst($normalizedStatus);

    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Group reservations by status to make it easier to render the dashboard columns.
 *
 * @param array<int, array<string, mixed>> $reservations
 * @return array<string, array<int, array<string, mixed>>>
 */
function group_reservations_by_status(array $reservations): array
{
    $groups = [
        'pending' => [],
        'approved' => [],
        'declined' => [],
    ];

    foreach ($reservations as $reservation) {
        $status = strtolower((string) ($reservation['status'] ?? 'pending'));
        if (!isset($groups[$status])) {
            $groups[$status] = [];
        }
        $groups[$status][] = $reservation;
    }

    return $groups;
}

function sanitize_reservation_range(?string $range): string
{
    if ($range === null) {
        return 'all';
    }

    $allowedRanges = ['all', 'today', 'week', 'date'];
    $normalized = strtolower(trim($range));

    return in_array($normalized, $allowedRanges, true) ? $normalized : 'all';
}

/**
 * @param array<int, array<string, mixed>> $reservations
 * @return array<int, array<string, mixed>>
 */
function filter_reservations_by_date_window(
    array $reservations,
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
): array {
    $filtered = [];

    foreach ($reservations as $reservation) {
        $rawDate = $reservation['preferred_date'] ?? null;

        if ($rawDate === null || trim((string) $rawDate) === '') {
            continue;
        }

        try {
            $reservationDate = new DateTimeImmutable((string) $rawDate);
        } catch (Exception $exception) {
            continue;
        }

        $reservationDate = $reservationDate->setTime(0, 0, 0);

        if ($reservationDate < $startDate || $reservationDate > $endDate) {
            continue;
        }

        $filtered[] = $reservation;
    }

    return $filtered;
}

/**
 * Apply the requested reservation filter and produce a summary of the selection.
 *
 * @param array<int, array<string, mixed>> $reservations
 * @return array{
 *     reservations: array<int, array<string, mixed>>,
 *     range: string,
 *     date: ?string,
 *     description: string
 * }
 */
function apply_reservation_time_filter(array $reservations, ?string $rangeInput, ?string $dateInput): array
{
    $range = sanitize_reservation_range($rangeInput);
    $normalizedDate = null;
    $description = 'Showing all reservations.';
    $filteredReservations = $reservations;

    if ($range === 'today') {
        $start = (new DateTimeImmutable('today'))->setTime(0, 0, 0);
        $end = $start->setTime(23, 59, 59);
        $filteredReservations = filter_reservations_by_date_window($reservations, $start, $end);
        $description = 'Showing reservations for today (' . $start->format('M j, Y') . ').';
    }

    if ($range === 'week') {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('monday this week')->setTime(0, 0, 0);
        $end = $today->modify('sunday this week')->setTime(23, 59, 59);
        $filteredReservations = filter_reservations_by_date_window($reservations, $start, $end);
        $description = 'Showing reservations for this week ('
            . $start->format('M j')
            . ' – '
            . $end->format('M j, Y')
            . ').';
    }

    if ($range === 'date') {
        $trimmedDate = $dateInput !== null ? trim($dateInput) : '';
        if ($trimmedDate === '') {
            $range = 'all';
        } else {
            $selectedDate = DateTimeImmutable::createFromFormat('Y-m-d', $trimmedDate);
            if ($selectedDate === false) {
                $range = 'all';
            } else {
                $start = $selectedDate->setTime(0, 0, 0);
                $end = $selectedDate->setTime(23, 59, 59);
                $filteredReservations = filter_reservations_by_date_window($reservations, $start, $end);
                $normalizedDate = $selectedDate->format('Y-m-d');
                $description = 'Showing reservations for ' . $selectedDate->format('M j, Y') . '.';
            }
        }
    }

    if ($range === 'all') {
        $normalizedDate = null;
        $filteredReservations = $reservations;
        $description = 'Showing all reservations.';
    }

    return [
        'reservations' => $filteredReservations,
        'range' => $range,
        'date' => $normalizedDate,
        'description' => $description,
    ];
}

/**
 * Present a human readable date string when possible.
 */
function format_reservation_date(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '—';
    }

    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('M j, Y');
    } catch (Exception $exception) {
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Present a human readable time string when possible.
 */
function format_reservation_time(?string $time): string
{
    if ($time === null) {
        return '—';
    }

    $trimmed = trim($time);
    if ($trimmed === '') {
        return '—';
    }

    if (strpos($trimmed, '-') !== false) {
        $parts = preg_split('/\s*-\s*/', $trimmed);
        if (is_array($parts) && count($parts) >= 2) {
            $formatPart = static function (string $value): string {
                $segment = trim($value);
                if ($segment === '') {
                    return '';
                }

                $timestamp = strtotime($segment);
                if ($timestamp !== false) {
                    return date('g:i A', $timestamp);
                }

                $timeFormats = ['g:i A', 'g:iA', 'H:i', 'H:i:s'];
                foreach ($timeFormats as $format) {
                    $dateTime = DateTime::createFromFormat($format, strtoupper($segment));
                    if ($dateTime instanceof DateTime) {
                        $errors = DateTime::getLastErrors();
                        if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                            return $dateTime->format('g:i A');
                        }
                    }

                    $dateTime = DateTime::createFromFormat($format, $segment);
                    if ($dateTime instanceof DateTime) {
                        $errors = DateTime::getLastErrors();
                        if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                            return $dateTime->format('g:i A');
                        }
                    }
                }

                return htmlspecialchars($segment, ENT_QUOTES, 'UTF-8');
            };

            $start = $formatPart($parts[0]);
            $end = $formatPart($parts[1]);

            if ($start !== '' && $end !== '') {
                return $start . ' – ' . $end;
            }
        }

        return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        return date('g:i A', $timestamp);
    }

    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}

/**
 * Produce a sortable integer representing the reservation time.
 */
function reservation_time_sort_value(?string $time): int
{
    if ($time === null) {
        return 86400;
    }

    $trimmed = trim($time);
    if ($trimmed === '') {
        return 86400;
    }

    $candidate = $trimmed;
    if (preg_match('/[\-–—]/u', $trimmed)) {
        $parts = preg_split('/\s*[\-–—]\s*/u', $trimmed);
        if (is_array($parts) && isset($parts[0]) && trim((string) $parts[0]) !== '') {
            $candidate = trim((string) $parts[0]);
        }
    }

    $timeFormats = ['g:i A', 'g:iA', 'g:i a', 'g:ia', 'h:i A', 'h:iA', 'H:i', 'H:i:s', 'g A', 'ga', 'H'];
    foreach ($timeFormats as $format) {
        $dateTime = DateTime::createFromFormat($format, $candidate);
        if ($dateTime instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return ((int) $dateTime->format('G')) * 3600
                    + ((int) $dateTime->format('i')) * 60
                    + (int) $dateTime->format('s');
            }
        }
    }

    $timestamp = strtotime($candidate);
    if ($timestamp !== false) {
        return ((int) date('G', $timestamp)) * 3600
            + ((int) date('i', $timestamp)) * 60
            + (int) date('s', $timestamp);
    }

    return 86400;
}

/**
 * Format the created at timestamp for display.
 */
function format_reservation_created_at(?string $createdAt): string
{
    if ($createdAt === null || trim($createdAt) === '') {
        return '—';
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        return date('M j, Y g:i A', $timestamp);
    }

    return htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
}

/**
 * Format announcement timestamps for display.
 */
function format_announcement_created_at(?string $createdAt): string
{
    if ($createdAt === null || trim($createdAt) === '') {
        return '—';
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        return date('M j, Y g:i A', $timestamp);
    }

    return htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
}

/**
 * Prepare reservation attachments for rendering in the dashboard.
 *
 * @param array<int, array<string, mixed>> $attachments
 * @return array<int, array{path: string, file_name: string, label: string}>
 */
function prepare_reservation_attachments(array $attachments): array
{
    $prepared = [];

    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $path = isset($attachment['stored_path']) ? trim((string) $attachment['stored_path']) : '';
        $fileName = isset($attachment['file_name']) ? trim((string) $attachment['file_name']) : '';

        if ($path === '' || $fileName === '') {
            continue;
        }

        $label = isset($attachment['label']) ? trim((string) $attachment['label']) : '';

        if ($label === '' || strcasecmp($label, $fileName) === 0) {
            $label = '';
        }

        $prepared[] = [
            'path' => $path,
            'file_name' => $fileName,
            'label' => $label,
        ];
    }

    return $prepared;
}

$statusMeta = [
    'pending' => [
        'title' => 'Pending Review',
        'subtitle' => 'Reservations awaiting your decision.',
        'class' => 'status-column-pending',
        'empty' => 'No pending reservations at the moment.',
    ],
    'approved' => [
        'title' => 'Approved',
        'subtitle' => 'Confirmed reservations ready to proceed.',
        'class' => 'status-column-approved',
        'empty' => 'No reservations have been approved yet.',
    ],
    'declined' => [
        'title' => 'Declined',
        'subtitle' => 'Reservations that were not accepted.',
        'class' => 'status-column-declined',
        'empty' => 'No declined reservations.',
    ],
];

$groupedReservations = [
    'pending' => [],
    'approved' => [],
    'declined' => [],
];

$filteredGroupedReservations = [
    'pending' => [],
    'approved' => [],
    'declined' => [],
];

if ($isLoggedIn) {
    $groupedReservations = group_reservations_by_status($reservations);
    $filteredGroupedReservations = group_reservations_by_status($filteredReservations);
}

$summaryTotals = [
    'total' => $isLoggedIn ? count($reservations) : 0,
    'pending' => $isLoggedIn ? count($groupedReservations['pending']) : 0,
    'approved' => $isLoggedIn ? count($groupedReservations['approved']) : 0,
    'declined' => $isLoggedIn ? count($groupedReservations['declined']) : 0,
];

$filteredSummaryTotals = [
    'total' => $isLoggedIn ? count($filteredReservations) : 0,
    'pending' => $isLoggedIn ? count($filteredGroupedReservations['pending']) : 0,
    'approved' => $isLoggedIn ? count($filteredGroupedReservations['approved']) : 0,
    'declined' => $isLoggedIn ? count($filteredGroupedReservations['declined']) : 0,
];

$reservationCountSummary = $isLoggedIn
    ? sprintf('%d of %d reservations', $filteredSummaryTotals['total'], $summaryTotals['total'])
    : '0 reservations';

$reservationHeaderTotals = $summaryTotals;
if ($currentSection === 'reservations') {
    $reservationHeaderTotals = $filteredSummaryTotals;
}

$announcementCount = $isLoggedIn ? count($announcements) : 0;
$visibleAnnouncementCount = $isLoggedIn
    ? array_reduce($announcements, static function (int $carry, array $announcement): int {
        return $carry + ((int) ($announcement['show_on_home'] ?? 0) === 1 ? 1 : 0);
    }, 0)
    : 0;

$sectionCopy = [
    'overview' => [
        'title' => 'Administration Overview',
        'subtitle' => 'Review key activity across reservations and announcements at a glance.',
    ],
    'reservations' => [
        'title' => 'Manage Reservations',
        'subtitle' => 'Approve, decline, or follow up on reservation requests.',
    ],
    'schedule' => [
        'title' => 'Daily Schedule',
        'subtitle' => 'Review approved reservations scheduled for a specific day.',
    ],
    'announcements' => [
        'title' => 'Announcements Board',
        'subtitle' => 'Publish timely updates and control what appears on the website.',
    ],
];

$recentReservations = $isLoggedIn ? array_slice($reservations, 0, 5) : [];
$recentAnnouncements = $isLoggedIn ? array_slice($announcements, 0, 3) : [];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | St. John the Baptist Parish</title>
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f4f6fb;
            color: #1f2937;
        }

        a {
            color: inherit;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: linear-gradient(180deg, #312e81 0%, #4338ca 50%, #6366f1 100%);
            color: #e2e8f0;
            padding: 40px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 30px 60px rgba(67, 56, 202, 0.22);
            border-top-right-radius: 32px;
            border-bottom-right-radius: 32px;
        }

        .sidebar-brand {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 36px;
        }

        .sidebar-brand span {
            display: block;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.82;
            margin-top: 4px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: auto;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a:focus {
            background: rgba(255, 255, 255, 0.22);
            transform: translateX(4px);
            color: #ffffff;
        }

        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.24);
            color: #ffffff;
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.2);
            transform: translateX(6px);
        }

        .sidebar-nav a .icon {
            width: 20px;
            display: inline-flex;
            justify-content: center;
        }

        .sidebar-footer {
            margin-top: 40px;
        }

        .logout-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(248, 250, 252, 0.15);
            color: #fefefe;
            padding: 10px 18px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .logout-button:hover,
        .logout-button:focus {
            background: rgba(255, 255, 255, 0.28);
            color: #ffffff;
        }

        .admin-main {
            flex: 1;
            padding: 40px 48px;
        }

        .main-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
        }

        .main-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            color: #1e1b4b;
        }

        .header-summary {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #4338ca;
            font-weight: 600;
        }

        .flash-messages {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .flash {
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 600;
        }

        .flash-success {
            background: rgba(34, 197, 94, 0.16);
            color: #166534;
        }

        .flash-error {
            background: rgba(248, 113, 113, 0.18);
            color: #991b1b;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px 22px;
            box-shadow: 0 24px 48px rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.12);
            position: relative;
            overflow: hidden;
        }

        .summary-card h2 {
            font-size: 13px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #6366f1;
            margin: 0 0 12px;
        }

        .summary-value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
        }

        .summary-caption {
            font-size: 13px;
            color: #6b7280;
            margin-top: 8px;
        }

        .overview-panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .overview-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .overview-list li {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: #f8fafc;
        }

        .overview-list-primary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .overview-list-title {
            font-weight: 700;
            color: #1f2937;
        }

        .overview-list-name {
            font-size: 13px;
            color: #64748b;
        }

        .overview-list-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: #475569;
        }

        .overview-list-meta .badge {
            font-size: 11px;
            padding: 4px 10px;
        }

        .overview-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            font-weight: 600;
            color: #4338ca;
            text-decoration: none;
        }

        .overview-link i {
            transition: transform 0.2s ease;
        }

        .overview-link:hover i,
        .overview-link:focus i {
            transform: translateX(4px);
        }

        .section-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 26px 60px rgba(15, 23, 42, 0.08);
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 24px;
            color: #1f2937;
        }

        .section-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .reservation-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 18px;
            background: rgba(248, 250, 252, 0.6);
        }

        .reservation-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }

        .reservation-filter-form .form-group {
            margin: 0;
        }

        .reservation-filter-form label {
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .reservation-filter-form select,
        .reservation-filter-form input[type="date"] {
            min-width: 180px;
        }

        .reservation-filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .reservation-filter-actions .btn-link {
            padding-left: 0;
            padding-right: 0;
        }

        .reservation-filter-summary {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 14px;
            color: #475569;
        }

        .reservation-filter-summary strong {
            font-size: 16px;
            color: #111827;
        }

        .schedule-toolbar {
            align-items: center;
            gap: 20px;
        }

        .schedule-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }

        .schedule-filter-form .form-group {
            margin: 0;
        }

        .schedule-filter-form label {
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .schedule-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 14px;
            color: #475569;
        }

        .schedule-meta strong {
            color: #111827;
            font-size: 16px;
        }

        .schedule-event-counts {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 16px;
            padding: 0;
            list-style: none;
        }

        .schedule-event-counts li {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(79, 70, 229, 0.08);
            color: #312e81;
            font-weight: 600;
            font-size: 13px;
        }

        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .schedule-item {
            background: #ffffff;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 14px 32px rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.12);
        }

        .schedule-item-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 15px;
            color: #1f2937;
            font-weight: 600;
        }

        .schedule-item-body {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
            color: #4b5563;
        }

        .schedule-item-body span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .schedule-item-notes {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(148, 163, 184, 0.4);
            color: #475569;
            white-space: pre-line;
        }

        .reservation-date-field {
            display: flex;
            flex-direction: column;
        }

        .reservation-date-field.is-hidden {
            display: none;
        }

        .status-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .status-column {
            position: relative;
            border-radius: 20px;
            padding: 24px;
            background: linear-gradient(145deg, #ffffff 0%, #eef2ff 100%);
            box-shadow: 0 18px 40px rgba(99, 102, 241, 0.12);
            overflow: hidden;
        }

        .status-column::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0.08;
            pointer-events: none;

        }

        .status-column h3 {
            font-size: 20px;
            margin: 0 0 6px;
            color: #1e1b4b;
        }

        .status-column p {
            margin: 0 0 16px;
            font-size: 14px;
            color: #6b7280;
        }

        .status-column .empty-state {
            font-style: italic;
            color: #94a3b8;
        }

        .status-column-pending::before {
            background: linear-gradient(135deg, #f97316, #fb923c);
        }

        .status-column-approved::before {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .status-column-declined::before {
            background: linear-gradient(135deg, #ef4444, #f87171);
        }

        .reservation-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 14px 32px rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.12);
        }

        .reservation-card:last-child {
            margin-bottom: 0;
        }

        .reservation-card h4 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #1f2937;
        }

        .reservation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #4b5563;
        }

        .reservation-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .muted-text {
            color: #94a3b8;
            font-style: italic;
        }

        .reservation-meta a {
            color: #312e81;
            font-weight: 600;
            text-decoration: none;
        }

        .reservation-meta a:hover,
        .reservation-meta a:focus {
            text-decoration: underline;
        }

        .reservation-notes {
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 14px;
            white-space: pre-wrap;
        }

        .reservation-attachments {
            list-style: none;
            margin: 0 0 16px;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reservation-attachments a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.12);
            color: #312e81;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .reservation-attachments a:hover,
        .reservation-attachments a:focus {
            background: rgba(99, 102, 241, 0.2);
            color: #1e1b4b;
        }

        .status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .status-actions form {
            margin: 0;
        }

        .status-actions .btn {
            border-radius: 999px;
            padding: 6px 16px;
            font-weight: 600;
        }

        .view-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(99, 102, 241, 0.4);
            color: #312e81;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .view-link:hover,
        .view-link:focus {
            background: rgba(99, 102, 241, 0.1);
            color: #1e1b4b;
        }

        .announcement-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) minmax(280px, 1fr);
            gap: 32px;
        }

        .announcement-form {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 22px 48px rgba(15, 23, 42, 0.08);
        }

        .announcement-form .form-group label {
            font-weight: 600;
            color: #1f2937;
        }

        .announcement-form .form-check {
            margin: 16px 0;
        }

        .announcement-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .announcement-item {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .announcement-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .announcement-item-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .announcement-date {
            font-size: 13px;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-image {
            margin-top: 16px;
            border-radius: 14px;
            overflow: hidden;
            background: #0f172a;
        }

        .announcement-image img {
            display: block;
            width: 100%;
            height: auto;
        }

        .announcement-body {
            margin-top: 16px;
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
            white-space: pre-line;
        }

        .announcement-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .announcement-actions form {
            margin: 0;
        }

        .visibility-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .visibility-badge.visible {
            background: rgba(34, 197, 94, 0.16);
            color: #166534;
        }

        .visibility-badge.hidden {
            background: rgba(148, 163, 184, 0.18);
            color: #475569;
        }

        .toggle-visibility-form {
            margin-top: 18px;
        }

        .toggle-visibility-form button {
            border-radius: 999px;
            padding: 8px 18px;
            font-weight: 600;
        }

        .empty-block {
            font-style: italic;
            color: #94a3b8;
        }

        .section-anchor {
            scroll-margin-top: 90px;
        }

        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 16px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 28px 60px rgba(79, 70, 229, 0.18);
            border: 1px solid rgba(99, 102, 241, 0.14);
        }

        .login-card h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1e1b4b;
        }

        .login-card p {
            margin-bottom: 24px;
            color: #64748b;
        }

        @media (max-width: 992px) {
            .admin-layout {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                border-radius: 0;
                border-bottom-left-radius: 32px;
                border-bottom-right-radius: 32px;
                padding: 28px 24px;
            }

            .reservation-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .reservation-filter-form {
                width: 100%;
            }

            .schedule-toolbar {
                align-items: stretch;
            }

            .schedule-filter-form {
                width: 100%;
            }

            .reservation-filter-summary {
                width: 100%;
            }

            .sidebar-nav {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 12px;
            }

            .sidebar-nav a {
                flex: 1 0 180px;
                justify-content: center;
            }

            .admin-main {
                padding: 32px 20px 48px;
            }

            .overview-panels {
                grid-template-columns: 1fr;
            }

            .announcement-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .summary-card {
                padding: 18px;
            }

            .section-card {
                padding: 24px;
            }

            .status-column {
                padding: 20px;
            }

            .reservation-filter-form {
                gap: 12px;
            }

            .reservation-filter-form select,
            .reservation-filter-form input[type="date"] {
                min-width: 140px;
            }
        }
    </style>
</head>

<body>

    <?php if (!$isLoggedIn): ?>
    <div class="login-wrapper">
        <div class="login-card">
            <h1>Welcome back</h1>
            <p>Sign in to manage reservations and announcements.</p>

            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($loginError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="<?php echo ADMIN_LOGIN_ACTION; ?>">
                <input type="hidden" name="redirect_section" value="<?php echo htmlspecialchars($currentSection, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                        value="<?php echo htmlspecialchars($submittedUsername, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                St. John the Baptist Parish
                <span>Administration</span>
            </div>
            <nav class="sidebar-nav">
                <a href="<?php echo htmlspecialchars(build_admin_section_url('overview'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $currentSection === 'overview' ? 'active' : ''; ?>">
                    <span class="icon"><i class="fa fa-bar-chart"></i></span>Overview
                </a>
                <a href="<?php echo htmlspecialchars(build_admin_section_url('reservations'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $currentSection === 'reservations' ? 'active' : ''; ?>">
                    <span class="icon"><i class="fa fa-calendar"></i></span>Reservations
                </a>
                <a href="<?php echo htmlspecialchars(build_admin_section_url('schedule'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $currentSection === 'schedule' ? 'active' : ''; ?>">
                    <span class="icon"><i class="fa fa-list-alt"></i></span>Schedule
                </a>
                <a href="<?php echo htmlspecialchars(build_admin_section_url('announcements'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?php echo $currentSection === 'announcements' ? 'active' : ''; ?>">
                    <span class="icon"><i class="fa fa-bullhorn"></i></span>Announcements
                </a>
            </nav>
            <div class="sidebar-footer">
                <a class="logout-button" href="admin.php?logout=1"><i class="fa fa-sign-out"></i><span>Logout</span></a>
            </div>
        </aside>
        <main class="admin-main">
            <?php $pageCopy = $sectionCopy[$currentSection] ?? $sectionCopy['overview']; ?>
            <header class="main-header">
                <div>
                    <h1><?php echo htmlspecialchars($pageCopy['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="header-summary"><?php echo htmlspecialchars($pageCopy['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="header-meta">
                    <?php if ($currentSection === 'overview' || $currentSection === 'reservations'): ?>
                        <span><i class="fa fa-calendar-check-o"></i> <?php echo htmlspecialchars((string) $reservationHeaderTotals['approved'], ENT_QUOTES, 'UTF-8'); ?> approved</span>
                        <span><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars((string) $reservationHeaderTotals['pending'], ENT_QUOTES, 'UTF-8'); ?> pending</span>
                    <?php endif; ?>
                    <?php if ($currentSection === 'schedule'): ?>
                        <span><i class="fa fa-calendar-check-o"></i> <?php echo htmlspecialchars((string) $scheduleReservationCount, ENT_QUOTES, 'UTF-8'); ?> scheduled</span>
                    <?php endif; ?>
                    <?php if ($currentSection === 'overview' || $currentSection === 'announcements'): ?>
                        <span><i class="fa fa-bullhorn"></i> <?php echo htmlspecialchars((string) $announcementCount, ENT_QUOTES, 'UTF-8'); ?> announcements</span>
                        <span><i class="fa fa-eye"></i> <?php echo htmlspecialchars((string) $visibleAnnouncementCount, ENT_QUOTES, 'UTF-8'); ?> live</span>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($flashSuccess !== '' || $flashError !== ''): ?>
                <div class="flash-messages">
                    <?php if ($flashSuccess !== ''): ?>
                        <div class="flash flash-success">
                            <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($flashError !== ''): ?>
                        <div class="flash flash-error">
                            <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($currentSection === 'overview'): ?>
                <div class="summary-cards">
                    <div class="summary-card">
                        <h2>Total Requests</h2>
                        <div class="summary-value"><?php echo htmlspecialchars((string) $summaryTotals['total'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="summary-caption">All reservation submissions</div>
                    </div>
                    <div class="summary-card">
                        <h2>Pending</h2>
                        <div class="summary-value"><?php echo htmlspecialchars((string) $summaryTotals['pending'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="summary-caption">Awaiting review</div>
                    </div>
                    <div class="summary-card">
                        <h2>Approved</h2>
                        <div class="summary-value"><?php echo htmlspecialchars((string) $summaryTotals['approved'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="summary-caption">Ready to proceed</div>
                    </div>
                    <div class="summary-card">
                        <h2>Declined</h2>
                        <div class="summary-value"><?php echo htmlspecialchars((string) $summaryTotals['declined'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="summary-caption">Not moving forward</div>
                    </div>
                </div>

                <div class="overview-panels">
                    <section class="section-card">
                        <div class="section-header">
                            <div>
                                <h2>Recent reservations</h2>
                                <p>Latest submissions with their current status.</p>
                            </div>
                        </div>
                        <?php if (empty($recentReservations)): ?>
                            <p class="empty-block">No reservations have been submitted yet.</p>
                        <?php else: ?>
                            <ul class="overview-list">
                                <?php foreach ($recentReservations as $reservation): ?>
                                    <li>
                                        <div class="overview-list-primary">
                                            <span class="overview-list-title">Reservation #<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="overview-list-name"><?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="overview-list-meta">
                                            <span><?php echo format_reservation_date($reservation['preferred_date'] ?? null); ?></span>
                                            <span><?php echo format_reservation_time($reservation['preferred_time'] ?? null); ?></span>
                                            <?php echo render_status_badge($reservation['status'] ?? 'pending'); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a class="overview-link" href="<?php echo htmlspecialchars(build_admin_section_url('reservations'), ENT_QUOTES, 'UTF-8'); ?>">
                            Go to reservations<i class="fa fa-arrow-right"></i>
                        </a>
                    </section>
                    <section class="section-card">
                        <div class="section-header">
                            <div>
                                <h2>Announcements</h2>
                                <p>Stay up to date with the latest messages for parishioners.</p>
                            </div>
                        </div>
                        <?php if (empty($recentAnnouncements)): ?>
                            <p class="empty-block">No announcements have been posted yet.</p>
                        <?php else: ?>
                            <ul class="overview-list">
                                <?php foreach ($recentAnnouncements as $announcement): ?>
                                    <?php $isVisible = ((int) ($announcement['show_on_home'] ?? 0) === 1); ?>
                                    <li>
                                        <div class="overview-list-primary">
                                            <span class="overview-list-title"><?php echo htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="overview-list-name">Posted <?php echo format_announcement_created_at($announcement['created_at'] ?? ''); ?></span>
                                        </div>
                                        <div class="overview-list-meta">
                                            <span class="visibility-badge <?php echo $isVisible ? 'visible' : 'hidden'; ?>">
                                                <i class="fa <?php echo $isVisible ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                                <?php echo $isVisible ? 'Visible' : 'Hidden'; ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a class="overview-link" href="<?php echo htmlspecialchars(build_admin_section_url('announcements'), ENT_QUOTES, 'UTF-8'); ?>">
                            Manage announcements<i class="fa fa-arrow-right"></i>
                        </a>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($currentSection === 'schedule'): ?>
                <section class="section-card">
                    <div class="section-header">
                        <div>
                            <h2>Daily schedule</h2>
                            <p>Approved reservations scheduled for your selected day.</p>
                        </div>
                        <div class="schedule-meta">
                            <strong><?php echo htmlspecialchars($scheduleDateHeading, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars((string) $scheduleReservationCount, ENT_QUOTES, 'UTF-8'); ?> reservations</span>
                        </div>
                    </div>

                    <div class="reservation-toolbar schedule-toolbar">
                        <form method="get" action="admin.php" class="schedule-filter-form">
                            <input type="hidden" name="section" value="schedule">
                            <div class="form-group">
                                <label for="schedule_date">Choose date</label>
                                <input type="date" class="form-control" id="schedule_date" name="schedule_date"
                                    value="<?php echo htmlspecialchars($scheduleDateValue, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="reservation-filter-actions">
                                <button type="submit" class="btn btn-primary">Update</button>
                                <a class="btn btn-link" href="<?php echo htmlspecialchars(build_admin_section_url('schedule'), ENT_QUOTES, 'UTF-8'); ?>">Today</a>
                            </div>
                        </form>
                        <div class="schedule-meta">
                            <span>Viewing <?php echo htmlspecialchars($scheduleDateDescription, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($scheduleEventCounts)): ?>
                        <ul class="schedule-event-counts">
                            <?php foreach ($scheduleEventCounts as $eventLabel => $count): ?>
                                <li>
                                    <i class="fa fa-tag"></i>
                                    <span><?php echo htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>· <?php echo htmlspecialchars((string) $count, ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (empty($dailyScheduleReservations)): ?>
                        <p class="empty-block">No approved reservations are scheduled for <?php echo htmlspecialchars($scheduleDateDescription, ENT_QUOTES, 'UTF-8'); ?>.</p>
                    <?php else: ?>
                        <div class="schedule-list">
                            <?php foreach ($dailyScheduleReservations as $scheduledReservation): ?>
                                <?php
                                $reservationId = isset($scheduledReservation['id']) ? (string) $scheduledReservation['id'] : '';
                                $reservationName = trim((string) ($scheduledReservation['name'] ?? ''));
                                $reservationEventType = trim((string) ($scheduledReservation['event_type'] ?? ''));
                                $reservationEventType = $reservationEventType !== '' ? $reservationEventType : 'Unspecified';
                                $reservationTimeDisplay = format_reservation_time($scheduledReservation['preferred_time'] ?? null);
                                $reservationDateDisplay = format_reservation_date($scheduledReservation['preferred_date'] ?? null);
                                $reservationEmail = trim((string) ($scheduledReservation['email'] ?? ''));
                                $reservationPhone = trim((string) ($scheduledReservation['phone'] ?? ''));
                                $reservationNotes = trim((string) ($scheduledReservation['notes'] ?? ''));
                                ?>
                                <div class="schedule-item">
                                    <div class="schedule-item-header">
                                        <span><i class="fa fa-clock-o"></i> <?php echo $reservationTimeDisplay; ?></span>
                                        <span><i class="fa fa-bookmark"></i> <?php echo htmlspecialchars($reservationEventType, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="schedule-item-body">
                                        <span><i class="fa fa-id-badge"></i> Reservation #<?php echo htmlspecialchars($reservationId, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($reservationName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><i class="fa fa-calendar"></i> <?php echo $reservationDateDisplay; ?></span>
                                        <span>
                                            <i class="fa fa-envelope"></i>
                                            <?php if ($reservationEmail !== ''): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($reservationEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservationEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else: ?>
                                                <span class="muted-text">Not provided</span>
                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            <i class="fa fa-phone"></i>
                                            <?php if ($reservationPhone !== ''): ?>
                                                <a href="tel:<?php echo htmlspecialchars($reservationPhone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservationPhone, ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else: ?>
                                                <span class="muted-text">Not provided</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($reservationNotes !== ''): ?>
                                        <div class="schedule-item-notes"><?php echo nl2br(htmlspecialchars($reservationNotes, ENT_QUOTES, 'UTF-8')); ?></div>
                                    <?php endif; ?>
                                    <a class="view-link" href="reservation_view.php?id=<?php echo htmlspecialchars($reservationId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fa fa-eye"></i>
                                        <span>View reservation</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($currentSection === 'reservations'): ?>
                <section class="section-card">
                    <div class="section-header">
                        <div>
                            <h2>Reservations</h2>
                            <p>Review reservation requests grouped by their current status.</p>
                        </div>
                        <div class="header-meta">
                            <span><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars((string) $filteredSummaryTotals['pending'], ENT_QUOTES, 'UTF-8'); ?> pending</span>
                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <div class="reservation-toolbar">
                            <form method="get" action="admin.php" class="reservation-filter-form">
                                <input type="hidden" name="section" value="reservations">
                                <div class="form-group">
                                    <label for="reservation_filter_range">Show</label>
                                    <select class="form-control form-control-sm" id="reservation_filter_range" name="range"
                                        data-reservation-filter-range>
                                        <option value="all" <?php echo $reservationFilterRange === 'all' ? 'selected' : ''; ?>>All reservations</option>
                                        <option value="today" <?php echo $reservationFilterRange === 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="week" <?php echo $reservationFilterRange === 'week' ? 'selected' : ''; ?>>This week</option>
                                        <option value="date" <?php echo $reservationFilterRange === 'date' ? 'selected' : ''; ?>>Specific date</option>
                                    </select>
                                </div>
                                <div class="form-group reservation-date-field" data-reservation-filter-date-wrapper>
                                    <label for="reservation_filter_date">Date</label>
                                    <input type="date" class="form-control form-control-sm" id="reservation_filter_date" name="date"
                                        value="<?php echo htmlspecialchars((string) ($reservationFilterDate ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="reservation-filter-actions">
                                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                    <?php if ($hasActiveReservationFilter): ?>
                                        <a class="btn btn-link btn-sm" href="<?php echo htmlspecialchars(build_admin_section_url('reservations'), ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <div class="reservation-filter-summary">
                                <strong><?php echo htmlspecialchars($reservationCountSummary, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($reservationFilterDescription, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($filteredSummaryTotals['total'] === 0): ?>
                        <p class="empty-block">
                            <?php if ($summaryTotals['total'] === 0): ?>
                                No reservations have been submitted yet.
                            <?php else: ?>
                                No reservations match the selected filters.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div class="status-columns">
                            <?php foreach ($statusMeta as $statusKey => $meta): ?>
                                <div class="status-column <?php echo htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <h3><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>

                                    <?php if (count($filteredGroupedReservations[$statusKey]) === 0): ?>
                                        <p class="empty-state"><?php echo htmlspecialchars($meta['empty'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php else: ?>
                                        <?php foreach ($filteredGroupedReservations[$statusKey] as $reservation): ?>
                                            <div class="reservation-card">
                                                <div class="status-badge"><?php echo render_status_badge($reservation['status']); ?></div>
                                                <h4>Reservation #<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <?php
                                                $emailValue = trim((string) ($reservation['email'] ?? ''));
                                                $phoneValue = trim((string) ($reservation['phone'] ?? ''));
                                                ?>
                                                <div class="reservation-meta">
                                                    <span>
                                                        <i class="fa fa-envelope"></i>
                                                        <?php if ($emailValue !== ''): ?>
                                                            <a href="mailto:<?php echo htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?></a>
                                                        <?php else: ?>
                                                            <span class="muted-text">Not provided</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span>
                                                        <i class="fa fa-phone"></i>
                                                        <?php if ($phoneValue !== ''): ?>
                                                            <a href="tel:<?php echo htmlspecialchars($phoneValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($phoneValue, ENT_QUOTES, 'UTF-8'); ?></a>
                                                        <?php else: ?>
                                                            <span class="muted-text">Not provided</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span><i class="fa fa-calendar"></i><?php echo format_reservation_date($reservation['preferred_date'] ?? null); ?></span>
                                                    <span><i class="fa fa-clock-o"></i><?php echo format_reservation_time($reservation['preferred_time'] ?? null); ?></span>
                                                </div>
                                                <?php if (trim((string) ($reservation['notes'] ?? '')) !== ''): ?>
                                                    <div class="reservation-notes"><?php echo nl2br(htmlspecialchars((string) $reservation['notes'], ENT_QUOTES, 'UTF-8')); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($reservation['attachments'])): ?>
                                                    <?php $preparedAttachments = prepare_reservation_attachments($reservation['attachments']); ?>
                                                    <?php if (!empty($preparedAttachments)): ?>
                                                        <ul class="reservation-attachments">
                                                            <?php
                                                            $genericAttachmentIndex = 0;
                                                            $genericAttachmentCount = count($preparedAttachments);
                                                            ?>
                                                            <?php foreach ($preparedAttachments as $preparedAttachment): ?>
                                                                <?php
                                                                $attachmentDisplayLabel = $preparedAttachment['label'];

                                                                if ($attachmentDisplayLabel === '') {
                                                                    $genericAttachmentIndex++;
                                                                    $attachmentDisplayLabel = 'Download attachment';

                                                                    if ($genericAttachmentCount > 1) {
                                                                        $attachmentDisplayLabel .= ' #' . $genericAttachmentIndex;
                                                                    }
                                                                }
                                                                ?>
                                                                <li>
                                                                    <a href="<?php echo htmlspecialchars($preparedAttachment['path'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                        download="<?php echo htmlspecialchars($preparedAttachment['file_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                        target="_blank" rel="noopener"
                                                                        title="<?php echo htmlspecialchars($attachmentDisplayLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <i class="fa fa-paperclip"></i>
                                                                        <span><?php echo htmlspecialchars($attachmentDisplayLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <div class="status-actions">
                                                    <a class="view-link" href="reservation_view.php?id=<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <i class="fa fa-eye"></i>
                                                        <span>View details</span>
                                                    </a>
                                                    <?php if ($reservation['status'] !== 'approved'): ?>
                                                        <form method="post" action="admin.php">
                                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <input type="hidden" name="redirect_section" value="reservations">
                                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($reservation['status'] !== 'declined'): ?>
                                                        <form method="post" action="admin.php">
                                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="status" value="declined">
                                                            <input type="hidden" name="redirect_section" value="reservations">
                                                            <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($reservation['status'] !== 'pending'): ?>
                                                        <form method="post" action="admin.php">
                                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="status" value="pending">
                                                            <input type="hidden" name="redirect_section" value="reservations">
                                                            <button type="submit" class="btn btn-secondary btn-sm">Mark Pending</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($currentSection === 'announcements'): ?>
                <section class="section-card">
                    <div class="section-header">
                        <div>
                            <h2>Create announcements</h2>
                            <p>Share news with parishioners and control visibility with one click.</p>
                        </div>
                    </div>
                    <div class="announcement-grid">
                        <div class="announcement-form">
                            <h3 class="h5 mb-3">Compose a message</h3>
                            <form method="post" action="admin.php" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="<?php echo ADMIN_ANNOUNCEMENT_CREATE_ACTION; ?>">
                                <input type="hidden" name="redirect_section" value="announcements">
                                <div class="form-group">
                                    <label for="announcement_title">Title</label>
                                    <input type="text" class="form-control" id="announcement_title" name="announcement_title" maxlength="150" required>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_body">Message</label>
                                    <textarea class="form-control" id="announcement_body" name="announcement_body" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_image">Image (optional)</label>
                                    <input type="file" class="form-control" id="announcement_image" name="announcement_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="announcement_show" name="announcement_show" value="1">
                                    <label class="form-check-label" for="announcement_show">Show this on the home page</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">Publish announcement</button>
                            </form>
                        </div>
                        <div class="announcement-list">
                            <?php if ($announcementCount === 0): ?>
                                <p class="empty-block">No announcements have been posted yet.</p>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <?php $isVisible = ((int) ($announcement['show_on_home'] ?? 0) === 1); ?>
                                    <div class="announcement-item">
                                        <div class="announcement-item-header">
                                            <h3><?php echo htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <span class="announcement-date"><i class="fa fa-calendar"></i> <?php echo format_announcement_created_at($announcement['created_at'] ?? ''); ?></span>
                                        </div>
                                        <?php if (!empty($announcement['image_path'])): ?>
                                            <div class="announcement-image">
                                                <img src="<?php echo htmlspecialchars($announcement['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Announcement image">
                                            </div>
                                        <?php endif; ?>
                                        <div class="announcement-body">
                                            <?php echo nl2br(htmlspecialchars($announcement['body'], ENT_QUOTES, 'UTF-8')); ?>
                                        </div>
                                        <div class="announcement-controls">
                                            <span class="visibility-badge <?php echo $isVisible ? 'visible' : 'hidden'; ?>">
                                                <i class="fa <?php echo $isVisible ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                                <?php echo $isVisible ? 'Visible on home page' : 'Hidden from home page'; ?>
                                            </span>
                                            <div class="announcement-actions">
                                                <form method="post" action="admin.php">
                                                    <input type="hidden" name="action" value="<?php echo ADMIN_ANNOUNCEMENT_TOGGLE_ACTION; ?>">
                                                    <input type="hidden" name="announcement_id" value="<?php echo htmlspecialchars((string) $announcement['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="show_on_home" value="<?php echo $isVisible ? '0' : '1'; ?>">
                                                    <input type="hidden" name="redirect_section" value="announcements">
                                                    <button type="submit" class="btn btn-<?php echo $isVisible ? 'secondary' : 'success'; ?> btn-sm">
                                                        <?php echo $isVisible ? 'Hide from home' : 'Show on home'; ?>
                                                    </button>
                                                </form>
                                                <form method="post" action="admin.php" onsubmit="return confirm('Delete this announcement? This action cannot be undone.');">
                                                    <input type="hidden" name="action" value="<?php echo ADMIN_ANNOUNCEMENT_DELETE_ACTION; ?>">
                                                    <input type="hidden" name="announcement_id" value="<?php echo htmlspecialchars((string) $announcement['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="redirect_section" value="announcements">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="fa fa-trash"></i><span>Delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>
    <script src="js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="js/vendor/jquery-1.12.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rangeSelect = document.querySelector('[data-reservation-filter-range]');
            var dateWrapper = document.querySelector('[data-reservation-filter-date-wrapper]');

            if (!rangeSelect || !dateWrapper) {
                return;
            }

            var toggleDateField = function () {
                var shouldShow = rangeSelect.value === 'date';
                dateWrapper.classList.toggle('is-hidden', !shouldShow);
            };

            toggleDateField();
            rangeSelect.addEventListener('change', toggleDateField);
        });
    </script>
</body>

</html>
