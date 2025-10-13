<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_auth.php';

customer_session_start();

if (get_logged_in_customer() !== null) {
    header('Location: reservation.php');
    exit;
}

/**
 * Fetch a valid password reset request for the provided token hash.
 *
 * @param mysqli  $connection
 * @param string  $tokenHash
 * @return array<string, mixed>|null
 * @throws Exception
 */
function fetch_valid_password_reset(mysqli $connection, string $tokenHash): ?array
{
    $query = 'SELECT pr.id, pr.customer_id, c.email, c.name FROM customer_password_resets pr '
        . 'INNER JOIN customers c ON c.id = pr.customer_id WHERE pr.token_hash = ? AND pr.expires_at > NOW() LIMIT 1';

    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        throw new Exception('Unable to prepare password reset lookup statement: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($statement, 's', $tokenHash);

    if (!mysqli_stmt_execute($statement)) {
        $executionError = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new Exception('Unable to execute password reset lookup statement: ' . $executionError);
    }

    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);
    }

    mysqli_stmt_close($statement);

    return is_array($row) ? $row : null;
}

$errorMessage = '';
$successMessage = '';
$password = '';
$confirmPassword = '';
$canShowForm = true;
$token = trim((string) ($_GET['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($token === '') {
        $errorMessage = 'The password reset link is invalid or has expired. Please request a new one.';
        $canShowForm = false;
    } else {
        /** @var mysqli|null $connection */
        $connection = null;

        try {
            $connection = get_db_connection();

            if (!mysqli_query($connection, 'DELETE FROM customer_password_resets WHERE expires_at <= NOW()')) {
                throw new Exception('Unable to clean up expired password reset requests: ' . mysqli_error($connection));
            }

            $tokenHash = hash('sha256', $token);
            $resetRequest = fetch_valid_password_reset($connection, $tokenHash);

            if ($resetRequest === null) {
                $errorMessage = 'The password reset link is invalid or has expired. Please request a new one.';
                $canShowForm = false;
            }
        } catch (Throwable $exception) {
            $errorMessage = 'An unexpected error occurred while verifying your reset link. Please request a new one.';
            $canShowForm = false;
            error_log('Customer password reset verification failed: ' . $exception->getMessage());
        } finally {
            if ($connection instanceof mysqli) {
                mysqli_close($connection);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($token === '') {
        $errorMessage = 'The password reset link is invalid or has expired. Please request a new one.';
        $canShowForm = false;
    } elseif ($password === '') {
        $errorMessage = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'Your password must be at least 8 characters long.';
    } elseif ($confirmPassword === '' || $password !== $confirmPassword) {
        $errorMessage = 'Please confirm your new password.';
    }

    if ($errorMessage === '') {
        /** @var mysqli|null $connection */
        $connection = null;

        try {
            $connection = get_db_connection();

            if (!mysqli_query($connection, 'DELETE FROM customer_password_resets WHERE expires_at <= NOW()')) {
                throw new Exception('Unable to clean up expired password reset requests: ' . mysqli_error($connection));
            }

            $tokenHash = hash('sha256', $token);
            $resetRequest = fetch_valid_password_reset($connection, $tokenHash);

            if ($resetRequest === null) {
                $errorMessage = 'The password reset link is invalid or has expired. Please request a new one.';
                $canShowForm = false;
            } else {
                $customerId = (int) ($resetRequest['customer_id'] ?? 0);
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                if ($passwordHash === false) {
                    throw new Exception('Unable to hash the provided password.');
                }

                $updateStatement = mysqli_prepare($connection, 'UPDATE customers SET password_hash = ? WHERE id = ? LIMIT 1');

                if ($updateStatement === false) {
                    throw new Exception('Unable to prepare password update statement: ' . mysqli_error($connection));
                }

                mysqli_stmt_bind_param($updateStatement, 'si', $passwordHash, $customerId);

                if (!mysqli_stmt_execute($updateStatement)) {
                    $executionError = mysqli_stmt_error($updateStatement);
                    mysqli_stmt_close($updateStatement);
                    throw new Exception('Unable to execute password update statement: ' . $executionError);
                }

                mysqli_stmt_close($updateStatement);

                $deleteStatement = mysqli_prepare($connection, 'DELETE FROM customer_password_resets WHERE customer_id = ?');

                if ($deleteStatement === false) {
                    throw new Exception('Unable to prepare reset cleanup statement: ' . mysqli_error($connection));
                }

                mysqli_stmt_bind_param($deleteStatement, 'i', $customerId);

                if (!mysqli_stmt_execute($deleteStatement)) {
                    $executionError = mysqli_stmt_error($deleteStatement);
                    mysqli_stmt_close($deleteStatement);
                    throw new Exception('Unable to execute reset cleanup statement: ' . $executionError);
                }

                mysqli_stmt_close($deleteStatement);

                $successMessage = 'Your password has been updated. You can now log in.';
                $canShowForm = false;
                $token = '';
                $password = '';
                $confirmPassword = '';
            }
        } catch (Throwable $exception) {
            if ($successMessage === '') {
                $errorMessage = 'An unexpected error occurred while resetting your password. Please try again.';
            }

            error_log('Customer password reset processing failed: ' . $exception->getMessage());
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
    <title>Reset Password | St. John the Baptist Parish</title>
    <meta name="description" content="Choose a new password to access your St. John the Baptist Parish customer account.">
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
        <h3 style="color: white;">Reset Password</h3>
    </div>

    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-11">
                    <div class="auth-card">
                        <div class="row no-gutters">
                            <div class="col-md-5 auth-card__media">
                                <div class="auth-card__media-inner">
                                    <span class="auth-badge"><i class="fa fa-shield" aria-hidden="true"></i> Secure update</span>
                                    <h3 style="text: white;">Choose a strong new password</h3>
                                    <p>Protect your parish reservation account with a refreshed password and continue planning important celebrations.</p>
                                    <ul class="auth-benefits">
                                        <li>Safeguard your reservation history</li>
                                        <li>Keep family details protected</li>
                                        <li>Continue coordinating sacraments with ease</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="auth-card__content">
                                    <h3 class="text-center mb-3">Create a new password</h3>
                                    <p class="text-center mb-4">Enter and confirm your new password below to secure your account.</p>
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
                                    <?php if ($canShowForm): ?>
                                        <form class="auth-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" data-loading-form>
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
                                            <div class="form-group">
                                                <label for="password">New password</label>
                                                <div class="input-with-icon">
                                                    <i class="fa fa-lock" aria-hidden="true"></i>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="confirm_password">Confirm new password</label>
                                                <div class="input-with-icon">
                                                    <i class="fa fa-check" aria-hidden="true"></i>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                                            </div>
                                            <button type="submit" class="auth-button" data-loading-button>
                                                <span>Update password</span>
                                                <span class="spinner-border spinner-border-sm align-middle ml-2 d-none" role="status" aria-hidden="true" data-loading-spinner></span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="auth-help">
                                            <i class="fa fa-life-ring" aria-hidden="true"></i>
                                            <span>Need a new link? <a href="customer_forgot_password.php">Request another password reset email</a>.</span>
                                        </div>
                                    <?php endif; ?>
                                    <p class="auth-footer-text">Remembered your password? <a href="customer_login.php">Return to the login page</a>.</p>
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
