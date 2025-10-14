<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/sms_notifications.php';

customer_session_start();

$loggedInCustomer = get_logged_in_customer();
$customerIsLoggedIn = $loggedInCustomer !== null;

const RESERVATION_UNKNOWN_SLOT = '__unknown__';

/**
 * Normalize a notification payload for front-end SweetAlert display.
 *
 * @param array<string, mixed> $notification
 * @return array<string, string>|null
 */
function prepare_notification(array $notification): ?array
{
    $allowedIcons = ['success', 'error', 'warning', 'info', 'question'];
    $icon = isset($notification['icon']) && in_array($notification['icon'], $allowedIcons, true)
        ? (string) $notification['icon']
        : 'info';

    $title = isset($notification['title']) ? trim((string) $notification['title']) : '';
    $text = isset($notification['text']) ? trim((string) $notification['text']) : '';

    if ($title === '' && $text === '') {
        return null;
    }

    $normalized = ['icon' => $icon];

    if ($title !== '') {
        $normalized['title'] = $title;
    }

    if ($text !== '') {
        $normalized['text'] = $text;
    }

    return $normalized;
}

/**
 * Normalize whitespace for a single name component.
 */
function normalize_name_component(string $value): string
{
    $normalized = preg_replace('/\s+/u', ' ', $value);
    if ($normalized === null) {
        $normalized = $value;
    }

    return trim($normalized);
}

/**
 * Combine individual name components into a single formatted string.
 */
function format_reservation_full_name(string $first, string $middle, string $last, string $suffix): string
{
    $nameParts = array_filter([$first, $middle, $last], static function ($part): bool {
        return $part !== '';
    });

    $fullName = implode(' ', $nameParts);

    if ($fullName !== '' && $suffix !== '') {
        return $fullName . ', ' . $suffix;
    }

    if ($fullName === '' && $suffix !== '') {
        return $suffix;
    }

    return $fullName;
}

/**
 * Split a full name string into first, middle, last, and suffix components.
 *
 * @return array{first: string, middle: string, last: string, suffix: string}
 */
function split_reservation_full_name(string $fullName): array
{
    $normalized = normalize_name_component($fullName);
    $components = [
        'first' => '',
        'middle' => '',
        'last' => '',
        'suffix' => '',
    ];

    if ($normalized === '') {
        return $components;
    }

    $commaParts = array_map('trim', explode(',', $normalized));
    if (count($commaParts) > 1) {
        $components['suffix'] = array_pop($commaParts);
        $normalized = implode(' ', $commaParts);
        $normalized = normalize_name_component($normalized);
    }

    $tokens = $normalized !== '' ? preg_split('/\s+/u', $normalized) : [];
    if (!is_array($tokens) || count($tokens) === 0) {
        return $components;
    }

    $suffixPatterns = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v', 'vi'];
    $lastToken = end($tokens);
    if ($lastToken !== false) {
        $lastTokenNormalized = strtolower(rtrim((string) $lastToken, '.'));
        if (in_array($lastTokenNormalized, $suffixPatterns, true)) {
            $components['suffix'] = $components['suffix'] !== '' ? $components['suffix'] : (string) array_pop($tokens);
        }
    }

    $tokenCount = count($tokens);
    if ($tokenCount === 1) {
        $components['first'] = (string) $tokens[0];
        return array_map('normalize_name_component', $components);
    }

    if ($tokenCount >= 2) {
        $components['first'] = (string) array_shift($tokens);
        $components['last'] = (string) array_pop($tokens);
        if (!empty($tokens)) {
            $components['middle'] = implode(' ', $tokens);
        }
    }

    return array_map('normalize_name_component', $components);
}

/**
 * Ensure form data includes normalized name components and a combined full name.
 *
 * @param array<string, mixed> $formData
 * @return void
 */
/**
 * Normalize name component fields and update the combined full name value.
 *
 * @param array<string, mixed> $formData
 * @param string $fieldPrefix Base key for component fields (e.g. `reservation-name`).
 * @param string $combinedField Target key for the combined full name string.
 * @return void
 */
function update_form_name_components(array &$formData, string $fieldPrefix, string $combinedField): void
{
    $firstKey = $fieldPrefix . '-first';
    $middleKey = $fieldPrefix . '-middle';
    $lastKey = $fieldPrefix . '-last';
    $suffixKey = $fieldPrefix . '-suffix';

    $first = normalize_name_component((string) ($formData[$firstKey] ?? ''));
    $middle = normalize_name_component((string) ($formData[$middleKey] ?? ''));
    $last = normalize_name_component((string) ($formData[$lastKey] ?? ''));
    $suffix = normalize_name_component((string) ($formData[$suffixKey] ?? ''));

    $formData[$firstKey] = $first;
    $formData[$middleKey] = $middle;
    $formData[$lastKey] = $last;
    $formData[$suffixKey] = $suffix;
    $formData[$combinedField] = format_reservation_full_name($first, $middle, $last, $suffix);
}

function update_reservation_full_name(array &$formData): void
{
    update_form_name_components($formData, 'reservation-name', 'reservation-name');
}

/**
 * Normalize and combine related reservation names (wedding couple and deceased).
 *
 * @param array<string, mixed> $formData
 * @return void
 */
function update_reservation_related_names(array &$formData): void
{
    update_form_name_components($formData, 'wedding-bride-name', 'wedding-bride-name');
    update_form_name_components($formData, 'wedding-groom-name', 'wedding-groom-name');
    update_form_name_components($formData, 'funeral-deceased-name', 'funeral-deceased-name');
}

$flashNotification = null;
if (isset($_SESSION['customer_flash_notification']) && is_array($_SESSION['customer_flash_notification'])) {
    $normalizedFlash = prepare_notification($_SESSION['customer_flash_notification']);
    if ($normalizedFlash !== null) {
        $flashNotification = $normalizedFlash;
    }
    unset($_SESSION['customer_flash_notification']);
}

/**
 * Render a consistently styled table row for reservation email summaries.
 */
function render_reservation_email_detail_row(string $label, string $value, bool $valueContainsHtml = false): string
{
    $trimmedValue = $valueContainsHtml ? trim(strip_tags($value)) : trim($value);
    if ($trimmedValue === '') {
        return '';
    }

    $labelCell = '<td style="padding:16px 24px; border-bottom:1px solid #e2e8f0; width:38%;'
        . ' font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; font-weight:700;'
        . ' vertical-align:top;">' . $label . '</td>';

    $valueCellStyles = 'padding:16px 24px; border-bottom:1px solid #e2e8f0; font-size:15px; color:#0f172a;'
        . ' font-weight:600; line-height:1.6;';
    if ($valueContainsHtml) {
        $valueCellStyles .= ' font-weight:500;';
    }

    $valueCell = '<td style="' . $valueCellStyles . '">' . $value . '</td>';

    return '<tr>' . $labelCell . $valueCell . '</tr>';
}

/**
 * Render a section heading row used to separate grouped reservation details.
 */
function render_reservation_email_section_heading(string $heading): string
{
    return '<tr><td colspan="2" style="padding:24px 24px 8px; border-top:1px solid #e2e8f0;'
        . ' font-size:11px; letter-spacing:0.16em; text-transform:uppercase; color:#4f46e5; font-weight:700;">'
        . $heading . '</td></tr>';
}

/**
 * Build reservation detail sections used in email notifications.
 *
 * @param array<string, mixed> $reservationDetails
 * @return array{
 *     wedding_html: string,
 *     wedding_alt: array<int, string>,
 *     funeral_html: string,
 *     funeral_alt: array<int, string>,
 *     attachments_html: string,
 *     attachments_alt: array<int, string>
 * }
 */
function build_reservation_notification_sections(array $reservationDetails): array
{
    $sections = [
        'wedding_html' => '',
        'wedding_alt' => [],
        'funeral_html' => '',
        'funeral_alt' => [],
        'attachments_html' => '',
        'attachments_alt' => [],
    ];

    if (!empty($reservationDetails['wedding_details']) && is_array($reservationDetails['wedding_details'])) {
        $weddingDetails = $reservationDetails['wedding_details'];

        $brideName = isset($weddingDetails['bride_name']) ? (string) $weddingDetails['bride_name'] : '';
        $groomName = isset($weddingDetails['groom_name']) ? (string) $weddingDetails['groom_name'] : '';
        $seminarDate = isset($weddingDetails['seminar_date']) ? (string) $weddingDetails['seminar_date'] : '';
        $sacramentDetails = isset($weddingDetails['sacrament_details']) ? (string) $weddingDetails['sacrament_details'] : '';
        $requirementLabels = [];

        if (!empty($weddingDetails['requirements']) && is_array($weddingDetails['requirements'])) {
            foreach ($weddingDetails['requirements'] as $label) {
                $trimmedLabel = trim((string) $label);
                if ($trimmedLabel !== '') {
                    $requirementLabels[] = $trimmedLabel;
                }
            }
        }

        $weddingDetailsRows = '';
        if ($brideName !== '') {
            $weddingDetailsRows .= render_reservation_email_detail_row('Bride', $brideName);
            $sections['wedding_alt'][] = 'Bride: ' . html_entity_decode(strip_tags($brideName), ENT_QUOTES, 'UTF-8');
        }
        if ($groomName !== '') {
            $weddingDetailsRows .= render_reservation_email_detail_row('Groom', $groomName);
            $sections['wedding_alt'][] = 'Groom: ' . html_entity_decode(strip_tags($groomName), ENT_QUOTES, 'UTF-8');
        }

        if ($seminarDate !== '') {
            $weddingDetailsRows .= render_reservation_email_detail_row('Seminar date', $seminarDate);
            $sections['wedding_alt'][] = 'Seminar date: ' . html_entity_decode(strip_tags($seminarDate), ENT_QUOTES, 'UTF-8');
        }

        if ($sacramentDetails !== '') {
            $weddingDetailsRows .= render_reservation_email_detail_row('Kumpisa/Kumpil/Binyag', $sacramentDetails);
            $sections['wedding_alt'][] = 'Kumpisa/Kumpil/Binyag: ' . html_entity_decode(strip_tags($sacramentDetails), ENT_QUOTES, 'UTF-8');
        }

        if (!empty($requirementLabels)) {
            $requirementItems = '';
            foreach ($requirementLabels as $requirementLabel) {
                $requirementItems .= '<li style="margin:0 0 6px; color:#0f172a;">' . $requirementLabel . '</li>';
                $sections['wedding_alt'][] = 'Requirement confirmed: ' . html_entity_decode(strip_tags($requirementLabel), ENT_QUOTES, 'UTF-8');
            }
            $weddingDetailsRows .= render_reservation_email_detail_row(
                'Confirmed requirements',
                '<ul style="margin:0; padding-left:18px; font-size:14px; font-weight:500; color:#0f172a; line-height:1.5;">'
                . $requirementItems . '</ul>',
                true
            );
        }

        if ($weddingDetailsRows !== '') {
            $sections['wedding_html'] = render_reservation_email_section_heading('Wedding information')
                . $weddingDetailsRows;
        }
    }

    if (!empty($reservationDetails['funeral_details']) && is_array($reservationDetails['funeral_details'])) {
        $funeralDetails = $reservationDetails['funeral_details'];

        $deceasedName = isset($funeralDetails['deceased_name']) ? (string) $funeralDetails['deceased_name'] : '';
        $maritalStatus = isset($funeralDetails['marital_status']) ? (string) $funeralDetails['marital_status'] : '';
        $officeReminder = isset($funeralDetails['office_reminder']) ? (string) $funeralDetails['office_reminder'] : '';

        $funeralDetailsRows = '';
        if ($deceasedName !== '') {
            $funeralDetailsRows .= render_reservation_email_detail_row('Deceased', $deceasedName);
            $sections['funeral_alt'][] = 'Deceased: ' . html_entity_decode(strip_tags($deceasedName), ENT_QUOTES, 'UTF-8');
        }
        if ($maritalStatus !== '') {
            $funeralDetailsRows .= render_reservation_email_detail_row('Marital status', $maritalStatus);
            $sections['funeral_alt'][] = 'Marital status: ' . html_entity_decode(strip_tags($maritalStatus), ENT_QUOTES, 'UTF-8');
        }
        if ($officeReminder !== '') {
            $funeralDetailsRows .= render_reservation_email_detail_row('Parish office reminder', $officeReminder);
            $sections['funeral_alt'][] = 'Parish office reminder: ' . html_entity_decode(strip_tags($officeReminder), ENT_QUOTES, 'UTF-8');
        }

        if ($funeralDetailsRows !== '') {
            $sections['funeral_html'] = render_reservation_email_section_heading('Funeral information')
                . $funeralDetailsRows;
        }
    }

    if (!empty($reservationDetails['attachments']) && is_array($reservationDetails['attachments'])) {
        $attachmentItems = '';
        foreach ($reservationDetails['attachments'] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $label = isset($attachment['label']) ? htmlspecialchars((string) $attachment['label'], ENT_QUOTES, 'UTF-8') : 'Attachment';
            $filename = isset($attachment['filename']) ? htmlspecialchars((string) $attachment['filename'], ENT_QUOTES, 'UTF-8') : '';
            if ($filename === '') {
                continue;
            }

            $attachmentItems .= '<li style="margin:0 0 6px; color:#0f172a;">' . $label
                . ($filename !== '' ? ' &ndash; ' . $filename : '') . '</li>';
            $sections['attachments_alt'][] = ($label !== '' ? $label . ': ' : '') . html_entity_decode($filename, ENT_QUOTES, 'UTF-8');
        }

        if ($attachmentItems !== '') {
            $sections['attachments_html'] = render_reservation_email_detail_row(
                'Uploaded documents',
                '<ul style="margin:0; padding-left:18px; font-size:14px; font-weight:500; color:#0f172a; line-height:1.6;">'
                . $attachmentItems . '</ul>',
                true
            );
        }
    }

    return $sections;
}

/**
 * Build the common reservation summary rows shared across notification emails.
 *
 * @param array<string, mixed> $reservationDetails
 * @param array<string, string> $sections
 */
function build_reservation_summary_rows(array $reservationDetails, array $sections): string
{
    $rows = '';

    $rows .= render_reservation_email_detail_row('Name', (string) ($reservationDetails['name'] ?? ''));
    $rows .= render_reservation_email_detail_row('Email', (string) ($reservationDetails['email'] ?? ''));
    $rows .= render_reservation_email_detail_row('Phone', (string) ($reservationDetails['phone'] ?? ''));
    $rows .= render_reservation_email_detail_row('Event type', (string) ($reservationDetails['event_type'] ?? ''));
    $rows .= render_reservation_email_detail_row('Preferred date', (string) ($reservationDetails['preferred_date'] ?? ''));
    $rows .= render_reservation_email_detail_row('Preferred time', (string) ($reservationDetails['preferred_time'] ?? ''));

    if (!empty($sections['wedding_html'])) {
        $rows .= $sections['wedding_html'];
    }

    if (!empty($sections['funeral_html'])) {
        $rows .= $sections['funeral_html'];
    }

    $rows .= render_reservation_email_detail_row('Notes', (string) ($reservationDetails['notes_html'] ?? ''), true);

    if (!empty($sections['attachments_html'])) {
        $rows .= $sections['attachments_html'];
    }

    return $rows;
}

function send_reservation_notification_email(array $reservationDetails, $adminUrl)
{

    $mail = new PHPMailer(true);

    $smtpUsername = 'gospelbaracael@gmail.com';
    $smtpPassword = 'nbawqssfjeovyaxv';
    $senderAddress = $smtpUsername;
    $senderName = 'St. John the Baptist Parish Reservations';

    $notificationRecipient = 'gospelbaracael@gmail.com';
    $notificationSubject = 'New reservation submitted';

    $escapedAdminUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');

    $sections = build_reservation_notification_sections($reservationDetails);
    $summaryRows = build_reservation_summary_rows($reservationDetails, $sections);

    $preferredDateDisplay = trim(strip_tags((string) ($reservationDetails['preferred_date'] ?? '')));
    $preferredDateDisplay = $preferredDateDisplay !== ''
        ? (string) $reservationDetails['preferred_date']
        : 'To be confirmed';

    $preferredTimeDisplay = trim(strip_tags((string) ($reservationDetails['preferred_time'] ?? '')));
    $preferredTimeDisplay = $preferredTimeDisplay !== ''
        ? (string) $reservationDetails['preferred_time']
        : 'To be confirmed';

    $eventTypeBadge = trim(strip_tags((string) ($reservationDetails['event_type'] ?? '')));
    $eventTypeBadge = $eventTypeBadge !== '' ? (string) $reservationDetails['event_type'] : 'Reservation';

    $summaryTable = '<div style="border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; margin:0 0 24px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . '<tr><td colspan="2" style="padding:18px 24px; background-color:#f8fafc; font-size:12px; letter-spacing:0.08em; '
        . 'text-transform:uppercase; color:#475569; font-weight:700;">Reservation summary</td></tr>'
        . $summaryRows
        . '</table>'
        . '</div>';

    $infoCards = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">'
        . '<tr>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:#eef2ff; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:#4338ca; font-weight:700;">'
        . 'Reservation date</div>'
        . '<div style="margin-top:10px; font-size:18px; font-weight:700; color:#1e1b4b;">' . $preferredDateDisplay . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:#e0f2fe; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:#0369a1; font-weight:700;">'
        . 'Preferred time</div>'
        . '<div style="margin-top:10px; font-size:18px; font-weight:700; color:#0c4a6e;">' . $preferredTimeDisplay . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>';

    $notificationMessage = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>New reservation submitted</title>'
        . '<style type="text/css">@media screen and (max-width:520px){.stack-column{display:block!important;'
        . 'width:100%!important;max-width:100%!important;}}</style>'
        . '</head>'
        . '<body style="margin:0; background-color:#f5f7fb; font-family:\'Segoe UI\', Arial, sans-serif; color:#1f2937;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fb;">'
        . '<tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="max-width:600px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 28px 55px '
        . 'rgba(15,23,42,0.15);">'
        . '<tr><td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 50%,#60a5fa 100%); padding:36px 32px; color:#ffffff;">'
        . '<div style="font-size:12px; letter-spacing:0.22em; text-transform:uppercase; opacity:0.85;">New reservation</div>'
        . '<div style="margin-top:12px; font-size:26px; font-weight:700;">A new reservation just arrived</div>'
        . '<p style="margin:18px 0 0; font-size:16px; line-height:1.6; opacity:0.92;">Review the request below and follow up when you\'re ready.</p>'
        . '<span style="display:inline-block; margin-top:24px; padding:8px 18px; background:rgba(255,255,255,0.22); border-radius:999px;'
        . ' font-size:12px; letter-spacing:0.12em; text-transform:uppercase; font-weight:600;">' . $eventTypeBadge . '</span>'
        . '</td></tr>'
        . '<tr><td style="padding:32px 32px 16px; color:#334155;">'
        . '<p style="margin:0 0 18px; font-size:16px; line-height:1.6;">Hi there,</p>'
        . '<p style="margin:0 0 24px; font-size:16px; line-height:1.6;">A new reservation was submitted on the parish website. '
        . 'Use the summary below to review the request and coordinate the next steps.</p>'
        . '<div style="margin:0 0 24px;"><span style="display:inline-block; padding:8px 16px; background-color:#e0e7ff; color:#3730a3; '
        . 'font-size:12px; letter-spacing:0.1em; text-transform:uppercase; font-weight:700; border-radius:999px;">Awaiting review</span></div>'
        . $infoCards
        . $summaryTable
        . '<p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#475569;">Need to update the reservation? You can manage every detail from the dashboard.</p>'
        . '<p style="margin:24px 0 32px; text-align:center;">'
        . '<a href="' . $escapedAdminUrl . '" style="display:inline-block; padding:14px 28px; background:linear-gradient(135deg,#4f46e5,#2563eb); color:#ffffff;'
        . ' text-decoration:none; border-radius:999px; font-weight:700; letter-spacing:0.05em;">Open admin dashboard</a>'
        . '</p>'
        . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#475569;">Reply directly to this email if you need to get in touch with the requester.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 32px 32px; background-color:#f8fafc; text-align:center; font-size:12px; color:#94a3b8;">'
        . 'St. John the Baptist Parish &bull; Reservation desk</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';

    $altBodyLines = [
        'We just received a new booking!',
        'Status: Awaiting review',
        '',
        'Name: ' . html_entity_decode(strip_tags($reservationDetails['name']), ENT_QUOTES, 'UTF-8'),
        'Email: ' . html_entity_decode(strip_tags($reservationDetails['email']), ENT_QUOTES, 'UTF-8'),
        'Phone: ' . html_entity_decode(strip_tags($reservationDetails['phone']), ENT_QUOTES, 'UTF-8'),
        'Event type: ' . html_entity_decode(strip_tags($reservationDetails['event_type']), ENT_QUOTES, 'UTF-8'),
        'Preferred date: ' . html_entity_decode(strip_tags($reservationDetails['preferred_date']), ENT_QUOTES, 'UTF-8'),
        'Preferred time: ' . html_entity_decode(strip_tags($reservationDetails['preferred_time']), ENT_QUOTES, 'UTF-8'),
        'Notes: ' . html_entity_decode($reservationDetails['notes_text'], ENT_QUOTES, 'UTF-8'),
        '',
        'Open the admin dashboard to manage the booking: ' . html_entity_decode(strip_tags($adminUrl), ENT_QUOTES, 'UTF-8'),
    ];

    if (!empty($sections['wedding_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Wedding information:';
        foreach ($sections['wedding_alt'] as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($sections['funeral_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Funeral information:';
        foreach ($sections['funeral_alt'] as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($sections['attachments_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Uploaded documents:';
        foreach ($sections['attachments_alt'] as $line) {
            $altBodyLines[] = ' - ' . html_entity_decode($line, ENT_QUOTES, 'UTF-8');
        }
    }

    if ($smtpUsername === 'yourgmail@gmail.com' || $smtpPassword === 'your_app_password') {
        error_log('Reservation notification mailer is using placeholder SMTP credentials. Update RESERVATION_SMTP_USERNAME and RESERVATION_SMTP_PASSWORD.');
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
        $mail->addAddress($notificationRecipient);

        $mail->isHTML(true);
        $mail->Subject = $notificationSubject;
        $mail->Body = $notificationMessage;
        $mail->AltBody = implode(PHP_EOL, $altBodyLines);

        $mail->send();
    } catch (PHPMailerException $mailerException) {
        error_log('Reservation notification email failed: ' . $mailerException->getMessage());
    } catch (\Throwable $mailerError) {
        error_log('Reservation notification encountered an unexpected error: ' . $mailerError->getMessage());
    }
}

function send_reservation_customer_confirmation_email(array $reservationDetails): void
{
    $recipientEmail = isset($reservationDetails['email_raw']) ? (string) $reservationDetails['email_raw'] : '';
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $mail = new PHPMailer(true);

    $smtpUsername = 'gospelbaracael@gmail.com';
    $smtpPassword = 'nbawqssfjeovyaxv';
    $senderAddress = $smtpUsername;
    $senderName = 'St. John the Baptist Parish Reservations';

    $customerName = trim(html_entity_decode(strip_tags($reservationDetails['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $greetingName = $customerName !== '' ? $reservationDetails['name'] : 'there';

    $sections = build_reservation_notification_sections($reservationDetails);
    $summaryRows = build_reservation_summary_rows($reservationDetails, $sections);

    $preferredDateDisplay = trim(strip_tags((string) ($reservationDetails['preferred_date'] ?? '')));
    $preferredDateDisplay = $preferredDateDisplay !== ''
        ? (string) $reservationDetails['preferred_date']
        : 'To be confirmed';

    $preferredTimeDisplay = trim(strip_tags((string) ($reservationDetails['preferred_time'] ?? '')));
    $preferredTimeDisplay = $preferredTimeDisplay !== ''
        ? (string) $reservationDetails['preferred_time']
        : 'To be confirmed';

    $eventTypeBadge = trim(strip_tags((string) ($reservationDetails['event_type'] ?? '')));
    $eventTypeBadge = $eventTypeBadge !== '' ? (string) $reservationDetails['event_type'] : 'Reservation';

    $summaryTable = '<div style="border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; margin:0 0 24px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . '<tr><td colspan="2" style="padding:18px 24px; background-color:#f8fafc; font-size:12px; letter-spacing:0.08em; '
        . 'text-transform:uppercase; color:#475569; font-weight:700;">Reservation summary</td></tr>'
        . $summaryRows
        . '</table>'
        . '</div>';

    $infoCards = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">'
        . '<tr>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:#e0f2fe; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:#0c4a6e; font-weight:700;">'
        . 'Preferred date</div>'
        . '<div style="margin-top:10px; font-size:18px; font-weight:700; color:#0f172a;">' . $preferredDateDisplay . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '<td class="stack-column" style="padding:0 6px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="background-color:#dcfce7; border-radius:18px;">'
        . '<tr><td style="padding:18px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:#047857; font-weight:700;">'
        . 'Preferred time</div>'
        . '<div style="margin-top:10px; font-size:18px; font-weight:700; color:#14532d;">' . $preferredTimeDisplay . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>';

    $nextSteps = '<div style="margin:0 0 24px; border:1px solid #bbf7d0; background-color:#f0fdf4; border-radius:18px; padding:20px 24px;">'
        . '<div style="font-size:12px; letter-spacing:0.1em; text-transform:uppercase; color:#047857; font-weight:700;">What happens next</div>'
        . '<ul style="margin:12px 0 0; padding-left:20px; font-size:14px; color:#166534; line-height:1.6;">'
        . '<li>Our parish team will review your reservation request.</li>'
        . '<li>We will contact you if we need more information or to confirm availability.</li>'
        . '<li>Another email will arrive once everything is approved or if adjustments are required.</li>'
        . '</ul>'
        . '</div>';

    $notificationMessage = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Reservation received</title>'
        . '<style type="text/css">@media screen and (max-width:520px){.stack-column{display:block!important;'
        . 'width:100%!important;max-width:100%!important;}}</style>'
        . '</head>'
        . '<body style="margin:0; background-color:#f5f7fb; font-family:\'Segoe UI\', Arial, sans-serif; color:#1f2937;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fb;">'
        . '<tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"'
        . ' style="max-width:600px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.12);">'
        . '<tr><td style="background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 60%,#7c3aed 100%); padding:36px 32px; color:#ffffff;">'
        . '<div style="font-size:12px; letter-spacing:0.22em; text-transform:uppercase; opacity:0.85;">Reservation update</div>'
        . '<div style="margin-top:12px; font-size:26px; font-weight:700;">We received your reservation request</div>'
        . '<p style="margin:18px 0 0; font-size:16px; line-height:1.6; opacity:0.92;">Thank you for reaching out to St. John the Baptist Parish. We\'re reviewing the details you shared.</p>'
        . '<span style="display:inline-block; margin-top:24px; padding:8px 18px; background:rgba(255,255,255,0.22); border-radius:999px;'
        . ' font-size:12px; letter-spacing:0.12em; text-transform:uppercase; font-weight:600;">' . $eventTypeBadge . '</span>'
        . '</td></tr>'
        . '<tr><td style="padding:32px 32px 16px; color:#334155;">'
        . '<p style="margin:0 0 18px; font-size:16px; line-height:1.6;">Hello ' . $greetingName . ',</p>'
        . '<p style="margin:0 0 24px; font-size:16px; line-height:1.6;">We\'ve logged your reservation details and our parish team will confirm everything shortly. Keep this email handy for your reference.</p>'
        . '<div style="margin:0 0 24px;"><span style="display:inline-block; padding:8px 16px; background-color:#dbeafe; color:#1d4ed8; '
        . 'font-size:12px; letter-spacing:0.1em; text-transform:uppercase; font-weight:700; border-radius:999px;">Request received</span></div>'
        . $infoCards
        . $summaryTable
        . $nextSteps
        . '<p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#475569;">If any detail needs to change, simply reply to this email and we\'ll be happy to assist.</p>'
        . '<p style="margin:0 0 12px; font-size:14px; color:#64748b;">Thank you for trusting our parish team.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 32px 32px; background-color:#f8fafc; text-align:center; font-size:12px; color:#94a3b8;">'
        . 'St. John the Baptist Parish &bull; Reservations Team</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';

    $altBodyLines = [
        'We received your reservation request.',
        'Status: Request received',
        '',
        'Name: ' . $customerName,
        'Email: ' . html_entity_decode(strip_tags($reservationDetails['email'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Phone: ' . html_entity_decode(strip_tags($reservationDetails['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Event type: ' . html_entity_decode(strip_tags($reservationDetails['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Preferred date: ' . html_entity_decode(strip_tags($reservationDetails['preferred_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Preferred time: ' . html_entity_decode(strip_tags($reservationDetails['preferred_time'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Notes: ' . html_entity_decode($reservationDetails['notes_text'] ?? '', ENT_QUOTES, 'UTF-8'),
    ];

    if (!empty($sections['wedding_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Wedding information:';
        foreach ($sections['wedding_alt'] as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($sections['funeral_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Funeral information:';
        foreach ($sections['funeral_alt'] as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($sections['attachments_alt'])) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Uploaded documents:';
        foreach ($sections['attachments_alt'] as $line) {
            $altBodyLines[] = ' - ' . html_entity_decode($line, ENT_QUOTES, 'UTF-8');
        }
    }

    $altBodyLines[] = '';
    $altBodyLines[] = 'We will notify you once the booking has been approved, declined, or marked as pending review.';
    $altBodyLines[] = 'If you spot anything incorrect, reply to this email and we will be glad to help.';

    if ($smtpUsername === 'yourgmail@gmail.com' || $smtpPassword === 'your_app_password') {
        error_log('Reservation notification mailer is using placeholder SMTP credentials. Update RESERVATION_SMTP_USERNAME and RESERVATION_SMTP_PASSWORD.');
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
        $mail->addAddress($recipientEmail, $customerName);

        $mail->isHTML(true);
        $mail->Subject = 'We received your reservation request';
        $mail->Body = $notificationMessage;
        $mail->AltBody = implode(PHP_EOL, $altBodyLines);

        $mail->send();
    } catch (PHPMailerException $mailerException) {
        error_log('Reservation customer confirmation email failed: ' . $mailerException->getMessage());
    } catch (\Throwable $mailerError) {
        error_log('Reservation customer confirmation encountered an unexpected error: ' . $mailerError->getMessage());
    }
}

/**
 * Convert a user supplied reservation date into the storage format (Y-m-d).
 */
function format_reservation_date_for_storage($input)
{
    $trimmedInput = trim((string) $input);
    if ($trimmedInput === '') {
        return null;
    }

    $supportedFormats = ['Y-m-d', 'm/d/Y', 'm/d/y'];
    foreach ($supportedFormats as $format) {
        $dateTime = DateTime::createFromFormat($format, $trimmedInput);
        if ($dateTime instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dateTime->format('Y-m-d');
            }
        }
    }

    return null;
}

/**
 * Parse an ISO formatted (Y-m-d) date string into a DateTime set to midnight.
 */
function parse_iso_date_to_midnight(string $value): ?DateTime
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $trimmed);
    if (!$dateTime instanceof DateTime) {
        return null;
    }

    $errors = DateTime::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }

    $dateTime->setTime(0, 0, 0, 0);
    return $dateTime;
}

/**
 * Attempt to parse a user-supplied time string into a DateTime instance.
 */
function parse_reservation_time_value(string $value): ?DateTime
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $parts = preg_split('/\s*-\s*/', $trimmed);
    $primary = trim($parts[0] ?? '');
    if ($primary === '') {
        return null;
    }

    $normalizedPrimary = strtoupper($primary);
    $timeFormats = [
        'g:i A',
        'g:iA',
        'g A',
        'gA',
        'H:i',
        'H:i:s',
        'G:i',
        'G:i:s'
    ];

    foreach ($timeFormats as $format) {
        $dateTime = DateTime::createFromFormat($format, $normalizedPrimary);
        if ($dateTime instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dateTime;
            }
        }

        $dateTime = DateTime::createFromFormat($format, $primary);
        if ($dateTime instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dateTime;
            }
        }
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        $dateTime = new DateTime();
        $dateTime->setTimestamp($timestamp);
        return $dateTime;
    }

    return null;
}

/**
 * Normalize stored reservation times into canonical slot labels.
 */
function normalize_reservation_time_slot_label(string $eventType, string $timeValue): string
{
    $trimmedTime = trim($timeValue);
    $eventTypeKey = strtolower($eventType);

    if ($eventTypeKey === 'baptism') {
        return '11:00 AM - 12:00 PM';
    }

    $parsed = parse_reservation_time_value($trimmedTime);

    if ($eventTypeKey === 'wedding') {
        if ($parsed instanceof DateTime) {
            $hour = (int) $parsed->format('G');
            if ($hour < 12) {
                return '7:30 AM - 10:00 AM';
            }
            return '3:00 PM - 5:00 PM';
        }

        $upper = strtoupper($trimmedTime);
        // Some legacy records saved the afternoon slot with an "AM" suffix ("3:00 am - 5:00 am").
        // Treat any reservation mentioning the 3:00/5:00 window as the afternoon slot regardless
        // of the recorded meridiem to keep the label aligned with the selectable schedule options.
        if (
            strpos($upper, '3:00') !== false ||
            strpos($upper, '15:') !== false ||
            strpos($upper, '5:00 PM') !== false ||
            strpos($upper, '5:00') !== false
        ) {
            return '3:00 PM - 5:00 PM';
        }

        if ($trimmedTime === '') {
            return RESERVATION_UNKNOWN_SLOT;
        }

        return '7:30 AM - 10:00 AM';
    }

    if ($eventTypeKey === 'funeral') {
        if ($parsed instanceof DateTime) {
            return $parsed->format('g:i A');
        }

        $upper = strtoupper($trimmedTime);
        if (preg_match('/([0-9]{1,2}:[0-9]{2})/', $upper, $matches) === 1) {
            $timeCandidate = DateTime::createFromFormat('H:i', $matches[1]);
            if ($timeCandidate instanceof DateTime) {
                return $timeCandidate->format('g:i A');
            }
        }

        return $trimmedTime === '' ? RESERVATION_UNKNOWN_SLOT : $trimmedTime;
    }

    if ($trimmedTime === '') {
        return RESERVATION_UNKNOWN_SLOT;
    }

    if ($parsed instanceof DateTime) {
        return $parsed->format('g:i A');
    }

    return $trimmedTime;
}

/**
 * Load reservation usage grouped by date and event type for availability checks.
 *
 * @param bool $forceRefresh
 * @return array<string, array<string, array<int, string>>>
 * @throws Exception
 */
function get_reservation_usage_summary(bool $forceRefresh = false): array
{
    static $cache = null;

    if (!$forceRefresh && is_array($cache)) {
        return $cache;
    }

    $connection = get_db_connection();

    $query = 'SELECT event_type, preferred_date, preferred_time, status FROM reservations';
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        $error = mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception('Unable to load reservation availability: ' . $error);
    }

    $summary = [];

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $eventType = isset($row['event_type']) ? (string) $row['event_type'] : '';
            $preferredDate = isset($row['preferred_date']) ? $row['preferred_date'] : '';
            $preferredTime = isset($row['preferred_time']) ? (string) $row['preferred_time'] : '';
            $status = isset($row['status']) ? strtolower((string) $row['status']) : '';

            if ($eventType === '') {
                continue;
            }

            if (in_array($status, ['declined', 'canceled', 'cancelled'], true)) {
                continue;
            }

            $normalizedDate = format_reservation_date_for_storage($preferredDate);
            if ($normalizedDate === null) {
                continue;
            }

            $normalizedTime = normalize_reservation_time_slot_label($eventType, $preferredTime);

            if (!isset($summary[$normalizedDate])) {
                $summary[$normalizedDate] = [];
            }

            if (!isset($summary[$normalizedDate][$eventType])) {
                $summary[$normalizedDate][$eventType] = [];
            }

            $summary[$normalizedDate][$eventType][] = $normalizedTime;
        }

        mysqli_free_result($result);
    }

    mysqli_close($connection);

    $cache = $summary;

    return $summary;
}

/**
 * Determine the available time slots for an event type on a specific date.
 *
 * @param string $eventType
 * @param string $normalizedDate
 * @param array<string, array<string, array<int, string>>> $usageSummary
 * @return array{slots: array<int, array<string, string>>, reason: string}
 */
function determine_available_time_slots(string $eventType, string $normalizedDate, array $usageSummary): array
{
    $result = [
        'slots' => [],
        'reason' => '',
    ];

    $dateTime = DateTime::createFromFormat('Y-m-d', $normalizedDate);
    if (!$dateTime instanceof DateTime) {
        return $result;
    }

    $dayOfWeek = (int) $dateTime->format('w');
    $eventTypeKey = strtolower($eventType);
    $eventUsage = $usageSummary[$normalizedDate][$eventType] ?? [];
    $takenValues = is_array($eventUsage) ? $eventUsage : [];

    if ($eventTypeKey === 'wedding') {
        if ($dayOfWeek === 0) {
            $result['reason'] = 'day_not_allowed';
            return $result;
        }

        if (in_array(RESERVATION_UNKNOWN_SLOT, $takenValues, true)) {
            $result['reason'] = 'fully_booked';
            return $result;
        }

        $takenSet = array_fill_keys($takenValues, true);
        $morningSlot = '7:30 AM - 10:00 AM';
        $afternoonSlot = '3:00 PM - 5:00 PM';

        if (!isset($takenSet[$morningSlot])) {
            $result['slots'][] = [
                'value' => $morningSlot,
                'label' => '7:30 AM – 10:00 AM',
            ];
        }

        if (isset($takenSet[$morningSlot]) && !isset($takenSet[$afternoonSlot])) {
            $result['slots'][] = [
                'value' => $afternoonSlot,
                'label' => '3:00 PM – 5:00 PM',
            ];
        }

        if (empty($result['slots'])) {
            $result['reason'] = !empty($takenValues) ? 'fully_booked' : 'day_not_allowed';
        }

        return $result;
    }

    if ($eventTypeKey === 'baptism') {
        if (!in_array($dayOfWeek, [0, 6], true)) {
            $result['reason'] = 'day_not_allowed';
            return $result;
        }

        if (in_array(RESERVATION_UNKNOWN_SLOT, $takenValues, true)) {
            $result['reason'] = 'fully_booked';
            return $result;
        }

        $slotValue = '11:00 AM - 12:00 PM';
        if (!in_array($slotValue, $takenValues, true)) {
            $result['slots'][] = [
                'value' => $slotValue,
                'label' => '11:00 AM – 12:00 PM',
            ];
        } else {
            $result['reason'] = 'fully_booked';
        }

        if (empty($result['slots']) && $result['reason'] === '') {
            $result['reason'] = 'fully_booked';
        }

        return $result;
    }

    if ($eventTypeKey === 'funeral') {
        if (in_array(RESERVATION_UNKNOWN_SLOT, $takenValues, true)) {
            $result['reason'] = 'fully_booked';
            return $result;
        }

        $baseSlots = ($dayOfWeek === 0 || $dayOfWeek === 1)
            ? ['1:00 PM', '2:00 PM']
            : ['8:00 AM', '9:00 AM', '10:00 AM'];

        $takenSet = array_fill_keys($takenValues, true);

        foreach ($baseSlots as $slotValue) {
            if (!isset($takenSet[$slotValue])) {
                $result['slots'][] = [
                    'value' => $slotValue,
                    'label' => $slotValue,
                ];
            }
        }

        if (empty($result['slots'])) {
            $result['reason'] = 'fully_booked';
        }

        return $result;
    }

    return $result;
}

/**
 * Fetch approved reservation summaries grouped by date for the calendar widget.
 */
function load_approved_reservations_grouped_by_date()
{
    $connection = get_db_connection();

    $query = "SELECT name, event_type, preferred_date, preferred_time, status FROM reservations WHERE status = 'approved' ORDER BY preferred_date, preferred_time";
    $result = mysqli_query($connection, $query);
    $hasStatusColumn = true;

    if ($result === false) {
        $fallbackQuery = 'SELECT name, event_type, preferred_date, preferred_time FROM reservations ORDER BY preferred_date, preferred_time';
        $result = mysqli_query($connection, $fallbackQuery);
        $hasStatusColumn = false;
    }

    $grouped = [];

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!isset($row['preferred_date'])) {
                continue;
            }

            $normalizedDate = format_reservation_date_for_storage($row['preferred_date']);
            if ($normalizedDate === null) {
                continue;
            }

            if ($hasStatusColumn && isset($row['status']) && strtolower((string) $row['status']) !== 'approved') {
                continue;
            }

            $eventType = isset($row['event_type']) ? (string) $row['event_type'] : '';
            $preferredTimeRaw = isset($row['preferred_time']) ? (string) $row['preferred_time'] : '';
            $preferredTimeTrimmed = trim($preferredTimeRaw);
            if ($preferredTimeTrimmed !== '') {
                $normalizedTime = normalize_reservation_time_slot_label($eventType, $preferredTimeRaw);
                if ($normalizedTime !== RESERVATION_UNKNOWN_SLOT) {
                    $preferredTimeTrimmed = $normalizedTime;
                }
            }

            if (!array_key_exists($normalizedDate, $grouped)) {
                $grouped[$normalizedDate] = [
                    'date' => $normalizedDate,
                    'reservations' => [],
                ];
            }

            $grouped[$normalizedDate]['reservations'][] = [
                'name' => isset($row['name']) ? trim((string) $row['name']) : '',
                'eventType' => trim($eventType),
                'preferredTime' => $preferredTimeTrimmed,
            ];
        }
        mysqli_free_result($result);
    }

    mysqli_close($connection);

    return array_values($grouped);
}

/**
 * Remove any uploaded reservation files from storage.
 *
 * @param array<int, array<string, mixed>> $uploadedFiles
 * @return void
 */
function remove_uploaded_files(array $uploadedFiles): void
{
    foreach ($uploadedFiles as $storedFile) {
        if (!is_array($storedFile)) {
            continue;
        }

        $storedPath = '';
        if (isset($storedFile['stored_path'])) {
            $storedPath = (string) $storedFile['stored_path'];
        } elseif (isset($storedFile['path'])) {
            $storedPath = (string) $storedFile['path'];
        }

        if ($storedPath === '') {
            continue;
        }

        $absolutePath = __DIR__ . '/' . ltrim($storedPath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

/**
 * Determine which attachment fields are required for a reservation submission.
 *
 * @param string $eventType
 * @param array<string, mixed> $formData
 * @param array<string, array<string, mixed>> $attachmentRequirementSets
 * @return array<string, string>
 */
function determine_required_attachment_documents(string $eventType, array $formData, array $attachmentRequirementSets): array
{
    if (!array_key_exists($eventType, $attachmentRequirementSets)) {
        return [];
    }

    $documents = $attachmentRequirementSets[$eventType]['documents'] ?? [];
    if (!is_array($documents)) {
        return [];
    }

    $required = [];
    foreach ($documents as $fieldName => $documentConfig) {
        if (!is_string($fieldName) || $fieldName === '' || !is_array($documentConfig)) {
            continue;
        }

        $isRequired = true;

        if (isset($documentConfig['conditional']) && is_array($documentConfig['conditional'])) {
            $conditional = $documentConfig['conditional'];
            $conditionalField = isset($conditional['field']) ? (string) $conditional['field'] : '';
            $conditionalValue = $conditional['value'] ?? null;

            if ($conditionalField !== '') {
                $formValue = isset($formData[$conditionalField]) ? (string) $formData[$conditionalField] : '';

                if (is_array($conditionalValue)) {
                    $expectedValues = array_map('strval', $conditionalValue);
                    $isRequired = in_array($formValue, $expectedValues, true);
                } elseif ($conditionalValue !== null) {
                    $isRequired = $formValue === (string) $conditionalValue;
                } else {
                    $isRequired = $formValue !== '';
                }
            }
        }

        if ($isRequired) {
            $label = isset($documentConfig['label']) ? (string) $documentConfig['label'] : $fieldName;
            $required[$fieldName] = $label;
        }
    }

    return $required;
}

/**
 * Persist uploaded reservation attachments to the database.
 *
 * @param mysqli $connection
 * @param int $reservationId
 * @param array<int, array<string, mixed>> $uploadedFiles
 * @return void
 * @throws Exception
 */
function save_reservation_attachments(mysqli $connection, int $reservationId, array $uploadedFiles): void
{
    if ($reservationId <= 0 || empty($uploadedFiles)) {
        return;
    }

    $insertQuery = 'INSERT INTO reservation_attachments (reservation_id, field_key, label, file_name, stored_path) VALUES (?, ?, ?, ?, ?)';
    $statement = mysqli_prepare($connection, $insertQuery);

    if ($statement === false) {
        throw new Exception('Failed to prepare attachment insert: ' . mysqli_error($connection));
    }

    $attachmentReservationId = $reservationId;
    $fieldKey = '';
    $label = '';
    $fileName = '';
    $storedPath = '';

    if (!mysqli_stmt_bind_param($statement, 'issss', $attachmentReservationId, $fieldKey, $label, $fileName, $storedPath)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new Exception('Failed to bind attachment parameters: ' . $error);
    }

    foreach ($uploadedFiles as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $fieldKey = (string) ($attachment['field'] ?? '');
        $label = (string) ($attachment['label'] ?? '');
        $fileName = (string) ($attachment['filename'] ?? '');
        $storedPath = (string) ($attachment['stored_path'] ?? '');

        if ($label === '') {
            $label = 'Attachment';
        }

        if ($fieldKey === '' || $fileName === '' || $storedPath === '') {
            continue;
        }

        if (!mysqli_stmt_execute($statement)) {
            $error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            throw new Exception('Failed to save reservation attachments: ' . $error);
        }
    }

    mysqli_stmt_close($statement);
}

$reservationNotifications = $flashNotification !== null ? [$flashNotification] : [];

$successMessage = '';
$errorMessage = '';
$emailStatusMessage = '';
$emailStatusSuccess = null;

$formData = [
    'reservation-name-first' => '',
    'reservation-name-middle' => '',
    'reservation-name-last' => '',
    'reservation-name-suffix' => '',
    'reservation-name' => '',
    'reservation-email' => '',
    'reservation-phone' => '',
    'reservation-type' => 'Baptism',
    'reservation-date' => '',
    'reservation-time' => '',
    'reservation-notes' => '',
    'wedding-bride-name-first' => '',
    'wedding-bride-name-middle' => '',
    'wedding-bride-name-last' => '',
    'wedding-bride-name-suffix' => '',
    'wedding-bride-name' => '',
    'wedding-groom-name-first' => '',
    'wedding-groom-name-middle' => '',
    'wedding-groom-name-last' => '',
    'wedding-groom-name-suffix' => '',
    'wedding-groom-name' => '',
    'wedding-seminar-date' => '',
    'wedding-sacrament-details' => '',
    'funeral-deceased-name-first' => '',
    'funeral-deceased-name-middle' => '',
    'funeral-deceased-name-last' => '',
    'funeral-deceased-name-suffix' => '',
    'funeral-deceased-name' => '',
    'funeral-marital-status' => '',
];

if ($customerIsLoggedIn) {
    if (!empty($loggedInCustomer['name'])) {
        $nameComponents = split_reservation_full_name((string) $loggedInCustomer['name']);
        $formData['reservation-name-first'] = $nameComponents['first'];
        $formData['reservation-name-middle'] = $nameComponents['middle'];
        $formData['reservation-name-last'] = $nameComponents['last'];
        $formData['reservation-name-suffix'] = $nameComponents['suffix'];
        update_reservation_full_name($formData);
        update_reservation_related_names($formData);
    }
    if (!empty($loggedInCustomer['email'])) {
        $formData['reservation-email'] = (string) $loggedInCustomer['email'];
    }
}

if (!$customerIsLoggedIn || empty($loggedInCustomer['name'])) {
    update_reservation_full_name($formData);
    update_reservation_related_names($formData);
}

$normalizedPreferredDate = null;
$reservationUsageSummary = [];
$uploadedFiles = [];
$selectedWeddingRequirements = [];
$funeralMaritalStatusOptions = [
    'married_not_baptized' => 'Married (not baptized in the church)',
    'single' => 'Single / Unmarried',
];

$funeralAttachmentLabels = [
    'married_not_baptized' => 'Marriage contract of the deceased (if married but not baptized in the church)',
    'single' => 'Baptismal certificate of the deceased (if single or unmarried)',
];

$attachmentRequirementSets = [
    'Baptism' => [
        'title' => 'Required Baptism documents',
        'description' => 'Upload clear scans or photos of the following requirements. Accepted formats: PDF, JPG, PNG (max 5MB each).',
        'documents' => [
            'baptism-birth-certificate' => [
                'label' => 'Birth certificate of the child (Xerox)',
            ],
            'baptism-parent-marriage-contract' => [
                'label' => 'Marriage contract of parents (Xerox)',
            ],
        ],
        'notes' => [
            'Choose one godfather and one godmother as major sponsors (proxies are not allowed).',
            'Major sponsors must be practicing Catholics in good standing.',
            'Suggested church donation: P800.',
            'Please bring original documents to the parish office on the day of baptism.',
        ],
    ],
    'Wedding' => [
        'title' => 'Required Wedding documents',
        'description' => 'Upload scanned copies of the following pre-marriage requirements. Accepted formats: PDF, JPG, PNG (max 5MB each).',
        'documents' => [
            'wedding-bride-baptismal' => [
                'label' => "Bride's baptismal certificate (for marriage purposes)",
            ],
            'wedding-groom-baptismal' => [
                'label' => "Groom's baptismal certificate (for marriage purposes)",
            ],
            'wedding-marriage-license' => [
                'label' => 'Marriage license',
            ],
            'wedding-seminar-certificate-file' => [
                'label' => 'Pre-Cana / marriage preparation seminar certificate',
            ],
        ],
        'notes' => [
            'Submit photocopies with the original documents to the parish office when requested.',
        ],
    ],
    'Funeral' => [
        'title' => 'Required Funeral documents',
        'description' => 'Upload the document that matches the marital status selected above. Accepted formats: PDF, JPG, PNG (max 5MB).',
        'documents' => [
            'funeral-marriage-contract' => [
                'label' => $funeralAttachmentLabels['married_not_baptized'],
                'conditional' => [
                    'field' => 'funeral-marital-status',
                    'value' => 'married_not_baptized',
                ],
            ],
            'funeral-baptismal-certificate' => [
                'label' => $funeralAttachmentLabels['single'],
                'conditional' => [
                    'field' => 'funeral-marital-status',
                    'value' => 'single',
                ],
            ],
        ],
    ],
];

$weddingRequirementChecklist = [
    'baptismal-certificate' => 'Baptismal Certificate (for marriage purposes)',
    'confirmation-certificate' => 'Confirmation Certificate (for marriage purposes)',
    'marriage-permit' => 'Marriage Permit',
    'marriage-banns' => 'Marriage Banns',
    'marriage-license' => 'Marriage License',
    'seminar-certificate' => 'Certificate of Seminar',
    'sponsors-list' => 'Listahan ng Ninong at Ninang (apat na pares na minimum)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$customerIsLoggedIn) {
        $errorMessage = 'Please log in to submit a reservation request.';
    } else {
        foreach ($formData as $field => $default) {
            if (isset($_POST[$field])) {
                $formData[$field] = trim((string) $_POST[$field]);
            }
        }

        update_reservation_full_name($formData);
        update_reservation_related_names($formData);

        if (isset($_POST['wedding-requirements']) && is_array($_POST['wedding-requirements'])) {
            $postedRequirements = array_map('strval', $_POST['wedding-requirements']);
            $selectedWeddingRequirements = array_values(array_intersect($postedRequirements, array_keys($weddingRequirementChecklist)));
        } else {
            $selectedWeddingRequirements = [];
        }

        if ($formData['reservation-name-first'] === '' || $formData['reservation-name-last'] === '') {
            $errorMessage = 'Please enter the first and last name of the person reserving.';
        } elseif (!filter_var($formData['reservation-email'], FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } elseif ($formData['reservation-phone'] === '') {
            $errorMessage = 'Please provide a contact number.';
        } elseif ($formData['reservation-type'] === '') {
            $errorMessage = 'Please select an event type.';
        } elseif (!array_key_exists($formData['reservation-type'], $attachmentRequirementSets)) {
            $errorMessage = 'The selected event type is not supported at this time. Please choose a different option.';
        } elseif ($formData['reservation-date'] === '') {
            $errorMessage = 'Please choose a date from the calendar.';
        } elseif ($formData['reservation-time'] === '') {
            $errorMessage = 'Please choose a preferred time.';
        } else {
            $normalizedPreferredDate = format_reservation_date_for_storage($formData['reservation-date']);
            if ($normalizedPreferredDate === null) {
                $errorMessage = 'Please choose a valid reservation date.';
            } else {
                $selectedDate = DateTime::createFromFormat('Y-m-d', $normalizedPreferredDate);
                if ($selectedDate instanceof DateTime) {
                    $selectedDate->setTime(0, 0, 0);
                    $currentDate = new DateTime('today');
                    if ($selectedDate < $currentDate) {
                        $errorMessage = 'Please choose a reservation date that is not in the past.';
                    }
                }
            }
        }

        if ($errorMessage === '' && $normalizedPreferredDate !== null) {
            try {
                $reservationUsageSummary = get_reservation_usage_summary(true);
            } catch (Exception $availabilityException) {
                $errorMessage = 'We could not verify availability at this time. Please try again later.';
            }
        }

        if ($errorMessage === '' && $normalizedPreferredDate !== null) {
            $availability = determine_available_time_slots(
                $formData['reservation-type'],
                $normalizedPreferredDate,
                $reservationUsageSummary
            );

            $availableValues = array_map(function ($slot) {
                return isset($slot['value']) ? (string) $slot['value'] : '';
            }, $availability['slots']);

            if (empty($availableValues)) {
                if ($availability['reason'] === 'day_not_allowed') {
                    if ($formData['reservation-type'] === 'Wedding') {
                        $errorMessage = 'Weddings may be scheduled Monday through Saturday. Please choose another date.';
                    } elseif ($formData['reservation-type'] === 'Baptism') {
                        $errorMessage = 'Baptisms are available on Saturdays and Sundays only. Please select a weekend date.';
                    } else {
                        $errorMessage = 'The selected event type is not available on that day. Please choose another date.';
                    }
                } else {
                    if ($formData['reservation-type'] === 'Wedding') {
                        $errorMessage = 'All wedding slots for this date have been reserved. Please choose another date.';
                    } elseif ($formData['reservation-type'] === 'Baptism') {
                        $errorMessage = 'The baptism schedule for this date is already reserved. Please pick a different weekend date.';
                    } else {
                        $errorMessage = 'All funeral times for this date are fully booked. Please choose another available date.';
                    }
                }
            } elseif (!in_array($formData['reservation-time'], $availableValues, true)) {
                $errorMessage = 'The selected time is no longer available. Please choose another available slot.';
            }
        }

        $requiredAttachments = [];
        if ($errorMessage === '') {
            if ($formData['reservation-type'] === 'Wedding') {
                if (
                    $formData['wedding-bride-name-first'] === ''
                    || $formData['wedding-bride-name-last'] === ''
                    || $formData['wedding-groom-name-first'] === ''
                    || $formData['wedding-groom-name-last'] === ''
                ) {
                    $errorMessage = 'Please provide the names of both individuals getting married.';
                } elseif ($formData['wedding-seminar-date'] === '') {
                    $errorMessage = 'Please enter the seminar date.';
                } else {
                    $seminarDate = parse_iso_date_to_midnight($formData['wedding-seminar-date']);
                    if (!$seminarDate instanceof DateTime) {
                        $errorMessage = 'Please enter a valid seminar date.';
                    } else {
                        $reservationDate = null;
                        if ($normalizedPreferredDate !== null) {
                            $reservationDate = parse_iso_date_to_midnight($normalizedPreferredDate);
                        }

                        if (!$reservationDate instanceof DateTime) {
                            $errorMessage = 'Please select a wedding date before choosing the seminar date.';
                        } else {
                            $latestSeminarDate = (clone $reservationDate)->modify('-1 day');
                            $earliestSeminarDate = (clone $reservationDate)->modify('-5 days');

                            if (
                                $seminarDate < $earliestSeminarDate
                                || $seminarDate > $latestSeminarDate
                            ) {
                                $errorMessage = 'The seminar date must be between one and five days before your wedding date.';
                            }
                        }
                    }
                }

                if ($errorMessage === '') {
                    $missingRequirements = array_diff(array_keys($weddingRequirementChecklist), $selectedWeddingRequirements);
                    if (!empty($missingRequirements)) {
                        $errorMessage = 'Please confirm all pre-wedding requirements.';
                    }
                }
            } elseif ($formData['reservation-type'] === 'Funeral') {
                if (
                    $formData['funeral-deceased-name-first'] === ''
                    || $formData['funeral-deceased-name-last'] === ''
                ) {
                    $errorMessage = 'Please provide the name of the deceased.';
                } elseif (!array_key_exists($formData['funeral-marital-status'], $funeralMaritalStatusOptions)) {
                    $errorMessage = 'Please select the marital status of the deceased.';
                }
            }
        }

        if ($errorMessage === '') {
            $requiredAttachments = determine_required_attachment_documents(
                $formData['reservation-type'],
                $formData,
                $attachmentRequirementSets
            );
        }
    }

    if ($errorMessage === '' && !empty($requiredAttachments)) {
        foreach ($requiredAttachments as $fieldName => $label) {
            $fileProvided = isset($_FILES[$fieldName]) && is_array($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] !== UPLOAD_ERR_NO_FILE;
            if (!$fileProvided) {
                $errorMessage = 'Please upload the required document: ' . $label . '.';
                break;
            }
        }
    }

    if ($errorMessage === '' && !empty($requiredAttachments)) {
        $maxFileSize = 5 * 1024 * 1024; // 5 MB per file
        $allowedMimeTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        $uploadDirectory = __DIR__ . '/uploads/reservations';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $errorMessage = 'Unable to prepare storage for uploaded documents. Please try again later.';
        }

        if ($errorMessage === '') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                $errorMessage = 'Unable to validate uploaded documents at this time. Please try again later.';
            }

            if ($errorMessage === '') {
                foreach ($requiredAttachments as $fieldName => $label) {
                    $file = $_FILES[$fieldName];

                    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                        $errorMessage = 'There was a problem uploading "' . $label . '". Please try again.';
                        break;
                    }

                    if (!isset($file['size']) || $file['size'] > $maxFileSize) {
                        $errorMessage = 'Each document must be 5MB or smaller. "' . $label . '" exceeds the size limit.';
                        break;
                    }

                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    if ($mimeType === false || !array_key_exists($mimeType, $allowedMimeTypes)) {
                        $errorMessage = 'Only PDF, JPG, and PNG files are accepted for "' . $label . '".';
                        break;
                    }

                    $originalName = isset($file['name']) ? (string) $file['name'] : 'document';
                    $baseName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                    if ($baseName === '') {
                        $baseName = 'document';
                    }

                    $extension = $allowedMimeTypes[$mimeType];
                    $finalFileName = $baseName . '_' . uniqid('', true) . '.' . $extension;
                    $destinationPath = $uploadDirectory . '/' . $finalFileName;

                    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                        $errorMessage = 'Unable to store "' . $label . '". Please try again.';
                        break;
                    }

                    $uploadedFiles[] = [
                        'field' => $fieldName,
                        'label' => $label,
                        'filename' => $finalFileName,
                        'stored_path' => 'uploads/reservations/' . $finalFileName,
                    ];
                }

                if ($errorMessage !== '') {
                    remove_uploaded_files($uploadedFiles);
                    $uploadedFiles = [];
                }
            }

            if ($finfo !== false) {
                finfo_close($finfo);
            }
        }
    }

    if ($errorMessage === '') {
        if ($formData['reservation-type'] === 'Wedding') {
            $weddingDetailsNoteLines = [
                'Wedding details:',
                '- Bride: ' . $formData['wedding-bride-name'],
                '- Groom: ' . $formData['wedding-groom-name'],
            ];

            if ($formData['wedding-seminar-date'] !== '') {
                $weddingDetailsNoteLines[] = '- Seminar date: ' . $formData['wedding-seminar-date'];
            }

            $sacramentDetails = $formData['wedding-sacrament-details'] !== ''
                ? $formData['wedding-sacrament-details']
                : 'Not specified';
            $weddingDetailsNoteLines[] = '- Kumpisa/Kumpil/Binyag details: ' . $sacramentDetails;

            if (!empty($selectedWeddingRequirements)) {
                $weddingRequirementLabels = [];
                foreach ($selectedWeddingRequirements as $requirementKey) {
                    if (array_key_exists($requirementKey, $weddingRequirementChecklist)) {
                        $weddingRequirementLabels[] = $weddingRequirementChecklist[$requirementKey];
                    }
                }
                if (!empty($weddingRequirementLabels)) {
                    $weddingDetailsNoteLines[] = '- Requirements confirmed: ' . implode(', ', $weddingRequirementLabels);
                }
            }

            $weddingDetailsNotes = implode("\n", $weddingDetailsNoteLines);
            $existingNotes = trim((string) $formData['reservation-notes']);
            if ($existingNotes !== '') {
                $existingNotes .= "\n\n";
            }
            $formData['reservation-notes'] = $existingNotes . $weddingDetailsNotes;
        } elseif ($formData['reservation-type'] === 'Funeral') {
            $funeralDetailsNoteLines = [
                'Funeral details:',
                '- Deceased: ' . $formData['funeral-deceased-name'],
                '- Marital status: ' . ($funeralMaritalStatusOptions[$formData['funeral-marital-status']] ?? $formData['funeral-marital-status']),
                '- Reminder: Arrange the schedule at the parish office at least a day before the burial.',
            ];

            $existingNotes = trim((string) $formData['reservation-notes']);
            if ($existingNotes !== '') {
                $existingNotes .= "\n\n";
            }
            $formData['reservation-notes'] = $existingNotes . implode("\n", $funeralDetailsNoteLines);
        }

        if (!empty($uploadedFiles)) {
            $notesWithUploads = trim((string) $formData['reservation-notes']);
            $formData['reservation-notes'] = $notesWithUploads;
        }

        try {
            $connection = get_db_connection();

            $insertQuery = 'INSERT INTO reservations (customer_id, name, email, phone, event_type, preferred_date, preferred_time, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $statement = mysqli_prepare($connection, $insertQuery);

            if ($statement === false) {
                mysqli_close($connection);
                throw new Exception('Failed to prepare reservation statement: ' . mysqli_error($connection));
            }

            $preferredDate = $normalizedPreferredDate ?? $formData['reservation-date'];
            $preferredTime = $formData['reservation-time'];
            $customerId = (int) ($loggedInCustomer['id'] ?? 0);

            if ($customerId <= 0) {
                mysqli_stmt_close($statement);
                mysqli_close($connection);
                throw new Exception('Please log in again before submitting your reservation.');
            }

            mysqli_stmt_bind_param(
                $statement,
                'isssssss',
                $customerId,
                $formData['reservation-name'],
                $formData['reservation-email'],
                $formData['reservation-phone'],
                $formData['reservation-type'],
                $preferredDate,
                $preferredTime,
                $formData['reservation-notes']
            );

            if (!mysqli_stmt_execute($statement)) {
                $executionError = 'Failed to save reservation: ' . mysqli_stmt_error($statement);
                mysqli_stmt_close($statement);
                mysqli_close($connection);
                throw new Exception($executionError);
            }

            $reservationId = (int) mysqli_insert_id($connection);
            mysqli_stmt_close($statement);

            if ($reservationId <= 0) {
                mysqli_close($connection);
                throw new Exception('Failed to determine the reservation number for this request. Please try again.');
            }

            try {
                if (!empty($uploadedFiles)) {
                    save_reservation_attachments($connection, $reservationId, $uploadedFiles);
                }
            } catch (Exception $attachmentException) {
                $cleanupStatement = mysqli_prepare($connection, 'DELETE FROM reservations WHERE id = ?');
                if ($cleanupStatement !== false) {
                    mysqli_stmt_bind_param($cleanupStatement, 'i', $reservationId);
                    mysqli_stmt_execute($cleanupStatement);
                    mysqli_stmt_close($cleanupStatement);
                }

                mysqli_close($connection);
                remove_uploaded_files($uploadedFiles);
                throw $attachmentException;
            }

            mysqli_close($connection);

            try {
                get_reservation_usage_summary(true);
            } catch (Exception $summaryRefreshException) {
                // Availability data refresh failures should not block the reservation flow.
            }

            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDirectory = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
            if ($scriptDirectory === '' || $scriptDirectory === '.') {
                $adminPath = '/admin.php';
            } else {
                $adminPath = $scriptDirectory . '/admin.php';
            }
            $adminUrl = $scheme . '://' . $host . $adminPath;

            $escapedName = htmlspecialchars($formData['reservation-name'], ENT_QUOTES, 'UTF-8');
            $escapedEmail = htmlspecialchars($formData['reservation-email'], ENT_QUOTES, 'UTF-8');
            $escapedPhone = htmlspecialchars($formData['reservation-phone'], ENT_QUOTES, 'UTF-8');
            $escapedEventType = htmlspecialchars($formData['reservation-type'], ENT_QUOTES, 'UTF-8');
            $escapedPreferredDate = htmlspecialchars($preferredDate, ENT_QUOTES, 'UTF-8');
            $escapedPreferredTime = htmlspecialchars($preferredTime, ENT_QUOTES, 'UTF-8');
            $escapedNotes = htmlspecialchars($formData['reservation-notes'], ENT_QUOTES, 'UTF-8');

            $notesHtml = $escapedNotes !== '' ? nl2br($escapedNotes) : '<em>No additional notes provided.</em>';
            if ($formData['reservation-notes'] !== '') {
                $notesPlain = preg_replace("/(\r\n|\r|\n)/", PHP_EOL, strip_tags($formData['reservation-notes']));
                $notesPlain = trim((string) $notesPlain);
                if ($notesPlain === '') {
                    $notesPlain = 'No additional notes provided.';
                }
            } else {
                $notesPlain = 'No additional notes provided.';
            }

            $reservationDetails = [
                'name' => $escapedName,
                'email' => $escapedEmail,
                'email_raw' => $formData['reservation-email'],
                'phone' => $escapedPhone,
                'event_type' => $escapedEventType,
                'preferred_date' => $escapedPreferredDate,
                'preferred_time' => $escapedPreferredTime,
                'notes_html' => $notesHtml,
                'notes_text' => $notesPlain,
                'attachments' => $uploadedFiles,
            ];

            if ($formData['reservation-type'] === 'Wedding') {
                $reservationDetails['wedding_details'] = [
                    'bride_name' => htmlspecialchars($formData['wedding-bride-name'], ENT_QUOTES, 'UTF-8'),
                    'groom_name' => htmlspecialchars($formData['wedding-groom-name'], ENT_QUOTES, 'UTF-8'),
                    'seminar_date' => htmlspecialchars($formData['wedding-seminar-date'], ENT_QUOTES, 'UTF-8'),
                    'sacrament_details' => htmlspecialchars($formData['wedding-sacrament-details'], ENT_QUOTES, 'UTF-8'),
                    'requirements' => array_map(function ($key) use ($weddingRequirementChecklist) {
                        return htmlspecialchars($weddingRequirementChecklist[$key] ?? $key, ENT_QUOTES, 'UTF-8');
                    }, $selectedWeddingRequirements),
                ];
            } elseif ($formData['reservation-type'] === 'Funeral') {
                $reservationDetails['funeral_details'] = [
                    'deceased_name' => htmlspecialchars($formData['funeral-deceased-name'], ENT_QUOTES, 'UTF-8'),
                    'marital_status' => htmlspecialchars($funeralMaritalStatusOptions[$formData['funeral-marital-status']] ?? $formData['funeral-marital-status'], ENT_QUOTES, 'UTF-8'),
                    'office_reminder' => htmlspecialchars('Arrange the schedule at the parish office at least a day before the burial.', ENT_QUOTES, 'UTF-8'),
                ];
            }

            send_reservation_notification_email($reservationDetails, $adminUrl);
            send_reservation_customer_confirmation_email($reservationDetails);

            $plainName = trim(html_entity_decode(strip_tags($reservationDetails['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $plainEventType = trim(html_entity_decode(strip_tags($reservationDetails['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $plainPreferredDate = trim(html_entity_decode(strip_tags($reservationDetails['preferred_date'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $plainPreferredTime = trim(html_entity_decode(strip_tags($reservationDetails['preferred_time'] ?? ''), ENT_QUOTES, 'UTF-8'));

            $scheduleSummary = $plainPreferredDate !== '' ? $plainPreferredDate : 'To be confirmed';
            if ($plainPreferredTime !== '') {
                $scheduleSummary .= ' at ' . $plainPreferredTime;
            }

            $customerPhoneNumber = isset($formData['reservation-phone']) ? (string) $formData['reservation-phone'] : '';
            $customerGreetingName = $plainName !== '' ? $plainName : 'there';
            $customerEventLabel = $plainEventType !== '' ? $plainEventType : 'reservation';
            $customerMessage = sprintf(
                'Hi %s, we received your %s reservation request. Preferred schedule: %s. We will contact you soon. - St. John the Baptist Parish',
                $customerGreetingName,
                $customerEventLabel,
                $scheduleSummary
            );

            send_sms_notification($customerPhoneNumber, $customerMessage);

            if (defined('RESERVATION_ADMIN_SMS_PHONE') && RESERVATION_ADMIN_SMS_PHONE !== '') {
                $adminMessage = sprintf(
                    'New reservation from %s (%s). Schedule: %s. Review: %s',
                    $plainName !== '' ? $plainName : 'Unknown name',
                    $plainEventType !== '' ? $plainEventType : 'Reservation',
                    $scheduleSummary,
                    $adminUrl
                );

                send_sms_notification(RESERVATION_ADMIN_SMS_PHONE, $adminMessage);
            }

            $successMessage = 'Thank you! Your reservation request has been saved. We will contact you soon to confirm the details.';

            $_SESSION['customer_flash_notification'] = [
                'icon' => 'success',
                'title' => 'Reservation received!',
                'text' => $successMessage,
            ];

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $redirectTarget = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== ''
                ? $_SERVER['REQUEST_URI']
                : (isset($_SERVER['PHP_SELF']) && is_string($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] !== ''
                    ? $_SERVER['PHP_SELF']
                    : 'reservation.php');

            header('Location: ' . $redirectTarget, true, 303);
            exit;

        } catch (Exception $exception) {
            if (isset($statement) && $statement instanceof mysqli_stmt) {
                mysqli_stmt_close($statement);
            }
            if (isset($connection) && $connection instanceof mysqli) {
                mysqli_close($connection);
            }
            if (!empty($uploadedFiles)) {
                remove_uploaded_files($uploadedFiles);
                $uploadedFiles = [];
            }
            $errorMessage = $exception->getMessage();
        }
    }
}
if ($errorMessage !== '') {
    $reservationNotifications[] = [
        'icon' => 'error',
        'title' => 'We could not save your reservation',
        'text' => $errorMessage,
    ];
} elseif ($successMessage !== '') {
    $reservationNotifications[] = [
        'icon' => 'success',
        'title' => 'Reservation received!',
        'text' => $successMessage,
    ];
}

if ($emailStatusMessage !== '') {
    $emailIcon = 'info';
    if ($emailStatusSuccess === false) {
        $emailIcon = 'warning';
    } elseif ($emailStatusSuccess === true) {
        $emailIcon = 'success';
    }

    $reservationNotifications[] = [
        'icon' => $emailIcon,
        'title' => $emailStatusSuccess === false ? 'Email delivery issue' : 'Email status',
        'text' => $emailStatusMessage,
    ];
}

try {
    $approvedReservationSummaries = load_approved_reservations_grouped_by_date();
} catch (Exception $exception) {
    $approvedReservationSummaries = [];
}

try {
    $reservationUsageSummaryForOutput = get_reservation_usage_summary();
} catch (Exception $exception) {
    $reservationUsageSummaryForOutput = [];
}

$approvedReservationsJson = json_encode($approvedReservationSummaries);
if ($approvedReservationsJson === false) {
    $approvedReservationsJson = '[]';
}

$reservationUsageJson = json_encode($reservationUsageSummaryForOutput, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($reservationUsageJson === false) {
    $reservationUsageJson = '{}';
}

$reservationNotificationsJson = json_encode($reservationNotifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($reservationNotificationsJson === false) {
    $reservationNotificationsJson = '[]';
}

$shouldOpenReservationModal = $customerIsLoggedIn && (
    $errorMessage !== '' ||
    ($emailStatusMessage !== '' && $successMessage === '')
);
$shouldDisplayReservationForm = $customerIsLoggedIn && $shouldOpenReservationModal;
$prefilledReservationDate = '';
if ($formData['reservation-date'] !== '') {
    $normalizedForPrefill = format_reservation_date_for_storage($formData['reservation-date']);
    if ($normalizedForPrefill !== null) {
        $prefilledReservationDate = $normalizedForPrefill;
    } else {
        $prefilledReservationDate = $formData['reservation-date'];
    }
}
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Reservations | St. John the Baptist Parish</title>
    <meta name="description"
        content="Reserve a sacrament or church service at St. John the Baptist Parish in Tiaong, Quezon.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.jpg">

    <!-- CSS here -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/magnific-popup.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/themify-icons.css">
    <link rel="stylesheet" href="css/nice-select.css">
    <link rel="stylesheet" href="css/flaticon.css">
    <link rel="stylesheet" href="css/gijgo.css">
    <link rel="stylesheet" href="css/animate.css">
    <link rel="stylesheet" href="css/slicknav.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/reservation-overrides.css">
</head>

<body class="reservation-page">
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">
                        <div class="col-xl-5 col-lg-6 d-none d-lg-block order-lg-1">
                            <div class="main-menu d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                        <li><a href="index.php">Home</a></li>
                                        <li><a href="about.php">About</a></li>
                                        <li><a href="schedule.php">Schedule</a></li>
                                        <li><a href="contact.php">Inquire</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <div class="col-xl-2 col-lg-2 col-6 order-lg-2 d-flex align-items-center justify-content-center">
                            <div class="logo-img">
                                <a href="index.php">
                                    <img src="img/about/about_1.jpg" height="60" alt="St. John the Baptist Parish logo" style="border-radius: 50px;">
                                </a>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-4 d-none d-lg-block order-lg-3">
                            <div class="book_room">
                                <div class="socail_links">
                                    <ul>
                                        <li>
                                            <a href="https://www.facebook.com/officialstjohnthebaptistparishtiaong"
                                                target="_blank" rel="noopener" aria-label="Facebook">
                                                <i class="fa fa-facebook-square"></i>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="mailto:stjohnbaptisttiaongparish@gmail.com" aria-label="Email">
                                                <i class="fa fa-envelope"></i>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="tel:+63425459244" aria-label="Call">
                                                <i class="fa fa-phone"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <?php if ($customerIsLoggedIn): ?>
                                    <p class="text-right text-white-50 mb-2 small" style="color: white;">Signed in as
                                        <strong
                                            style="color: white;"><?php echo htmlspecialchars($loggedInCustomer['name'] ?? 'Member', ENT_QUOTES); ?></strong>
                                        &middot; <a class="text-white" href="customer_logout.php">Log out</a>
                                    </p>
                                <?php endif; ?>
                                <div class="book_btn d-none d-lg-block">
                                    <?php if (!$customerIsLoggedIn): ?>
                                        <a class="boxed-btn3" href="customer_login.php">Log in to reserve</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 d-lg-none d-flex justify-content-end">
                            <div class="mobile_menu d-block d-lg-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="bradcam_area breadcam_bg">
        <h3>Make a Reservation</h3>
    </div>

    <section class="reservation_intro pt-120 pb-60">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center">
                    <div class="section_title mb-40">
                        <span>Plan your celebration or service</span>
                        <h3>Reserve a sacrament, liturgy, or pastoral service</h3>
                        <p>Please complete the form below with as much detail as possible. A member of our pastoral
                            staff will follow up within two business days to confirm availability and discuss next
                            steps.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="reservation_form_area pb-120">
        <div class="container-fluid reservation_form_container">
            <?php if (!$customerIsLoggedIn): ?>
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-9 col-xl-7">
                        <div class="alert alert-info text-center" role="alert">
                            Please <a href="customer_login.php" class="alert-link">log in</a> or
                            <a href="customer_register.php" class="alert-link">create an account</a> to submit a reservation
                            request online.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-12">
                    <div class="reservation_calendar mb-5">
                        <h4 class="mb-4">Availability Preview</h4>
                        <p class="mb-4">Dates with a badge already have at least one reservation on the parish calendar.
                            You can
                            still choose them if an additional slot fits your celebration—just open the day to review
                            the
                            details before submitting your request.
                        </p>
                        <div class="calendar_legend mb-3">
                            <span><span class="legend booked"></span> Reservation on file</span>
                        </div>
                        <div class="availability_calendar" id="availability-calendar"></div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-12 col-lg-9 col-xl-7">
                    <div class="reservation_form_cta text-center p-5">
                        <h4 class="mb-3">Ready to request a sacrament?</h4>
                        <p class="mb-4">We now collect reservation details and required documents directly inside the
                            reservation window. Choose a date on the calendar or use the button below to begin your
                            request.</p>
                        <?php if ($customerIsLoggedIn): ?>
                            <button type="button" class="boxed-btn3" data-toggle="modal"
                                data-target="#reservationDayModal">Start a Reservation</button>
                        <?php else: ?>
                            <a class="boxed-btn3" href="customer_login.php">Log in to start</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="reservation_faq pb-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="section_title text-center mb-40">
                        <span>Be prepared</span>
                        <h3>What happens after I submit a request?</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="single_about_info text-center">
                                <h4>We review your request</h4>
                                <p>Our staff checks the parish calendar and confirms priest availability.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="single_about_info text-center">
                                <h4>We connect with you</h4>
                                <p>Expect a call or email within two business days to discuss preparation steps.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="single_about_info text-center">
                                <h4>We finalize the details</h4>
                                <p>Together we complete the required forms, schedule rehearsals, and plan the liturgy.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include 'includes/footer.php'; ?>


    <div class="modal fade reservation_day_modal" id="reservationDayModal" tabindex="-1" role="dialog"
        aria-labelledby="reservationDayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content reservation-modal">
                <div class="modal-header reservation-modal__header">
                    <div class="reservation-modal__title-group">
                        <span class="reservation-modal__eyebrow">Sacrament reservations</span>
                        <h5 class="modal-title" id="reservationDayModalLabel" style="color:white;">Start a Reservation
                        </h5>
                        <p class="reservation-modal__subtitle" style="color:white;">Choose an available celebration
                            date, share the
                            details, and upload the required documents—all in one elegant flow.</p>
                    </div>
                    <button type="button" class="close reservation-modal__close" data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body reservation-modal__body">
                    <div class="reservation-modal__body-inner">
                        <div class="reservation-modal__progress">
                            <div class="reservation-modal__progress-item">
                                <span class="reservation-modal__progress-number">1</span>
                                <span class="reservation-modal__progress-text">Choose an available date</span>
                            </div>
                            <div class="reservation-modal__progress-item">
                                <span class="reservation-modal__progress-number">2</span>
                                <span class="reservation-modal__progress-text">Share your celebration details</span>
                            </div>
                            <div class="reservation-modal__progress-item">
                                <span class="reservation-modal__progress-number">3</span>
                                <span class="reservation-modal__progress-text">Attach the required documents</span>
                            </div>
                        </div>
                        <div data-reservation-messages>
                            <noscript>
                                <?php if ($successMessage !== ''): ?>
                                    <div class="alert alert-success" role="alert">
                                        <?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($emailStatusMessage !== ''): ?>
                                    <div class="alert <?php echo $emailStatusSuccess ? 'alert-info' : 'alert-warning'; ?>"
                                        role="alert">
                                        <?php echo htmlspecialchars($emailStatusMessage, ENT_QUOTES); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($errorMessage !== ''): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?>
                                    </div>
                                <?php endif; ?>
                            </noscript>
                        </div>
                        <div class="reservation_modal_content reservation-modal__form">
                            <?php if (!$customerIsLoggedIn): ?>
                                <div class="reservation_modal_sidebar mb-4">
                                    <h6 class="text-uppercase text-muted">Availability preview</h6>
                                    <div data-reservation-availability>
                                        <p class="mb-2">Create a free account or log in to request a sacrament online.</p>
                                        <p class="small text-muted mb-0">Once signed in you can choose an available date and
                                            submit your reservation details.</p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <a class="boxed-btn3 mb-3" href="customer_login.php">Log in to reserve</a>
                                    <p class="mb-0">Need an account? <a href="customer_register.php">Create one in
                                            minutes</a>.</p>
                                </div>
                            <?php else: ?>
                                <div class="reservation_modal_sidebar mb-4">
                                    <h6 class="text-uppercase text-muted">Availability preview</h6>
                                    <div data-reservation-availability>
                                        <p class="mb-2">Select a date on the calendar to see existing approved reservations
                                            and prefill the request form.</p>
                                        <p class="small text-muted mb-0">Dates without a <span
                                                class="badge badge-danger">Booked</span>
                                            tag remain open for requests.</p>
                                    </div>
                                </div>
                                <button type="button"
                                    class="boxed-btn3 w-100 mb-4<?php echo $shouldDisplayReservationForm ? ' d-none' : ''; ?>"
                                    data-reservation-start>
                                    Make a Reservation
                                </button>
                                <form id="reservation-form"
                                    class="reservation_form<?php echo $shouldDisplayReservationForm ? '' : ' d-none'; ?>"
                                    method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>"
                                    enctype="multipart/form-data" data-server-handled="true" data-reservation-form
                                    data-loading-form>
                                    <div class="form-group">
                                        <label class="d-block" for="reservation-name-first">Name of person reserving
                                            *</label>
                                        <div class="form-row">
                                            <div class="col-sm-6 mb-3">
                                                <input type="text" id="reservation-name-first" name="reservation-name-first"
                                                    class="form-control" placeholder="First name" required
                                                    autocomplete="given-name"
                                                    value="<?php echo htmlspecialchars($formData['reservation-name-first'], ENT_QUOTES); ?>">
                                            </div>
                                            <div class="col-sm-6 mb-3">
                                                <input type="text" id="reservation-name-middle"
                                                    name="reservation-name-middle" class="form-control"
                                                    placeholder="Middle name (optional)" autocomplete="additional-name"
                                                    value="<?php echo htmlspecialchars($formData['reservation-name-middle'], ENT_QUOTES); ?>">
                                            </div>
                                            <div class="col-sm-6 mb-3">
                                                <input type="text" id="reservation-name-last" name="reservation-name-last"
                                                    class="form-control" placeholder="Last name" required
                                                    autocomplete="family-name"
                                                    value="<?php echo htmlspecialchars($formData['reservation-name-last'], ENT_QUOTES); ?>">
                                            </div>
                                            <div class="col-sm-6 mb-3">
                                                <input type="text" id="reservation-name-suffix"
                                                    name="reservation-name-suffix" class="form-control"
                                                    placeholder="Suffix (optional)" autocomplete="honorific-suffix"
                                                    value="<?php echo htmlspecialchars($formData['reservation-name-suffix'], ENT_QUOTES); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reservation-email">Email *</label>
                                        <input type="email" id="reservation-email" name="reservation-email"
                                            class="form-control" placeholder="name@example.com" required
                                            value="<?php echo htmlspecialchars($formData['reservation-email'], ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="reservation-phone">Contact number *</label>
                                        <input type="tel" id="reservation-phone" name="reservation-phone"
                                            class="form-control" placeholder="(042) 545-9244" required
                                            value="<?php echo htmlspecialchars($formData['reservation-phone'], ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="d-block">Event type *</label>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="reservation-type-baptism" name="reservation-type"
                                                class="custom-control-input" value="Baptism" required <?php echo $formData['reservation-type'] === 'Baptism' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label"
                                                for="reservation-type-baptism">Baptism</label>
                                        </div>
                                        <div class="custom-control custom-radio mt-2">
                                            <input type="radio" id="reservation-type-wedding" name="reservation-type"
                                                class="custom-control-input" value="Wedding" <?php echo $formData['reservation-type'] === 'Wedding' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label"
                                                for="reservation-type-wedding">Wedding</label>
                                        </div>
                                        <div class="custom-control custom-radio mt-2">
                                            <input type="radio" id="reservation-type-funeral" name="reservation-type"
                                                class="custom-control-input" value="Funeral" <?php echo $formData['reservation-type'] === 'Funeral' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="reservation-type-funeral">Funeral
                                                <small class="d-block text-muted">Coordinate with the parish office a day
                                                    before burial</small>
                                            </label>
                                        </div>
                                    </div>
                                    <input type="hidden" id="reservation-date" name="reservation-date"
                                        value="<?php echo htmlspecialchars($formData['reservation-date'], ENT_QUOTES); ?>">
                                    <div class="form-group">
                                        <label for="reservation-time">Preferred time *</label>
                                        <select id="reservation-time" name="reservation-time" class="form-control" required
                                            style="height: 50px;"
                                            data-initial-value="<?php echo htmlspecialchars($formData['reservation-time'], ENT_QUOTES); ?>">
                                            <option value="">Select a time</option>
                                        </select>
                                        <small class="form-text text-muted" data-reservation-time-help>
                                            Choose an event type and calendar date to see available times.
                                        </small>
                                    </div>
                                    <div id="wedding-details" class="reservation_attachment_box mb-4">
                                        <h6 class="mb-3">Wedding information</h6>
                                        <div class="form-group">
                                            <label class="d-block" for="wedding-bride-name-first">Bride's name *</label>
                                            <div class="form-row">
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-bride-name-first"
                                                        name="wedding-bride-name-first" placeholder="First name"
                                                        autocomplete="section-wedding given-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-bride-name-first'], ENT_QUOTES); ?>"
                                                        data-wedding-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-bride-name-middle"
                                                        name="wedding-bride-name-middle"
                                                        placeholder="Middle name (optional)"
                                                        autocomplete="section-wedding additional-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-bride-name-middle'], ENT_QUOTES); ?>">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-bride-name-last"
                                                        name="wedding-bride-name-last" placeholder="Last name"
                                                        autocomplete="section-wedding family-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-bride-name-last'], ENT_QUOTES); ?>"
                                                        data-wedding-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-bride-name-suffix"
                                                        name="wedding-bride-name-suffix" placeholder="Suffix (optional)"
                                                        autocomplete="section-wedding honorific-suffix"
                                                        value="<?php echo htmlspecialchars($formData['wedding-bride-name-suffix'], ENT_QUOTES); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="d-block" for="wedding-groom-name-first">Groom's name *</label>
                                            <div class="form-row">
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-groom-name-first"
                                                        name="wedding-groom-name-first" placeholder="First name"
                                                        autocomplete="section-wedding-groom given-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-groom-name-first'], ENT_QUOTES); ?>"
                                                        data-wedding-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-groom-name-middle"
                                                        name="wedding-groom-name-middle"
                                                        placeholder="Middle name (optional)"
                                                        autocomplete="section-wedding-groom additional-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-groom-name-middle'], ENT_QUOTES); ?>">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-groom-name-last"
                                                        name="wedding-groom-name-last" placeholder="Last name"
                                                        autocomplete="section-wedding-groom family-name"
                                                        value="<?php echo htmlspecialchars($formData['wedding-groom-name-last'], ENT_QUOTES); ?>"
                                                        data-wedding-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="wedding-groom-name-suffix"
                                                        name="wedding-groom-name-suffix" placeholder="Suffix (optional)"
                                                        autocomplete="section-wedding-groom honorific-suffix"
                                                        value="<?php echo htmlspecialchars($formData['wedding-groom-name-suffix'], ENT_QUOTES); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="wedding-seminar-date">Seminar date *</label>
                                            <input type="text" class="form-control" id="wedding-seminar-date"
                                                name="wedding-seminar-date"
                                                placeholder="Select seminar date"
                                                value="<?php echo htmlspecialchars($formData['wedding-seminar-date'], ENT_QUOTES); ?>"
                                                data-wedding-required="true"
                                                style="font-size: 15px;">
                                            <small class="form-text text-muted">Choose a seminar date that is between one and five days before your wedding day.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="wedding-sacrament-details">Kumpisa / Kumpil / Binyag details</label>
                                            <textarea class="form-control" id="wedding-sacrament-details"
                                                name="wedding-sacrament-details" rows="3"
                                                placeholder="Parishes or dates for confession, confirmation, and baptism"><?php echo htmlspecialchars($formData['wedding-sacrament-details'], ENT_QUOTES); ?></textarea>
                                        </div>
                                        <div class="form-group mb-0">
                                            <h6 class="mb-2">Mga kailangan bago ikasal *</h6>
                                            <p class="small text-muted">Please confirm that you have prepared the following
                                                requirements.</p>
                                            <?php foreach ($weddingRequirementChecklist as $requirementKey => $requirementLabel): ?>
                                                <?php $inputId = 'wedding-requirement-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $requirementKey); ?>
                                                <div class="custom-control custom-checkbox mb-1">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>"
                                                        name="wedding-requirements[]"
                                                        value="<?php echo htmlspecialchars($requirementKey, ENT_QUOTES); ?>"
                                                        <?php echo in_array($requirementKey, $selectedWeddingRequirements, true) ? 'checked' : ''; ?> data-wedding-required="true"
                                                        data-wedding-checkbox="true">
                                                    <label class="custom-control-label"
                                                        for="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>">
                                                        <?php echo htmlspecialchars($requirementLabel, ENT_QUOTES); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div id="funeral-details" class="reservation_attachment_box mb-4">
                                        <h6 class="mb-3">Funeral information</h6>
                                        <div class="alert alert-warning small" role="alert">
                                            Arrange or reserve the funeral schedule at the parish office at least one day
                                            before the burial to avoid delays or declined requests.
                                        </div>
                                        <div class="form-group">
                                            <label class="d-block" for="funeral-deceased-name-first">Name of the deceased
                                                *</label>
                                            <div class="form-row">
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="funeral-deceased-name-first"
                                                        name="funeral-deceased-name-first" placeholder="First name"
                                                        autocomplete="section-funeral given-name"
                                                        value="<?php echo htmlspecialchars($formData['funeral-deceased-name-first'], ENT_QUOTES); ?>"
                                                        data-funeral-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control"
                                                        id="funeral-deceased-name-middle"
                                                        name="funeral-deceased-name-middle"
                                                        placeholder="Middle name (optional)"
                                                        autocomplete="section-funeral additional-name"
                                                        value="<?php echo htmlspecialchars($formData['funeral-deceased-name-middle'], ENT_QUOTES); ?>">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control" id="funeral-deceased-name-last"
                                                        name="funeral-deceased-name-last" placeholder="Last name"
                                                        autocomplete="section-funeral family-name"
                                                        value="<?php echo htmlspecialchars($formData['funeral-deceased-name-last'], ENT_QUOTES); ?>"
                                                        data-funeral-required="true">
                                                </div>
                                                <div class="col-sm-6 col-lg-3 mb-3">
                                                    <input type="text" class="form-control"
                                                        id="funeral-deceased-name-suffix"
                                                        name="funeral-deceased-name-suffix" placeholder="Suffix (optional)"
                                                        autocomplete="section-funeral honorific-suffix"
                                                        value="<?php echo htmlspecialchars($formData['funeral-deceased-name-suffix'], ENT_QUOTES); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="funeral-marital-status">Marital status of the deceased *</label>
                                            <select class="form-control" id="funeral-marital-status" style="height: 50px;"
                                                name="funeral-marital-status" data-funeral-required="true"
                                                data-funeral-marital-select="true">
                                                <option value="">Select status</option>
                                                <?php foreach ($funeralMaritalStatusOptions as $statusValue => $statusLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES); ?>"
                                                        <?php echo $formData['funeral-marital-status'] === $statusValue ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                            </div>
                            <?php
                            $attachmentSectionIdMap = [
                                'Baptism' => 'baptism-attachments',
                                'Wedding' => 'wedding-attachments',
                                'Funeral' => 'funeral-attachments',
                            ];
                            ?>
                            <?php foreach ($attachmentRequirementSets as $eventType => $attachmentSet): ?>
                                <?php
                                $documents = isset($attachmentSet['documents']) && is_array($attachmentSet['documents'])
                                    ? $attachmentSet['documents']
                                    : [];
                                if (empty($documents)) {
                                    continue;
                                }

                                $sectionId = $attachmentSectionIdMap[$eventType] ?? 'attachments-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $eventType));
                                $shouldShowSection = $shouldDisplayReservationForm && $formData['reservation-type'] === $eventType;
                                $sectionStyle = $shouldShowSection ? '' : 'display: none;';
                                $sectionTitle = isset($attachmentSet['title']) ? (string) $attachmentSet['title'] : 'Required documents';
                                $sectionDescription = isset($attachmentSet['description']) ? (string) $attachmentSet['description'] : '';
                                $sectionNotes = isset($attachmentSet['notes']) && is_array($attachmentSet['notes']) ? $attachmentSet['notes'] : [];
                                ?>
                                <div class="reservation_attachment_box mb-4"
                                    id="<?php echo htmlspecialchars($sectionId, ENT_QUOTES); ?>"
                                    data-attachment-section="<?php echo htmlspecialchars($eventType, ENT_QUOTES); ?>"
                                    style="<?php echo htmlspecialchars($sectionStyle, ENT_QUOTES); ?>">
                                    <h6 class="mb-3 pt-3"><?php echo htmlspecialchars($sectionTitle, ENT_QUOTES); ?></h6>
                                    <?php if ($sectionDescription !== ''): ?>
                                        <p class="small text-muted"><?php echo htmlspecialchars($sectionDescription, ENT_QUOTES); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php foreach ($documents as $fieldName => $documentConfig): ?>
                                        <?php
                                        if (!is_array($documentConfig)) {
                                            continue;
                                        }

                                        $inputId = (string) $fieldName;
                                        $label = isset($documentConfig['label']) ? (string) $documentConfig['label'] : $inputId;
                                        $accept = isset($documentConfig['accept']) ? (string) $documentConfig['accept'] : '.pdf,.jpg,.jpeg,.png';
                                        $conditional = isset($documentConfig['conditional']) && is_array($documentConfig['conditional'])
                                            ? $documentConfig['conditional']
                                            : null;
                                        $conditionalField = $conditional['field'] ?? '';
                                        $conditionalValue = $conditional['value'] ?? '';
                                        ?>
                                        <div class="form-group"
                                            data-attachment-field="<?php echo htmlspecialchars($fieldName, ENT_QUOTES); ?>" <?php if ($conditionalField !== ''): ?>
                                                data-attachment-conditional-field="<?php echo htmlspecialchars((string) $conditionalField, ENT_QUOTES); ?>"
                                                data-attachment-conditional-value="<?php echo htmlspecialchars((string) $conditionalValue, ENT_QUOTES); ?>"
                                            <?php endif; ?>>
                                            <label for="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($label, ENT_QUOTES); ?> *
                                            </label>
                                            <input type="file" class="form-control-file"
                                                id="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>"
                                                name="<?php echo htmlspecialchars($fieldName, ENT_QUOTES); ?>"
                                                accept="<?php echo htmlspecialchars($accept, ENT_QUOTES); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($sectionNotes)): ?>
                                        <ul class="small pl-3 text-left mb-0">
                                            <?php foreach ($sectionNotes as $note): ?>
                                                <li><?php echo htmlspecialchars((string) $note, ENT_QUOTES); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-group" data-reservation-form-toggle-target<?php echo $shouldDisplayReservationForm ? '' : ' hidden'; ?>>
                                <label for="reservation-notes">Additional notes or requests</label>
                                <textarea id="reservation-notes" name="reservation-notes" class="form-control" rows="4"
                                    placeholder="Tell us about your celebration"><?php echo htmlspecialchars($formData['reservation-notes'], ENT_QUOTES); ?></textarea>
                            </div>
                            <div class="alert alert-warning small mt-3 d-none" role="alert"
                                data-reservation-form-toggle-target<?php echo $shouldDisplayReservationForm ? '' : ' hidden'; ?> data-reservation-time-warning>
                            </div>
                            <button type="submit" class="boxed-btn3 w-100" data-reservation-form-toggle-target<?php echo $shouldDisplayReservationForm ? '' : ' hidden'; ?> data-reservation-submit
                                data-loading-button <?php echo $shouldDisplayReservationForm && $formData['reservation-time'] !== '' ? '' : ' disabled'; ?>>
                                <span>Submit Reservation Request</span>
                                <span class="spinner-border spinner-border-sm ml-2 align-middle d-none" role="status"
                                    aria-hidden="true" data-loading-spinner></span>
                            </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.approvedReservations = <?php echo $approvedReservationsJson; ?>;
        window.shouldOpenReservationModal = <?php echo $shouldOpenReservationModal ? 'true' : 'false'; ?>;
        window.prefilledReservationDate = <?php echo json_encode($prefilledReservationDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.shouldDisplayReservationForm = <?php echo $shouldDisplayReservationForm ? 'true' : 'false'; ?>;
        window.reservationUsage = <?php echo $reservationUsageJson; ?>;
        window.reservationNotifications = <?php echo $reservationNotificationsJson; ?>;
    </script>
    <script src="js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="js/vendor/jquery-1.12.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/isotope.pkgd.min.js"></script>
    <script src="js/ajax-form.js"></script>
    <script src="js/waypoints.min.js"></script>
    <script src="js/jquery.counterup.min.js"></script>
    <script src="js/imagesloaded.pkgd.min.js"></script>
    <script src="js/scrollIt.js"></script>
    <script src="js/jquery.scrollUp.min.js"></script>
    <script src="js/wow.min.js"></script>
    <script src="js/nice-select.min.js"></script>
    <script src="js/jquery.slicknav.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/gijgo.min.js"></script>
    <script src="js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/reservations.js"></script>
    <script src="js/form-loading.js"></script>
</body>

</html>