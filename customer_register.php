<?php
require_once __DIR__ . '/includes/customer_auth.php';

customer_session_start();

$existingCustomer = get_logged_in_customer();
if ($existingCustomer !== null) {
    header('Location: reservation.php');
    exit;
}

$errors = [];
$name = '';
$email = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($address === '') {
        $errors[] = 'Please provide your home address.';
    }

    if ($password === '') {
        $errors[] = 'Please create a password.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Passwords must be at least 8 characters long.';
    }

    if ($confirmPassword === '' || $password !== $confirmPassword) {
        $errors[] = 'Please confirm your password.';
    }

    if (empty($errors)) {
        try {
            $connection = get_db_connection();

            $checkQuery = 'SELECT id FROM customers WHERE email = ? LIMIT 1';
            $checkStatement = mysqli_prepare($connection, $checkQuery);
            if ($checkStatement === false) {
                throw new Exception('Unable to prepare customer lookup: ' . mysqli_error($connection));
            }

            mysqli_stmt_bind_param($checkStatement, 's', $email);

            if (!mysqli_stmt_execute($checkStatement)) {
                $executionError = mysqli_stmt_error($checkStatement);
                mysqli_stmt_close($checkStatement);
                mysqli_close($connection);
                throw new Exception('Unable to check for existing account: ' . $executionError);
            }

            $existingResult = mysqli_stmt_get_result($checkStatement);
            $emailTaken = $existingResult && mysqli_fetch_assoc($existingResult);
            mysqli_stmt_close($checkStatement);

            if ($emailTaken) {
                mysqli_close($connection);
                $errors[] = 'An account with that email already exists. Please log in instead.';
            } else {
                $insertQuery = 'INSERT INTO customers (name, email, address, password_hash) VALUES (?, ?, ?, ?)';
                $insertStatement = mysqli_prepare($connection, $insertQuery);

                if ($insertStatement === false) {
                    mysqli_close($connection);
                    throw new Exception('Unable to prepare customer registration: ' . mysqli_error($connection));
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($insertStatement, 'ssss', $name, $email, $address, $passwordHash);

                if (!mysqli_stmt_execute($insertStatement)) {
                    $executionError = mysqli_stmt_error($insertStatement);
                    mysqli_stmt_close($insertStatement);
                    mysqli_close($connection);
                    throw new Exception('Unable to register your account: ' . $executionError);
                }

                $customerId = mysqli_insert_id($connection);
                mysqli_stmt_close($insertStatement);
                mysqli_close($connection);

                log_in_customer([
                    'id' => $customerId,
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                ]);

                $_SESSION['customer_flash_notification'] = [
                    'icon' => 'success',
                    'title' => 'Registration successful',
                    'text' => 'Welcome! Your account has been created and you can now submit reservation requests.',
                ];

                header('Location: reservation.php');
                exit;
            }
        } catch (Throwable $exception) {
            $errors[] = 'An unexpected error occurred while creating your account. Please try again.';
            error_log('Customer registration failed: ' . $exception->getMessage());
        }
    }
}
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Create an Account | St. John the Baptist Parish</title>
    <meta name="description" content="Register for a St. John the Baptist Parish reservation account in Tiaong, Quezon.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.jpg">

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
                        <div class="col-xl-5 col-lg-6 d-none d-lg-block order-lg-2">
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
                        <div class="col-xl-2 col-lg-2 col-6 order-lg-1">
                            <div class="logo-img">
                                <a href="index.php">
                                    <img src="img/about/about_1.jpg" alt="St. John the Baptist Parish logo">
                                </a>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-4 d-none d-lg-block order-lg-3">
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
                                    <a class="boxed-btn3" href="customer_login.php">Back to login</a>
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

    <div class="bradcam_area breadcam_bg" style="background: #563e9e;">
        <h3 style="color: white;">Create an Account</h3>
    </div>

    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-11">
                    <div class="auth-card">
                        <div class="row no-gutters">
                            <div class="col-md-5 auth-card__media">
                                <div class="auth-card__media-inner">
                                    <span class="auth-badge"><i class="fa fa-star" aria-hidden="true"></i> Join the community</span>
                                    <h3 style="color: white;">Create your parish account</h3>
                                    <p>Register once to streamline every future reservation and stay informed about parish life.</p>
                                    <ul class="auth-benefits">
                                        <li>Manage baptism, wedding, and mass bookings</li>
                                        <li>Store your family information securely</li>
                                        <li>Receive reminders for upcoming celebrations</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="auth-card__content">
                                    <h3 class="text-center mb-3">Create an Account</h3>
                                    <p class="text-center mb-4">We’ll save your details securely so every reservation request takes only a moment.</p>
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger auth-alert" role="alert">
                                            <ul class="mb-0 pl-3 text-left">
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <form class="auth-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" data-loading-form>
                                        <div class="form-group">
                                            <label for="name">Full name</label>
                                            <div class="input-with-icon">
                                                <i class="fa fa-user" aria-hidden="true"></i>
                                                <input type="text" class="form-control" id="name" name="name" placeholder="Your full name"
                                                    required value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
                                            </div>
                                        </div>
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
                                            <label for="address">Home address</label>
                                            <div class="input-with-icon input-with-icon--textarea">
                                                <i class="fa fa-home" aria-hidden="true"></i>
                                                <textarea class="form-control" id="address" name="address" required
                                                    placeholder="Street, city, province"><?php echo htmlspecialchars($address, ENT_QUOTES); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="password">Password</label>
                                                <div class="input-with-icon">
                                                    <i class="fa fa-lock" aria-hidden="true"></i>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                </div>
                                                <small class="form-text text-muted">Must be at least 8 characters.</small>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="confirm_password">Confirm password</label>
                                                <div class="input-with-icon">
                                                    <i class="fa fa-check" aria-hidden="true"></i>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="auth-button" data-loading-button>
                                            <span>Create account</span>
                                            <span class="spinner-border spinner-border-sm align-middle ml-2 d-none" role="status" aria-hidden="true" data-loading-spinner></span>
                                        </button>
                                    </form>
                                    <div class="auth-help">
                                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                                        <span>By signing up you’ll receive updates about parish events and reservations.</span>
                                    </div>
                                    <p class="auth-footer-text">Already registered? <a href="customer_login.php">Log in to your account</a>.</p>
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
