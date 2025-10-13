<?php
require_once __DIR__ . '/includes/customer_auth.php';

customer_session_start();

$customer = get_logged_in_customer();
if ($customer !== null) {
    header('Location: reservation.php');
    exit;
}

$errorMessage = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $errorMessage = 'Please enter your password.';
    } else {
        try {
            $connection = get_db_connection();
            $query = 'SELECT id, name, email, address, password_hash FROM customers WHERE email = ? LIMIT 1';
            $statement = mysqli_prepare($connection, $query);

            if ($statement === false) {
                throw new Exception('Unable to prepare login query: ' . mysqli_error($connection));
            }

            mysqli_stmt_bind_param($statement, 's', $email);

            if (!mysqli_stmt_execute($statement)) {
                $executionError = mysqli_stmt_error($statement);
                mysqli_stmt_close($statement);
                mysqli_close($connection);
                throw new Exception('Unable to execute login query: ' . $executionError);
            }

            $result = mysqli_stmt_get_result($statement);
            $customerRow = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($statement);
            mysqli_close($connection);

            if (!$customerRow || !password_verify($password, (string) ($customerRow['password_hash'] ?? ''))) {
                $errorMessage = 'Invalid email or password. Please try again.';
            } else {
                log_in_customer($customerRow);
                header('Location: reservation.php');
                exit;
            }
        } catch (Throwable $exception) {
            $errorMessage = 'An unexpected error occurred while attempting to log in. Please try again.';
            error_log('Customer login failed: ' . $exception->getMessage());
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Customer Login | St. John the Baptist Parish</title>
    <meta name="description" content="Access your St. John the Baptist Parish reservation account for Tiaong, Quezon.">
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
                                            <a href="https://www.facebook.com/officialstjohnthebaptistparishtiaong" target="_blank" rel="noopener" aria-label="Facebook">
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
        <h3 style="color: white;">Customer Login</h3>
    </div>

    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-11">
                    <div class="auth-card">
                        <div class="row no-gutters">
                            <div class="col-md-5 auth-card__media">
                                <div class="auth-card__media-inner">
                                    <span class="auth-badge"><i class="fa fa-unlock-alt" aria-hidden="true"></i> Welcome back</span>
                                    <h3 style="color: white;">Effortless reservations</h3>
                                    <p>Sign in to manage your sacramental reservations with ease and stay connected with the parish.</p>
                                    <ul class="auth-benefits">
                                        <li>Track upcoming ceremonies and commitments</li>
                                        <li>Update family information in moments</li>
                                        <li>Receive confirmations straight to your inbox</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="auth-card__content">
                                    <h3 class="text-center mb-3">Customer Login</h3>
                                    <p class="text-center mb-4">Access your reservation requests and saved details anytime, anywhere.</p>
                                    <?php if ($errorMessage !== ''): ?>
                                        <div class="alert alert-danger auth-alert" role="alert">
                                            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form class="auth-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" data-loading-form>
                                        <div class="form-group">
                                            <label for="email">Email address</label>
                                            <div class="input-with-icon">
                                                <i class="fa fa-envelope" aria-hidden="true"></i>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    placeholder="name@example.com" required
                                                    value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Password</label>
                                            <div class="input-with-icon">
                                                <i class="fa fa-lock" aria-hidden="true"></i>
                                                <input type="password" class="form-control" id="password" name="password" required>
                                            </div>
                                        </div>
                                        <div class="auth-forgot-link">
                                            <a href="customer_forgot_password.php">Forgot your password?</a>
                                        </div>
                                        <button type="submit" class="auth-button" data-loading-button>
                                            <span>Log in</span>
                                            <span class="spinner-border spinner-border-sm align-middle ml-2 d-none" role="status" aria-hidden="true" data-loading-spinner></span>
                                        </button>
                                    </form>
                                    <div class="auth-help">
                                        <i class="fa fa-life-ring" aria-hidden="true"></i>
                                        <span>Need assistance? <a href="contact.php">Reach out to our parish team</a>.</span>
                                    </div>
                                    <p class="auth-footer-text">Don't have an account yet? <a href="customer_register.php">Create one in a minute</a>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
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
