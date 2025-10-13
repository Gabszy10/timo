<?php

session_start();

if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/includes/reservation_repository.php';

/**
 * Escape a value for safe HTML output.
 */
function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_reservation_date_value(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '—';
    }

    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('l, F j, Y');
    } catch (Exception $exception) {
        return escape_html(trim($date));
    }
}

function format_reservation_time_value(?string $time): string
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
                    $upperSegment = strtoupper($segment);
                    $dateTime = DateTime::createFromFormat($format, $upperSegment);
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

                return escape_html($segment);
            };

            $start = $formatPart($parts[0]);
            $end = $formatPart($parts[1]);

            if ($start !== '' && $end !== '') {
                return $start . ' – ' . $end;
            }
        }

        return escape_html($trimmed);
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        return date('g:i A', $timestamp);
    }

    return escape_html($trimmed);
}

function format_reservation_created_at_value(?string $createdAt): string
{
    if ($createdAt === null || trim($createdAt) === '') {
        return '—';
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        return date('F j, Y • g:i A', $timestamp);
    }

    return escape_html(trim($createdAt));
}

function format_reservation_text_value(?string $value, string $fallback = '—'): string
{
    if ($value === null) {
        return $fallback;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return $fallback;
    }

    return escape_html($trimmed);
}

/**
 * @param array<int, array<string, mixed>> $attachments
 * @return array<int, array{path: string, file_name: string, label: string}>
 */
function prepare_view_attachments(array $attachments): array
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

$reservation = null;
$errorTitle = null;
$errorMessage = null;
$statusCode = 200;

$reservationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($reservationId === null || $reservationId === false || $reservationId <= 0) {
    $statusCode = 400;
    $errorTitle = 'Invalid reservation selected';
    $errorMessage = 'We could not understand which reservation you wanted to view. Please go back and try again.';
} else {
    try {
        $reservation = fetch_reservation_by_id($reservationId);
        if ($reservation === null) {
            $statusCode = 404;
            $errorTitle = 'Reservation not found';
            $errorMessage = 'The reservation you are looking for may have been removed or no longer exists.';
        }
    } catch (Throwable $exception) {
        $statusCode = 500;
        $errorTitle = 'We ran into a problem';
        $errorMessage = 'Something went wrong while loading the reservation details. Please return to the dashboard and try again.';
    }
}

http_response_code($statusCode);

$statusStyles = [
    'approved' => [
        'label' => 'Approved',
        'accent' => '#0f766e',
        'accent_soft' => 'rgba(15, 118, 110, 0.12)',
        'badge_bg' => 'rgba(15, 118, 110, 0.15)',
        'badge_text' => '#115e59',
        'badge_border' => 'rgba(15, 118, 110, 0.35)',
    ],
    'declined' => [
        'label' => 'Declined',
        'accent' => '#dc2626',
        'accent_soft' => 'rgba(220, 38, 38, 0.1)',
        'badge_bg' => 'rgba(220, 38, 38, 0.14)',
        'badge_text' => '#991b1b',
        'badge_border' => 'rgba(220, 38, 38, 0.32)',
    ],
    'pending' => [
        'label' => 'Pending',
        'accent' => '#4f46e5',
        'accent_soft' => 'rgba(79, 70, 229, 0.12)',
        'badge_bg' => 'rgba(79, 70, 229, 0.14)',
        'badge_text' => '#3730a3',
        'badge_border' => 'rgba(79, 70, 229, 0.32)',
    ],
];

$statusNormalized = 'pending';
$statusLabel = 'Pending';
$statusStyle = $statusStyles['pending'];

if ($reservation !== null) {
    $statusNormalizedCandidate = strtolower(trim((string) ($reservation['status'] ?? 'pending')));
    if (isset($statusStyles[$statusNormalizedCandidate])) {
        $statusNormalized = $statusNormalizedCandidate;
        $statusStyle = $statusStyles[$statusNormalizedCandidate];
        $statusLabel = $statusStyle['label'];
    }
}

$documentTitle = 'Reservation Details';
if ($reservation !== null) {
    $reservationIdDisplay = isset($reservation['id']) && (int) $reservation['id'] > 0
        ? '#' . (int) $reservation['id']
        : 'Unassigned reservation';
    $documentTitle = 'Reservation ' . $reservationIdDisplay;
}

$styleAttributes = sprintf(
    '--accent-color:%s; --accent-soft:%s; --badge-bg:%s; --badge-text:%s; --badge-border:%s;',
    escape_html($statusStyle['accent']),
    escape_html($statusStyle['accent_soft']),
    escape_html($statusStyle['badge_bg']),
    escape_html($statusStyle['badge_text']),
    escape_html($statusStyle['badge_border'])
);

$preparedAttachments = $reservation !== null
    ? prepare_view_attachments($reservation['attachments'] ?? [])
    : [];

$attachmentCount = count($preparedAttachments);
$notes = $reservation !== null ? trim((string) ($reservation['notes'] ?? '')) : '';
$hasNotes = $notes !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($documentTitle); ?> · Admin Dashboard</title>
    <style>
        :root {
            color-scheme: light;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(120% 120% at 50% 0%, rgba(15, 23, 42, 0.92) 0%, rgba(15, 23, 42, 0.98) 30%, #020617 100%);
            color: #0f172a;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 48px 16px 64px;
        }

        .page-shell {
            width: min(940px, 100%);
            background: rgba(255, 255, 255, 0.98);
            border-radius: 32px;
            box-shadow: 0 50px 120px rgba(15, 23, 42, 0.35);
            overflow: hidden;
            position: relative;
        }

        .page-shell::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(180% 130% at 80% -40%, rgba(255, 255, 255, 0.65) 0%, transparent 55%),
                radial-gradient(120% 120% at 10% 0%, rgba(226, 232, 240, 0.8) 0%, transparent 60%);
        }

        .page-content {
            position: relative;
            padding: 40px;
            backdrop-filter: blur(6px);
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 32px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 32px;
            color: #0f172a;
            letter-spacing: -0.03em;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.04em;
            background: var(--badge-bg);
            color: var(--badge-text);
            border: 1px solid var(--badge-border);
            text-transform: uppercase;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--badge-text);
            box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.18);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 0.02em;
            margin-bottom: 24px;
        }

        .back-link span {
            border-bottom: 1px solid transparent;
            padding-bottom: 2px;
            transition: border-color 0.2s ease;
        }

        .back-link:hover span,
        .back-link:focus span {
            border-color: currentColor;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .info-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 22px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.12);
        }

        .info-card h2 {
            margin: 0 0 16px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #475569;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 16px;
            color: #0f172a;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .accent {
            color: var(--accent-color);
        }

        .notes-card,
        .attachments-card {
            background: #ffffff;
            border-radius: 26px;
            padding: 30px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.1);
            margin-bottom: 24px;
        }

        .section-heading {
            margin: 0 0 16px 0;
            font-size: 20px;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .notes-content {
            background: var(--accent-soft);
            border-radius: 20px;
            padding: 24px;
            line-height: 1.7;
            font-size: 15px;
            color: #1e293b;
            white-space: pre-line;
        }

        .empty-state {
            margin: 0;
            padding: 20px;
            border-radius: 18px;
            background: rgba(226, 232, 240, 0.35);
            color: #64748b;
            font-size: 15px;
        }

        .attachments-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 14px;
        }

        .attachments-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-radius: 18px;
            background: rgba(248, 250, 252, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .attachment-label {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .attachment-label span:first-child {
            font-weight: 600;
            color: #0f172a;
        }

        .attachment-label span:last-child {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--accent-color);
            border-radius: 999px;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.02em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .attachment-link:hover,
        .attachment-link:focus {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.18);
        }

        .error-card {
            text-align: center;
            padding: 48px 32px;
            background: rgba(248, 113, 113, 0.08);
            border-radius: 28px;
            border: 1px solid rgba(248, 113, 113, 0.45);
            color: #991b1b;
        }

        .error-card h2 {
            margin-top: 0;
            font-size: 26px;
        }

        .error-card p {
            font-size: 16px;
            line-height: 1.6;
            color: rgba(69, 10, 10, 0.85);
        }

        @media (max-width: 720px) {
            body {
                padding: 24px 12px 48px;
            }

            .page-content {
                padding: 32px 24px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .notes-card,
            .attachments-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body style="<?php echo $styleAttributes; ?>">
    <div class="page-shell">
        <div class="page-content">
            <a class="back-link" href="admin.php?section=reservations">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9.5 3.5L5 8L9.5 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Back to reservations</span>
            </a>

            <?php if ($reservation !== null): ?>
                <div class="page-header">
                    <h1><?php echo escape_html($documentTitle); ?></h1>
                    <span class="status-badge">
                        <span class="status-indicator" aria-hidden="true"></span>
                        <?php echo escape_html($statusLabel); ?>
                    </span>
                </div>

                <div class="info-grid">
                    <div class="info-card">
                        <h2>Guest details</h2>
                        <div class="info-item">
                            <span class="info-label">Guest name</span>
                            <span class="info-value"><?php echo format_reservation_text_value($reservation['name'] ?? '', '—'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email address</span>
                            <span class="info-value accent">
                                <?php echo format_reservation_text_value($reservation['email'] ?? '', 'Not provided'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone number</span>
                            <span class="info-value"><?php echo format_reservation_text_value($reservation['phone'] ?? '', 'Not provided'); ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h2>Event summary</h2>
                        <div class="info-item">
                            <span class="info-label">Event type</span>
                            <span class="info-value"><?php echo format_reservation_text_value($reservation['event_type'] ?? '', 'Not specified'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Preferred date</span>
                            <span class="info-value"><?php echo format_reservation_date_value($reservation['preferred_date'] ?? null); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Preferred time</span>
                            <span class="info-value"><?php echo format_reservation_time_value($reservation['preferred_time'] ?? null); ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h2>Submission</h2>
                        <div class="info-item">
                            <span class="info-label">Reservation ID</span>
                            <span class="info-value"><?php echo isset($reservation['id']) ? '#' . escape_html((string) $reservation['id']) : '—'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Submitted on</span>
                            <span class="info-value"><?php echo format_reservation_created_at_value($reservation['created_at'] ?? null); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value accent"><?php echo escape_html($statusLabel); ?></span>
                        </div>
                    </div>
                </div>

                <div class="notes-card">
                    <h2 class="section-heading">Notes &amp; special requests</h2>
                    <?php if ($hasNotes): ?>
                        <div class="notes-content"><?php echo nl2br(escape_html($notes)); ?></div>
                    <?php else: ?>
                        <p class="empty-state">No special instructions were included with this reservation.</p>
                    <?php endif; ?>
                </div>

                <div class="attachments-card">
                    <h2 class="section-heading">Attachments</h2>
                    <?php if ($attachmentCount > 0): ?>
                        <ul class="attachments-list">
                            <?php $attachmentIndex = 0; ?>
                            <?php foreach ($preparedAttachments as $attachment): ?>
                                <?php
                                $attachmentIndex++;
                                $displayLabel = $attachment['label'] !== ''
                                    ? $attachment['label']
                                    : 'Download attachment';
                                if ($attachment['label'] === '' && $attachmentCount > 1) {
                                    $displayLabel .= ' #' . $attachmentIndex;
                                }
                                ?>
                                <li>
                                    <div class="attachment-label">
                                        <span><?php echo escape_html($displayLabel); ?></span>
                                        <span><?php echo escape_html($attachment['file_name']); ?></span>
                                    </div>
                                    <a class="attachment-link" href="<?php echo escape_html($attachment['path']); ?>" download="<?php echo escape_html($attachment['file_name']); ?>">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M9 2.25V11.25" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M5.25 7.5L9 11.25L12.75 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M3 12.75V13.5C3 14.7426 4.00736 15.75 5.25 15.75H12.75C13.9926 15.75 15 14.7426 15 13.5V12.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                        </svg>
                                        <span>Download</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-state">No files were attached to this reservation.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="error-card">
                    <h2><?php echo escape_html($errorTitle ?? 'Unable to display reservation'); ?></h2>
                    <p><?php echo escape_html($errorMessage ?? 'Please return to the reservations dashboard and try again.'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
