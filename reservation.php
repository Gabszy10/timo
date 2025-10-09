<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once __DIR__ . '/includes/db_connection.php';

function send_reservation_notification_email(array $reservationDetails, $adminUrl)
{

    $mail = new PHPMailer(true);

    $smtpUsername = 'gospelbaracael@gmail.com';
    $smtpPassword = 'nbawqssfjeovyaxv';
    $senderAddress = $smtpUsername;
    $senderName = 'St. Helena Parish Reservations';

    $notificationRecipient = 'gospelbaracael@gmail.com';
    $notificationSubject = 'New reservation submitted';

    $escapedAdminUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');
    $attachmentsHtml = '';
    $attachmentsAltLines = [];
    $weddingDetailsHtml = '';
    $weddingDetailsAltLines = [];
    $funeralDetailsHtml = '';
    $funeralDetailsAltLines = [];

    if (!empty($reservationDetails['wedding_details']) && is_array($reservationDetails['wedding_details'])) {
        $weddingDetails = $reservationDetails['wedding_details'];

        $brideName = isset($weddingDetails['bride_name']) ? (string) $weddingDetails['bride_name'] : '';
        $groomName = isset($weddingDetails['groom_name']) ? (string) $weddingDetails['groom_name'] : '';
        $seminarDate = isset($weddingDetails['seminar_date']) ? (string) $weddingDetails['seminar_date'] : '';
        $seminarTime = isset($weddingDetails['seminar_time']) ? (string) $weddingDetails['seminar_time'] : '';
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
            $weddingDetailsRows .= '<tr><td style="font-weight:bold;">Bride:</td><td>' . $brideName . '</td></tr>';
            $weddingDetailsAltLines[] = 'Bride: ' . html_entity_decode(strip_tags($brideName), ENT_QUOTES, 'UTF-8');
        }
        if ($groomName !== '') {
            $weddingDetailsRows .= '<tr><td style="font-weight:bold;">Groom:</td><td>' . $groomName . '</td></tr>';
            $weddingDetailsAltLines[] = 'Groom: ' . html_entity_decode(strip_tags($groomName), ENT_QUOTES, 'UTF-8');
        }

        if ($seminarDate !== '' || $seminarTime !== '') {
            $seminarInfo = trim($seminarDate . ($seminarDate !== '' && $seminarTime !== '' ? ' at ' : '') . $seminarTime);
            if ($seminarInfo !== '') {
                $weddingDetailsRows .= '<tr><td style="font-weight:bold;">Seminar schedule:</td><td>' . $seminarInfo . '</td></tr>';
                $weddingDetailsAltLines[] = 'Seminar schedule: ' . html_entity_decode(strip_tags($seminarInfo), ENT_QUOTES, 'UTF-8');
            }
        }

        if ($sacramentDetails !== '') {
            $weddingDetailsRows .= '<tr><td style="font-weight:bold;">Kumpisa/Kumpil/Binyag:</td><td>' . $sacramentDetails . '</td></tr>';
            $weddingDetailsAltLines[] = 'Kumpisa/Kumpil/Binyag: ' . html_entity_decode(strip_tags($sacramentDetails), ENT_QUOTES, 'UTF-8');
        }

        if (!empty($requirementLabels)) {
            $requirementItems = '';
            foreach ($requirementLabels as $requirementLabel) {
                $requirementItems .= '<li>' . $requirementLabel . '</li>';
                $weddingDetailsAltLines[] = 'Requirement confirmed: ' . html_entity_decode(strip_tags($requirementLabel), ENT_QUOTES, 'UTF-8');
            }
            $weddingDetailsRows .= '<tr><td style="font-weight:bold; vertical-align: top;">Confirmed requirements:</td>'
                . '<td><ul style="margin: 0; padding-left: 18px;">' . $requirementItems . '</ul></td></tr>';
        }

        if ($weddingDetailsRows !== '') {
            $weddingDetailsHtml = '<tr><td colspan="2" style="padding-top: 12px;"><strong>Wedding information</strong></td></tr>'
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
            $funeralDetailsRows .= '<tr><td style="font-weight:bold;">Deceased:</td><td>' . $deceasedName . '</td></tr>';
            $funeralDetailsAltLines[] = 'Deceased: ' . html_entity_decode(strip_tags($deceasedName), ENT_QUOTES, 'UTF-8');
        }
        if ($maritalStatus !== '') {
            $funeralDetailsRows .= '<tr><td style="font-weight:bold;">Marital status:</td><td>' . $maritalStatus . '</td></tr>';
            $funeralDetailsAltLines[] = 'Marital status: ' . html_entity_decode(strip_tags($maritalStatus), ENT_QUOTES, 'UTF-8');
        }
        if ($officeReminder !== '') {
            $funeralDetailsRows .= '<tr><td style="font-weight:bold;">Parish office reminder:</td><td>' . $officeReminder . '</td></tr>';
            $funeralDetailsAltLines[] = 'Parish office reminder: ' . html_entity_decode(strip_tags($officeReminder), ENT_QUOTES, 'UTF-8');
        }

        if ($funeralDetailsRows !== '') {
            $funeralDetailsHtml = '<tr><td colspan="2" style="padding-top: 12px;"><strong>Funeral information</strong></td></tr>'
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

            $attachmentItems .= '<li>' . $label . ($filename !== '' ? ' &ndash; ' . $filename : '') . '</li>';
            $attachmentsAltLines[] = ($label !== '' ? $label . ': ' : '') . $filename;
        }

        if ($attachmentItems !== '') {
            $attachmentsHtml = '<tr><td style="font-weight:bold; vertical-align: top;">Uploaded documents:</td>'
                . '<td><ul style="margin: 0; padding-left: 18px;">' . $attachmentItems . '</ul></td></tr>';
        }
    }

    $notificationMessage = '<html><body style="font-family: Arial, sans-serif; color: #333;">'
        . '<h2 style="color: #2c3e50;">We just received a new booking!</h2>'
        . '<p>Hi there,</p>'
        . '<p>Great news, a new reservation has been submitted on the website. Here are the details:</p>'
        . '<table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">'
        . '<tr><td style="font-weight:bold;">Name:</td><td>' . $reservationDetails['name'] . '</td></tr>'
        . '<tr><td style="font-weight:bold;">Email:</td><td>' . $reservationDetails['email'] . '</td></tr>'
        . '<tr><td style="font-weight:bold;">Phone:</td><td>' . $reservationDetails['phone'] . '</td></tr>'
        . '<tr><td style="font-weight:bold;">Event type:</td><td>' . $reservationDetails['event_type'] . '</td></tr>'
        . '<tr><td style="font-weight:bold;">Preferred date:</td><td>' . $reservationDetails['preferred_date'] . '</td></tr>'
        . '<tr><td style="font-weight:bold;">Preferred time:</td><td>' . $reservationDetails['preferred_time'] . '</td></tr>'
        . $weddingDetailsHtml
        . $funeralDetailsHtml
        . '<tr><td style="font-weight:bold;">Notes:</td><td>' . $reservationDetails['notes_html'] . '</td></tr>'
        . $attachmentsHtml
        . '</table>'
        . '<p style="margin-top: 20px;">You can review and manage this booking from the admin dashboard.</p>'
        . '<p style="margin: 30px 0; text-align: center;">'
        . '<a href="' . $escapedAdminUrl . '" '
        . 'style="display: inline-block; padding: 12px 24px; background-color: #3498db; color: #ffffff; text-decoration: none; '
        . 'border-radius: 4px; font-weight: bold;">Open Admin Dashboard</a>'
        . '</p>'
        . '<p style="font-size: 14px; color: #666;">Thank you for keeping an eye on new reservations!</p>'
        . '</body></html>';

    $altBodyLines = [
        'We just received a new booking!',
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

    if (!empty($weddingDetailsAltLines)) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Wedding information:';
        foreach ($weddingDetailsAltLines as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($funeralDetailsAltLines)) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Funeral information:';
        foreach ($funeralDetailsAltLines as $altLine) {
            $altBodyLines[] = ' - ' . $altLine;
        }
    }

    if (!empty($attachmentsAltLines)) {
        $altBodyLines[] = '';
        $altBodyLines[] = 'Uploaded documents:';
        foreach ($attachmentsAltLines as $line) {
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

            if (!array_key_exists($normalizedDate, $grouped)) {
                $grouped[$normalizedDate] = [
                    'date' => $normalizedDate,
                    'reservations' => [],
                ];
            }

            $grouped[$normalizedDate]['reservations'][] = [
                'name' => isset($row['name']) ? trim((string) $row['name']) : '',
                'eventType' => isset($row['event_type']) ? trim((string) $row['event_type']) : '',
                'preferredTime' => isset($row['preferred_time']) ? trim((string) $row['preferred_time']) : '',
            ];
        }
        mysqli_free_result($result);
    }

    mysqli_close($connection);

    return array_values($grouped);
}

$successMessage = '';
$errorMessage = '';
$emailStatusMessage = '';
$emailStatusSuccess = null;

$formData = [
    'reservation-name' => '',
    'reservation-email' => '',
    'reservation-phone' => '',
    'reservation-type' => 'Baptism',
    'reservation-date' => '',
    'reservation-time' => '',
    'reservation-notes' => '',
    'wedding-bride-name' => '',
    'wedding-groom-name' => '',
    'wedding-seminar-date' => '',
    'wedding-seminar-time' => '',
    'wedding-sacrament-details' => '',
    'funeral-deceased-name' => '',
    'funeral-marital-status' => '',
];

$normalizedPreferredDate = null;
$uploadedFiles = [];
$selectedWeddingRequirements = [];
$funeralMaritalStatusOptions = [
    'married_not_baptized' => 'Married (not baptized in the church)',
    'single' => 'Single / Unmarried',
];

$supportedAttachmentRequirements = [
    'Baptism' => [
        'baptism-birth-certificate' => 'Birth certificate of the child (Xerox)',
        'baptism-parent-marriage-contract' => 'Marriage contract of parents (Xerox)',
    ],
    'Wedding' => [],
    'Funeral' => [],
];
$funeralAttachmentLabels = [
    'married_not_baptized' => 'Marriage contract of the deceased (if married but not baptized in the church)',
    'single' => 'Baptismal certificate of the deceased (if single or unmarried)',
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
    foreach ($formData as $field => $default) {
        if (isset($_POST[$field])) {
            $formData[$field] = trim((string) $_POST[$field]);
        }
    }

    if (isset($_POST['wedding-requirements']) && is_array($_POST['wedding-requirements'])) {
        $postedRequirements = array_map('strval', $_POST['wedding-requirements']);
        $selectedWeddingRequirements = array_values(array_intersect($postedRequirements, array_keys($weddingRequirementChecklist)));
    } else {
        $selectedWeddingRequirements = [];
    }

    if ($formData['reservation-name'] === '') {
        $errorMessage = 'Please enter the name of the person reserving.';
    } elseif (!filter_var($formData['reservation-email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($formData['reservation-phone'] === '') {
        $errorMessage = 'Please provide a contact number.';
    } elseif ($formData['reservation-type'] === '') {
        $errorMessage = 'Please select an event type.';
    } elseif (!array_key_exists($formData['reservation-type'], $supportedAttachmentRequirements)) {
        $errorMessage = 'The selected event type is not supported at this time. Please choose a different option.';
    } elseif ($formData['reservation-date'] === '') {
        $errorMessage = 'Please choose a preferred date.';
    } elseif ($formData['reservation-time'] === '') {
        $errorMessage = 'Please choose a preferred time.';
    } else {
        $normalizedPreferredDate = format_reservation_date_for_storage($formData['reservation-date']);
        if ($normalizedPreferredDate === null) {
            $errorMessage = 'Please choose a valid preferred date.';
        }
    }

    $requiredAttachments = [];
    if ($errorMessage === '') {
        if ($formData['reservation-type'] === 'Wedding') {
            if ($formData['wedding-bride-name'] === '' || $formData['wedding-groom-name'] === '') {
                $errorMessage = 'Please provide the names of both individuals getting married.';
            } elseif ($formData['wedding-seminar-date'] === '') {
                $errorMessage = 'Please enter the seminar date.';
            } elseif ($formData['wedding-seminar-time'] === '') {
                $errorMessage = 'Please enter the seminar time.';
            } else {
                $missingRequirements = array_diff(array_keys($weddingRequirementChecklist), $selectedWeddingRequirements);
                if (!empty($missingRequirements)) {
                    $errorMessage = 'Please confirm all pre-wedding requirements.';
                }
            }
        } elseif ($formData['reservation-type'] === 'Funeral') {
            if ($formData['funeral-deceased-name'] === '') {
                $errorMessage = 'Please provide the name of the deceased.';
            } elseif (!array_key_exists($formData['funeral-marital-status'], $funeralMaritalStatusOptions)) {
                $errorMessage = 'Please select the marital status of the deceased.';
            } else {
                if ($formData['funeral-marital-status'] === 'married_not_baptized') {
                    $requiredAttachments = [
                        'funeral-marriage-contract' => $funeralAttachmentLabels['married_not_baptized'],
                    ];
                } else {
                    $requiredAttachments = [
                        'funeral-baptismal-certificate' => $funeralAttachmentLabels['single'],
                    ];
                }
            }
        } else {
            $requiredAttachments = $supportedAttachmentRequirements[$formData['reservation-type']] ?? [];
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
                        'path' => 'uploads/reservations/' . $finalFileName,
                    ];
                }

                if ($errorMessage !== '') {
                    foreach ($uploadedFiles as $storedFile) {
                        if (!is_array($storedFile) || !isset($storedFile['path'])) {
                            continue;
                        }

                        $storedPath = __DIR__ . '/' . ltrim((string) $storedFile['path'], '/');
                        if (is_file($storedPath)) {
                            @unlink($storedPath);
                        }
                    }
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
                '- Seminar schedule: ' . $formData['wedding-seminar-date'] . ' at ' . $formData['wedding-seminar-time'],
            ];

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
            $notesWithUploads .= ($notesWithUploads !== '' ? "\n\n" : '');
            $notesWithUploads .= "Uploaded files:";
            foreach ($uploadedFiles as $uploadedFile) {
                $notesWithUploads .= "\n- " . $uploadedFile['label'] . ': ' . $uploadedFile['filename'];
            }
            $formData['reservation-notes'] = $notesWithUploads;
        }

        try {
            $connection = get_db_connection();

            $insertQuery = 'INSERT INTO reservations (name, email, phone, event_type, preferred_date, preferred_time, notes) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $statement = mysqli_prepare($connection, $insertQuery);

            if ($statement === false) {
                mysqli_close($connection);
                throw new Exception('Failed to prepare reservation statement: ' . mysqli_error($connection));
            }

            $preferredDate = $normalizedPreferredDate ?? $formData['reservation-date'];
            $preferredTime = $formData['reservation-time'];

            mysqli_stmt_bind_param(
                $statement,
                'sssssss',
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

            mysqli_stmt_close($statement);
            mysqli_close($connection);

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
                    'seminar_time' => htmlspecialchars($formData['wedding-seminar-time'], ENT_QUOTES, 'UTF-8'),
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

            $successMessage = 'Thank you! Your reservation request has been saved. We will contact you soon to confirm the details.';

            foreach ($formData as $field => $default) {
                $formData[$field] = $field === 'reservation-type' ? 'Baptism' : '';
            }
            $selectedWeddingRequirements = [];
        } catch (Exception $exception) {
            if (isset($statement) && $statement instanceof mysqli_stmt) {
                mysqli_stmt_close($statement);
            }
            if (isset($connection) && $connection instanceof mysqli) {
                mysqli_close($connection);
            }
            $errorMessage = $exception->getMessage();
        }
    }
}

try {
    $approvedReservationSummaries = load_approved_reservations_grouped_by_date();
} catch (Exception $exception) {
    $approvedReservationSummaries = [];
}

$approvedReservationsJson = json_encode($approvedReservationSummaries);
if ($approvedReservationsJson === false) {
    $approvedReservationsJson = '[]';
}

$shouldOpenReservationModal = ($successMessage !== '' || $errorMessage !== '' || $emailStatusMessage !== '');
$shouldDisplayReservationForm = $shouldOpenReservationModal;
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
    <title>Reservations | St. Helena Parish</title>
    <meta name="description" content="Reserve a sacrament or church service at St. Helena Parish.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

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
</head>

<body>
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">
                        <div class="col-xl-5 col-lg-6">
                            <div class="main-menu d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                        <li><a href="index.php">Home</a></li>
                                        <li><a href="about.php">About</a></li>
                                        <li><a href="schedule.php">Schedule</a></li>
                                        <li><a href="contact.php">Contact</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <div class="col-xl-2 col-lg-2">
                            <div class="logo-img">
                                <a href="index.php">
                                    <img src="img/logo.png" alt="St. Helena Parish">
                                </a>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-4 d-none d-lg-block">
                            <div class="book_room">
                                <div class="socail_links">
                                    <ul>
                                        <li><a href="#"><i class="fa fa-facebook-square"></i></a></li>
                                        <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                                        <li><a href="#"><i class="fa fa-instagram"></i></a></li>
                                    </ul>
                                </div>
                                <div class="book_btn d-none d-lg-block">
                                    <button type="button" class="boxed-btn3" data-toggle="modal"
                                        data-target="#reservationDayModal">Reserve Now</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
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
            <div class="row">
                <div class="col-12">
                    <div class="reservation_calendar mb-5">
                        <h4 class="mb-4">Availability Preview</h4>
                        <p class="mb-4">The calendar below highlights dates that are no longer available. Look for the
                            <span class="badge badge-danger">Booked</span> tag—days without a tag remain open for new
                            reservations.
                        </p>
                        <div class="calendar_legend mb-3">
                            <span><span class="legend booked"></span> Booked</span>
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
                        <button type="button" class="boxed-btn3" data-toggle="modal"
                            data-target="#reservationDayModal">Start a Reservation</button>
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

    <footer class="footer">
        <div class="footer_top">
            <div class="container">
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-lg-3">
                        <div class="footer_widget">
                            <h3 class="footer_title">About St. Helena</h3>
                            <p>P. Burgos Street<br>Barangay 1, Batangas City<br>
                                Batangas 4200, Philippines<br>
                                <a href="tel:+11234567890">(123) 456-7890</a><br>
                                <a href="mailto:office@sthelenaparish.org">office@sthelenaparish.org</a>
                            </p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-lg-3">
                        <div class="footer_widget">
                            <h3 class="footer_title">Quick Links</h3>
                            <ul>
                                <li><a href="reservation.php">Reserve a sacrament</a></li>
                                <li><a href="schedule.php">Parish calendar</a></li>
                                <li><a href="services.php">Ministry services</a></li>
                                <li><a href="contact.php">Get in touch</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-lg-3">
                        <div class="footer_widget">
                            <h3 class="footer_title">Mass Times</h3>
                            <p>Saturday Vigil – 5:00 PM<br>Sunday – 8:00 AM, 10:00 AM, 6:00 PM<br>Weekdays – 12:10 PM
                            </p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-lg-3">
                        <div class="footer_widget">
                            <h3 class="footer_title">Follow Us</h3>
                            <div class="socail_links">
                                <ul>
                                    <li><a href="#"><i class="fa fa-facebook-square"></i></a></li>
                                    <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                                    <li><a href="#"><i class="fa fa-instagram"></i></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <div class="modal fade reservation_day_modal" id="reservationDayModal" tabindex="-1" role="dialog"
        aria-labelledby="reservationDayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationDayModalLabel">Start a Reservation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div data-reservation-messages>
                        <?php if ($successMessage !== ''): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($emailStatusMessage !== ''): ?>
                            <div class="alert <?php echo $emailStatusSuccess ? 'alert-info' : 'alert-warning'; ?>" role="alert">
                                <?php echo htmlspecialchars($emailStatusMessage, ENT_QUOTES); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($errorMessage !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="reservation_modal_content">
                        <div class="reservation_modal_sidebar mb-4">
                            <h6 class="text-uppercase text-muted">Availability preview</h6>
                            <div data-reservation-availability>
                                <p class="mb-2">Select a date on the calendar to see existing approved reservations and
                                    prefill the request form.</p>
                                <p class="small text-muted mb-0">Dates without a <span class="badge badge-danger">Booked</span>
                                    tag remain open for requests.</p>
                            </div>
                        </div>
                        <button type="button"
                            class="boxed-btn3 w-100 mb-4<?php echo $shouldDisplayReservationForm ? ' d-none' : ''; ?>"
                            data-reservation-start>
                            Make a Reservation
                        </button>
                        <form id="reservation-form" class="reservation_form<?php echo $shouldDisplayReservationForm ? '' : ' d-none'; ?>"
                            method="post"
                            action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>"
                            enctype="multipart/form-data" data-server-handled="true" data-reservation-form>
                                    <div class="form-group">
                                        <label for="reservation-name">Name of person reserving *</label>
                                        <input type="text" id="reservation-name" name="reservation-name"
                                            class="form-control" placeholder="Full name" required
                                            value="<?php echo htmlspecialchars($formData['reservation-name'], ENT_QUOTES); ?>">
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
                                            class="form-control" placeholder="(123) 456-7890" required
                                            value="<?php echo htmlspecialchars($formData['reservation-phone'], ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="d-block">Event type *</label>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="reservation-type-baptism" name="reservation-type"
                                                class="custom-control-input" value="Baptism" required
                                                <?php echo $formData['reservation-type'] === 'Baptism' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="reservation-type-baptism">Baptism</label>
                                        </div>
                                        <div class="custom-control custom-radio mt-2">
                                            <input type="radio" id="reservation-type-wedding" name="reservation-type"
                                                class="custom-control-input" value="Wedding"
                                                <?php echo $formData['reservation-type'] === 'Wedding' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="reservation-type-wedding">Wedding
                                                <small class="d-block text-muted">Document upload coming soon</small>
                                            </label>
                                        </div>
                                        <div class="custom-control custom-radio mt-2">
                                            <input type="radio" id="reservation-type-funeral" name="reservation-type"
                                                class="custom-control-input" value="Funeral"
                                                <?php echo $formData['reservation-type'] === 'Funeral' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="reservation-type-funeral">Funeral
                                                <small class="d-block text-muted">Coordinate with the parish office a day before burial</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="reservation-date">Preferred date *</label>
                                            <input type="date" id="reservation-date" name="reservation-date"
                                                class="form-control" required
                                                value="<?php echo htmlspecialchars($formData['reservation-date'], ENT_QUOTES); ?>">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="reservation-time">Preferred time *</label>
                                            <input type="time" id="reservation-time" name="reservation-time"
                                                class="form-control" required
                                                value="<?php echo htmlspecialchars($formData['reservation-time'], ENT_QUOTES); ?>">
                                        </div>
                                    </div>
                                    <div id="wedding-details" class="reservation_attachment_box mb-4">
                                        <h6 class="mb-3">Wedding information</h6>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="wedding-bride-name">Bride's full name *</label>
                                                <input type="text" class="form-control" id="wedding-bride-name"
                                                    name="wedding-bride-name" placeholder="Name of bride"
                                                    value="<?php echo htmlspecialchars($formData['wedding-bride-name'], ENT_QUOTES); ?>"
                                                    data-wedding-required="true">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="wedding-groom-name">Groom's full name *</label>
                                                <input type="text" class="form-control" id="wedding-groom-name"
                                                    name="wedding-groom-name" placeholder="Name of groom"
                                                    value="<?php echo htmlspecialchars($formData['wedding-groom-name'], ENT_QUOTES); ?>"
                                                    data-wedding-required="true">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="wedding-seminar-date">Seminar date *</label>
                                                <input type="date" class="form-control" id="wedding-seminar-date"
                                                    name="wedding-seminar-date"
                                                    value="<?php echo htmlspecialchars($formData['wedding-seminar-date'], ENT_QUOTES); ?>"
                                                    data-wedding-required="true">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="wedding-seminar-time">Seminar time *</label>
                                                <input type="time" class="form-control" id="wedding-seminar-time"
                                                    name="wedding-seminar-time"
                                                    value="<?php echo htmlspecialchars($formData['wedding-seminar-time'], ENT_QUOTES); ?>"
                                                    data-wedding-required="true">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="wedding-sacrament-details">Kumpisa / Kumpil / Binyag details</label>
                                            <textarea class="form-control" id="wedding-sacrament-details"
                                                name="wedding-sacrament-details" rows="3"
                                                placeholder="Parishes or dates for confession, confirmation, and baptism"><?php echo htmlspecialchars($formData['wedding-sacrament-details'], ENT_QUOTES); ?></textarea>
                                        </div>
                                        <div class="form-group mb-0">
                                            <h6 class="mb-2">Mga kailangan bago ikasal *</h6>
                                            <p class="small text-muted">Please confirm that you have prepared the following requirements.</p>
                                            <?php foreach ($weddingRequirementChecklist as $requirementKey => $requirementLabel): ?>
                                                <?php $inputId = 'wedding-requirement-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $requirementKey); ?>
                                                <div class="custom-control custom-checkbox mb-1">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>"
                                                        name="wedding-requirements[]"
                                                        value="<?php echo htmlspecialchars($requirementKey, ENT_QUOTES); ?>"
                                                        <?php echo in_array($requirementKey, $selectedWeddingRequirements, true) ? 'checked' : ''; ?>
                                                        data-wedding-required="true" data-wedding-checkbox="true">
                                                    <label class="custom-control-label" for="<?php echo htmlspecialchars($inputId, ENT_QUOTES); ?>">
                                                        <?php echo htmlspecialchars($requirementLabel, ENT_QUOTES); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div id="funeral-details" class="reservation_attachment_box mb-4">
                                        <h6 class="mb-3">Funeral information</h6>
                                        <div class="alert alert-warning small" role="alert">
                                            Arrange or reserve the funeral schedule at the parish office at least one day before the burial to avoid delays or declined requests.
                                        </div>
                                        <div class="form-group">
                                            <label for="funeral-deceased-name">Name of the deceased *</label>
                                            <input type="text" class="form-control" id="funeral-deceased-name"
                                                name="funeral-deceased-name" placeholder="Full name of the deceased"
                                                value="<?php echo htmlspecialchars($formData['funeral-deceased-name'], ENT_QUOTES); ?>"
                                                data-funeral-required="true">
                                        </div>
                                        <div class="form-group">
                                            <label for="funeral-marital-status">Marital status of the deceased *</label>
                                            <select class="form-control" id="funeral-marital-status" name="funeral-marital-status"
                                                data-funeral-required="true" data-funeral-marital-select="true">
                                                <option value="">Select status</option>
                                                <?php foreach ($funeralMaritalStatusOptions as $statusValue => $statusLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES); ?>"
                                                        <?php echo $formData['funeral-marital-status'] === $statusValue ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div id="funeral-attachments" class="reservation_attachment_box_inner">
                                            <p class="small text-muted">Upload the document that matches the marital status selected above. Accepted formats: PDF, JPG, PNG (max 5MB).</p>
                                            <div class="form-group" data-funeral-marital-group="married_not_baptized">
                                                <label for="funeral-marriage-contract">Marriage contract of the deceased *</label>
                                                <input type="file" class="form-control-file" id="funeral-marriage-contract"
                                                    name="funeral-marriage-contract" accept=".pdf,.jpg,.jpeg,.png"
                                                    data-funeral-file="true">
                                            </div>
                                            <div class="form-group" data-funeral-marital-group="single">
                                                <label for="funeral-baptismal-certificate">Baptismal certificate of the deceased *</label>
                                                <input type="file" class="form-control-file" id="funeral-baptismal-certificate"
                                                    name="funeral-baptismal-certificate" accept=".pdf,.jpg,.jpeg,.png"
                                                    data-funeral-file="true">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="baptism-attachments" class="reservation_attachment_box mb-4">
                                        <h6 class="mb-3">Required Baptism documents</h6>
                                        <p class="small text-muted">Upload clear scans or photos of the following
                                            requirements. Accepted formats: PDF, JPG, PNG (max 5MB each).</p>
                                        <div class="form-group">
                                            <label for="baptism-birth-certificate">Birth certificate of the child *</label>
                                            <input type="file" class="form-control-file" id="baptism-birth-certificate"
                                                name="baptism-birth-certificate" accept=".pdf,.jpg,.jpeg,.png"
                                                <?php echo $formData['reservation-type'] === 'Baptism' ? 'required' : ''; ?>
                                                data-baptism-required="true">
                                        </div>
                                        <div class="form-group">
                                            <label for="baptism-parent-marriage-contract">Marriage contract of parents *</label>
                                            <input type="file" class="form-control-file"
                                                id="baptism-parent-marriage-contract"
                                                name="baptism-parent-marriage-contract" accept=".pdf,.jpg,.jpeg,.png"
                                                <?php echo $formData['reservation-type'] === 'Baptism' ? 'required' : ''; ?>
                                                data-baptism-required="true">
                                        </div>
                                        <ul class="small pl-3 text-left">
                                            <li>Choose one godfather and one godmother as major sponsors (proxies are not
                                                allowed).</li>
                                            <li>Major sponsors must be practicing Catholics in good standing.</li>
                                            <li>Suggested church donation: <strong>P800</strong>.</li>
                                            <li>Please bring original documents to the parish office on the day of
                                                baptism.</li>
                                        </ul>
                                    </div>
                                    <div class="form-group">
                                        <label for="reservation-notes">Additional notes or requests</label>
                                        <textarea id="reservation-notes" name="reservation-notes" class="form-control"
                                            rows="4" placeholder="Tell us about your celebration"><?php echo htmlspecialchars($formData['reservation-notes'], ENT_QUOTES); ?></textarea>
                                    </div>
                                    <button type="submit" class="boxed-btn3 w-100">Submit Reservation Request</button>
                        </form>
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
    <script src="js/reservations.js"></script>
</body>

</html>