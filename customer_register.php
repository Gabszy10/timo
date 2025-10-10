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
    <title>Create an Account | St. Helena Parish</title>
    <meta name="description" content="Register for a St. Helena Parish reservation account.">
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
</head>

<body>
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
                                    <a class="boxed-btn3" href="customer_login.php">Back to login</a>
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
        <h3>Create an Account</h3>
    </div>

    <section class="pt-120 pb-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="reservation_form_cta p-5">
                        <h3 class="mb-4 text-center">Register to make reservations online</h3>
                        <p class="text-center">Save your information once so that future reservation requests are quick and easy.</p>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 pl-3 text-left">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>">
                            <div class="form-group">
                                <label for="name">Full name</label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="Your full name"
                                    required value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="name@example.com" required
                                    value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
                            </div>
                            <div class="form-group">
                                <label for="address">Home address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required
                                    placeholder="Street, city, province"><?php echo htmlspecialchars($address, ENT_QUOTES); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="password">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="form-text text-muted">Must be at least 8 characters.</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="confirm_password">Confirm password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="boxed-btn3 w-100">Create account</button>
                        </form>
                        <p class="text-center mt-4 mb-0">Already have an account? <a href="customer_login.php">Log in here</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer_top">
            <div class="container">
                <div class="row">
                    <div class="col-xl-4 col-md-6 col-lg-4">
                        <div class="footer_widget">
                            <h3 class="footer_title">About St. Helena</h3>
                            <p>P. Burgos Street<br>Barangay 1, Batangas City<br>
                                Batangas 4200, Philippines<br>
                                <a href="tel:+11234567890">(123) 456-7890</a><br>
                                <a href="mailto:office@sthelenaparish.org">office@sthelenaparish.org</a>
                            </p>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 col-lg-4">
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
                    <div class="col-xl-4 col-md-6 col-lg-4">
                        <div class="footer_widget">
                            <h3 class="footer_title">Reservation Times</h3>
                            <ul class="list-unstyled mb-0">
                                <li><strong>Weddings:</strong> Monday – Saturday, 7:30 AM – 10:00 AM<br>
                                    <span class="small text-muted">Additional 3:00 PM – 5:00 PM slot opens when the morning schedule fills.</span>
                                </li>
                                <li class="mt-2"><strong>Baptisms:</strong> Saturday &amp; Sunday, 11:00 AM – 12:00 PM</li>
                                <li class="mt-2"><strong>Funerals:</strong> Sunday &amp; Monday at 1:00 PM or 2:00 PM<br>
                                    <span class="small text-muted">Tuesday – Saturday at 8:00 AM, 9:00 AM, or 10:00 AM.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="copy-right_text">
            <div class="container">
                <div class="footer_border"></div>
                <div class="row">
                    <div class="col-xl-12">
                        <p class="copy_right text-center">
                            &copy; <?php echo date('Y'); ?> St. Helena Parish. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

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
</body>

</html>
