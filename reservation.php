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
        . '<tr><td style="font-weight:bold;">Notes:</td><td>' . $reservationDetails['notes_html'] . '</td></tr>'
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
 * Fetch approved reservation dates from the database for the calendar widget.
 */
function load_approved_reservation_dates()
{
    $connection = get_db_connection();

    $dates = [];
    $query = "SELECT preferred_date FROM reservations WHERE status = 'approved'";
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        $fallbackQuery = 'SELECT preferred_date FROM reservations';
        $result = mysqli_query($connection, $fallbackQuery);
    }

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($row['preferred_date'])) {
                $normalized = format_reservation_date_for_storage($row['preferred_date']);
                if ($normalized !== null) {
                    $dates[] = $normalized;
                }
            }
        }
        mysqli_free_result($result);
    }

    mysqli_close($connection);

    return $dates;
}

$successMessage = '';
$errorMessage = '';
$emailStatusMessage = '';
$emailStatusSuccess = null;

$formData = [
    'reservation-name' => '',
    'reservation-email' => '',
    'reservation-phone' => '',
    'reservation-type' => '',
    'reservation-date' => '',
    'reservation-time' => '',
    'reservation-notes' => '',
];

$normalizedPreferredDate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $default) {
        if (isset($_POST[$field])) {
            $formData[$field] = trim((string) $_POST[$field]);
        }
    }

    if ($formData['reservation-name'] === '') {
        $errorMessage = 'Please enter the name of the person reserving.';
    } elseif (!filter_var($formData['reservation-email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($formData['reservation-phone'] === '') {
        $errorMessage = 'Please provide a contact number.';
    } elseif ($formData['reservation-type'] === '') {
        $errorMessage = 'Please select an event type.';
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

    if ($errorMessage === '') {
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
            ];

            send_reservation_notification_email($reservationDetails, $adminUrl);

            $successMessage = 'Thank you! Your reservation request has been saved. We will contact you soon to confirm the details.';

            foreach ($formData as $field => $default) {
                $formData[$field] = '';
            }
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
    $approvedReservationDates = array_values(array_unique(load_approved_reservation_dates()));
} catch (Exception $exception) {
    $approvedReservationDates = [];
}

$approvedReservationsJson = json_encode($approvedReservationDates);
if ($approvedReservationsJson === false) {
    $approvedReservationsJson = '[]';
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
                                    <a class="boxed-btn3" href="#reservation-form">Reserve Now</a>
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
        <div class="container" style="max-width: 1500px;">
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
                <div class="col-12 col-xl-10 mx-auto">
                    <form id="reservation-form" class="reservation_form" method="post"
                        action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>"
                        data-server-handled="true">
                        <h4 class="mb-4">Reservation Details</h4>
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
                        <div class="form-group">
                            <label for="reservation-name">Name of person reserving *</label>
                            <input type="text" id="reservation-name" name="reservation-name" class="form-control"
                                placeholder="Full name" required
                                value="<?php echo htmlspecialchars($formData['reservation-name'], ENT_QUOTES); ?>">
                        </div>
                        <div class="form-group">
                            <label for="reservation-email">Email *</label>
                            <input type="email" id="reservation-email" name="reservation-email" class="form-control"
                                placeholder="name@example.com" required
                                value="<?php echo htmlspecialchars($formData['reservation-email'], ENT_QUOTES); ?>">
                        </div>
                        <div class="form-group">
                            <label for="reservation-phone">Contact number *</label>
                            <input type="tel" id="reservation-phone" name="reservation-phone" class="form-control"
                                placeholder="(123) 456-7890" required
                                value="<?php echo htmlspecialchars($formData['reservation-phone'], ENT_QUOTES); ?>">
                        </div>
                        <div class="form-group">
                            <label for="reservation-type">Type of event *</label>
                            <select id="reservation-type" name="reservation-type" class="form-control" required
                                style="height: 54px;">
                                <option value="" disabled <?php echo $formData['reservation-type'] === '' ? 'selected' : ''; ?>>Select an option</option>
                                <option value="Wedding" <?php echo $formData['reservation-type'] === 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                                <option value="Baptism" <?php echo $formData['reservation-type'] === 'Baptism' ? 'selected' : ''; ?>>Baptism</option>
                                <option value="Funeral Mass" <?php echo $formData['reservation-type'] === 'Funeral Mass' ? 'selected' : ''; ?>>Funeral Mass</option>
                                <option value="Confirmation" <?php echo $formData['reservation-type'] === 'Confirmation' ? 'selected' : ''; ?>>Confirmation</option>
                                <option value="Quinceañera" <?php echo $formData['reservation-type'] === 'Quinceañera' ? 'selected' : ''; ?>>Quinceañera</option>
                                <option value="Home or Business Blessing" <?php echo $formData['reservation-type'] === 'Home or Business Blessing' ? 'selected' : ''; ?>>
                                    Home or Business Blessing</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="reservation-date">Preferred date *</label>
                                <input type="text" id="reservation-date" name="reservation-date"
                                    class="form-control datepicker" placeholder="Select date" required
                                    value="<?php echo htmlspecialchars($formData['reservation-date'], ENT_QUOTES); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="reservation-time">Preferred time *</label>
                                <input type="time" id="reservation-time" name="reservation-time" class="form-control"
                                    required
                                    value="<?php echo htmlspecialchars($formData['reservation-time'], ENT_QUOTES); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reservation-notes">Additional notes or requests</label>
                            <textarea id="reservation-notes" name="reservation-notes" class="form-control" rows="4"
                                placeholder="Tell us about your celebration"><?php echo htmlspecialchars($formData['reservation-notes'], ENT_QUOTES); ?></textarea>
                        </div>
                        <button type="submit" class="boxed-btn3 w-100">Submit Reservation Request</button>
                    </form>
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
                            <p>1234 Grace Avenue<br>Springfield, USA 12345<br>
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

    <script>
        window.approvedReservations = <?php echo $approvedReservationsJson; ?>;
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