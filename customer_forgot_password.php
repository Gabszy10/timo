<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

customer_session_start();

if (get_logged_in_customer() !== null) {
    header('Location: reservation.php');
    exit;
}

$errorMessage = '';
$successMessage = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        /** @var mysqli|null $connection */
        $connection = null;

        try {
            $connection = get_db_connection();

            if (!mysqli_query($connection, 'DELETE FROM customer_password_resets WHERE expires_at <= UTC_TIMESTAMP()')) {
                throw new Exception('Unable to clean up expired password reset requests: ' . mysqli_error($connection));
            }

            $query = 'SELECT id, name FROM customers WHERE email = ? LIMIT 1';
            $statement = mysqli_prepare($connection, $query);

            if ($statement === false) {
                throw new Exception('Unable to prepare customer lookup statement: ' . mysqli_error($connection));
            }

            mysqli_stmt_bind_param($statement, 's', $email);

            if (!mysqli_stmt_execute($statement)) {
                $executionError = mysqli_stmt_error($statement);
                mysqli_stmt_close($statement);
                throw new Exception('Unable to execute customer lookup statement: ' . $executionError);
            }

            $result = mysqli_stmt_get_result($statement);
            $customerRow = $result ? mysqli_fetch_assoc($result) : null;

            if ($result instanceof mysqli_result) {
                mysqli_free_result($result);
            }

            mysqli_stmt_close($statement);

            if ($customerRow) {
                $customerId = (int) ($customerRow['id'] ?? 0);
                $customerName = trim((string) ($customerRow['name'] ?? ''));

                $deleteStatement = mysqli_prepare($connection, 'DELETE FROM customer_password_resets WHERE customer_id = ?');

                if ($deleteStatement === false) {
                    throw new Exception('Unable to prepare password reset cleanup statement: ' . mysqli_error($connection));
                }

                mysqli_stmt_bind_param($deleteStatement, 'i', $customerId);

                if (!mysqli_stmt_execute($deleteStatement)) {
                    $executionError = mysqli_stmt_error($deleteStatement);
                    mysqli_stmt_close($deleteStatement);
                    throw new Exception('Unable to execute password reset cleanup statement: ' . $executionError);
                }

                mysqli_stmt_close($deleteStatement);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $insertQuery = 'INSERT INTO customer_password_resets (customer_id, token_hash, expires_at) '
                    . 'VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))';
                $insertStatement = mysqli_prepare($connection, $insertQuery);

                if ($insertStatement === false) {
                    throw new Exception('Unable to prepare password reset insert statement: ' . mysqli_error($connection));
                }

                mysqli_stmt_bind_param($insertStatement, 'is', $customerId, $tokenHash);

                if (!mysqli_stmt_execute($insertStatement)) {
                    $executionError = mysqli_stmt_error($insertStatement);
                    mysqli_stmt_close($insertStatement);
                    throw new Exception('Unable to execute password reset insert statement: ' . $executionError);
                }

                mysqli_stmt_close($insertStatement);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDirectory = '';

                if (isset($_SERVER['PHP_SELF'])) {
                    $scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');

                    if ($scriptDirectory === '/' || $scriptDirectory === '.') {
                        $scriptDirectory = '';
                    }
                }

                $resetPath = $scriptDirectory . '/customer_reset_password.php?token=' . urlencode($token);
                $resetUrl = $scheme . '://' . $host . $resetPath;

                $mail = new PHPMailer(true);

                try {
                    $smtpUsername = 'gospelbaracael@gmail.com';
                    $smtpPassword = 'nbawqssfjeovyaxv';
                    $senderAddress = $smtpUsername;
                    $senderName = 'St. John the Baptist Parish Reservations';

                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUsername;
                    $mail->Password = $smtpPassword;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom($senderAddress, $senderName);
                    $mail->addAddress($email, $customerName !== '' ? $customerName : $email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Reset your St. John the Baptist Parish password';

                    $escapedName = htmlspecialchars($customerName !== '' ? $customerName : 'there', ENT_QUOTES, 'UTF-8');
                    $escapedResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

                    $mail->Body = '<p>Hi ' . $escapedName . ',</p>'
                        . '<p>We received a request to reset the password for your parish reservations account. '
                        . 'Use the secure button below to choose a new password. This link will expire in one hour.</p>'
                        . '<p style="margin: 24px 0; text-align: center;">'
                        . '<a href="' . $escapedResetUrl . '" style="display: inline-block; padding: 12px 24px; '
                        . 'background-color: #1f75fe; color: #ffffff; text-decoration: none; border-radius: 999px; '
                        . 'font-weight: 600;">Reset password</a>'
                        . '</p>'
                        . '<p>If you did not request a password reset, you can safely ignore this message. '
                        . 'Your current password will remain unchanged.</p>'
                        . '<p>God bless,<br>St. John the Baptist Parish</p>';

                    $mail->AltBody = 'Hi ' . ($customerName !== '' ? $customerName : 'there') . ",\n\n"
                        . "We received a request to reset the password for your parish reservations account. "
                        . "Use the link below to choose a new password. This link will expire in one hour.\n\n"
                        . $resetUrl . "\n\nIf you did not request a password reset, you can ignore this email.\n\n"
                        . 'St. John the Baptist Parish';

                    $mail->send();
                } catch (PHPMailerException $mailerException) {
                    throw new Exception('Unable to send password reset email: ' . $mailerException->getMessage(), 0, $mailerException);
                }
            }

            $successMessage = 'If an account matches that email address, we\'ve sent a password reset link.';
            $email = '';
        } catch (Throwable $exception) {
            $errorMessage = 'An unexpected error occurred while processing your request. Please try again.';
            error_log('Customer password reset request failed: ' . $exception->getMessage());
        } finally {
            if ($connection instanceof mysqli) {
                mysqli_close($connection);
            }
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Forgot Password | St. John the Baptist Parish</title>
    <meta name="description" content="Reset your St. John the Baptist Parish customer password to regain access to reservations.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

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
    <link rel="stylesheet" href="css/auth.css">
</head>

<body class="auth-body">
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">
                        <div class="col-xl-5 col-lg-6">
                            <div class="main-menu  d-none d-lg-block">
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
                        <div class="col-xl-2 col-lg-2">
                            <div class="logo-img">
                                <a href="index.php">
                                    <img src="img/logo.png" alt="St. John the Baptist Parish">
                                </a>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-4 d-none d-lg-block">
                            <div class="book_room">
                                <div class="socail_links">
                                    <ul>
                                        <li>
                                            <a href="https://www.facebook.com/stjohnthebaptistparish_tiaong" target="_blank" rel="noopener" aria-label="Facebook">
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
                                <div class="book_btn d-none d-lg-block">
                                    <a class="boxed-btn3" href="customer_register.php">Create an account</a>
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

    <div class="bradcam_area breadcam_bg" style="background: #563e9e;">
        <h3 style="color: white;">Forgot Password</h3>
    </div>

    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-11">
                    <div class="auth-card">
                        <div class="row no-gutters">
                            <div class="col-md-5 auth-card__media">
                                <div class="auth-card__media-inner">
                                    <span class="auth-badge"><i class="fa fa-envelope-open" aria-hidden="true"></i> Reset access</span>
                                    <h3 style="text: white;">We'll help you get back in</h3>
                                    <p>Request a secure link to update your password and return to managing your sacramental reservations.</p>
                                    <ul class="auth-benefits">
                                        <li>Keep your reservation details safe</li>
                                        <li>Update access in just a few steps</li>
                                        <li>Receive instant confirmation via email</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="auth-card__content">
                                    <h3 class="text-center mb-3">Forgot your password?</h3>
                                    <p class="text-center mb-4">Enter the email associated with your account and we'll send a reset link within moments.</p>
                                    <?php if ($errorMessage !== ''): ?>
                                        <div class="alert alert-danger auth-alert" role="alert">
                                            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($successMessage !== ''): ?>
                                        <div class="alert alert-success auth-alert" role="alert">
                                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form class="auth-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" data-loading-form>
                                        <div class="form-group">
                                            <label for="email">Email address</label>
                                            <div class="input-with-icon">
                                                <i class="fa fa-envelope" aria-hidden="true"></i>
                                                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
                                            </div>
                                        </div>
                                        <button type="submit" class="auth-button" data-loading-button>
                                            <span>Send reset link</span>
                                            <span class="spinner-border spinner-border-sm align-middle ml-2 d-none" role="status" aria-hidden="true" data-loading-spinner></span>
                                        </button>
                                    </form>
                                    <div class="auth-help">
                                        <i class="fa fa-arrow-left" aria-hidden="true"></i>
                                        <span>Remembered your password? <a href="customer_login.php">Return to sign in</a>.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

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
    <script src="js/contact.js"></script>
    <script src="js/jquery.ajaxchimp.min.js"></script>
    <script src="js/jquery.form.js"></script>
    <script src="js/jquery.validate.min.js"></script>
    <script src="js/mail-script.js"></script>
    <script src="js/main.js"></script>
    <script src="js/form-loading.js"></script>
</body>

</html>
