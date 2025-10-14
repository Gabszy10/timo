<?php require_once __DIR__ . '/includes/site_meta.php'; ?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
<?php
    render_site_meta([
        'title' => 'Inquire | St. John the Baptist Parish',
        'description' => 'Reach out to St. John the Baptist Parish for reservations, sacramental requests, and pastoral care support in Tiaong, Quezon.',
        'image' => '/img/banner/bradcam2.png',
    ]);
?>
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
    <style>
        .contact-section {
            position: relative;
            background: linear-gradient(135deg, rgba(19, 52, 119, 0.05), rgba(0, 123, 255, 0.08));
        }

        .contact-section::before {
            content: "";
            position: absolute;
            top: -120px;
            right: -80px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0));
            z-index: 0;
        }

        .contact-section::after {
            content: "";
            position: absolute;
            bottom: -120px;
            left: -80px;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle at center, rgba(46, 105, 255, 0.15), rgba(46, 105, 255, 0));
            z-index: 0;
        }

        .contact-section .container {
            position: relative;
            z-index: 2;
        }

        .contact-intro {
            max-width: 760px;
            margin: 0 auto 3rem;
        }

        .contact-intro .section-subtitle {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #2e69ff;
            font-weight: 600;
        }

        .contact-intro h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .contact-wrapper {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(10, 31, 68, 0.12);
            overflow: hidden;
        }

        .contact-form-panel,
        .contact-details-panel {
            padding: 3rem;
        }

        .contact-details-panel {
            background: linear-gradient(160deg, #163a9f 0%, #009ddc 100%);
            color: #ffffff;
        }

        .contact-details-panel h3,
        .contact-details-panel p,
        .contact-details-panel li,
        .contact-details-panel a {
            color: #ffffff;
        }

        .contact-details-panel a {
            text-decoration: underline;
        }

        .contact-details-panel .contact-card {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(6px);
            box-shadow: 0 16px 32px rgba(5, 22, 54, 0.2);
            margin-bottom: 1.5rem;
        }

        .contact-details-panel .contact-card:last-child {
            margin-bottom: 0;
        }

        .contact-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }

        .contact_form label {
            font-weight: 600;
            color: #2a2a2a;
        }

        .contact_form .form-control {
            border-radius: 14px;
            border: 1px solid rgba(19, 52, 119, 0.18);
            padding: 0.85rem 1.1rem;
            font-size: 1rem;
            box-shadow: none;
            transition: all 0.2s ease;
        }

        .contact_form .form-control:focus {
            border-color: #2e69ff;
            box-shadow: 0 0 0 0.2rem rgba(46, 105, 255, 0.12);
        }

        .boxed-btn3 {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 0.9rem 2.4rem;
            border-radius: 30px;
            background: linear-gradient(135deg, #2e69ff 0%, #47c2ff 100%);
            border: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .boxed-btn3 i {
            font-size: 1.2rem;
        }

        .boxed-btn3:hover,
        .boxed-btn3:focus {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(46, 105, 255, 0.35);
            color: #ffffff;
        }

        .contact-highlight-grid {
            margin-top: 3.5rem;
        }

        .highlight-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(10, 31, 68, 0.08);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .highlight-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 32px 80px rgba(10, 31, 68, 0.16);
        }

        .highlight-card h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #133477;
        }

        .highlight-card p {
            color: #4f5d75;
            margin-bottom: 0;
        }

        .highlight-card a {
            font-weight: 600;
            color: #2e69ff;
        }

        .highlight-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(46, 105, 255, 0.12);
            color: #2e69ff;
            font-size: 1.3rem;
            margin-bottom: 1.2rem;
        }

        @media (max-width: 991.98px) {
            .contact-form-panel,
            .contact-details-panel {
                padding: 2.2rem;
            }

            .contact-details-panel {
                border-radius: 0 0 24px 24px;
            }
        }

        @media (max-width: 767.98px) {
            .contact-intro h2 {
                font-size: 2.1rem;
            }

            .contact-form-panel,
            .contact-details-panel {
                padding: 2rem 1.6rem;
            }

            .highlight-card {
                padding: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area" style="background-color: black;">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">
                        <div class="col-xl-5 col-lg-6 d-none d-lg-block order-lg-1">
                            <div class="main-menu d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                        <li><a href="index.php">Home</a></li>
                                        <li><a href="about.php">About</a></li>
                                        <li><a href="schedule.php">Schedule</a></li>
                                        <li><a class="active" href="contact.php">Inquire</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <div class="col-xl-2 col-lg-2 col-6 order-lg-2 d-flex align-items-center justify-content-center">
                            <div class="logo-img">
                                <a href="index.php">
                                    <img src="img/about/about_1.jpg" height="60" alt="St. John the Baptist Parish logo"
                                        style="border-radius: 50px;">
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
                                    <a class="boxed-btn3" href="reservation.php">Reserve Now</a>
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

    <section class="contact-section pt-120 pb-120">
        <div class="container">
            <div class="text-center contact-intro">
                <br>
                <span class="section-subtitle"><i class="fa fa-paper-plane"></i> Connect With the Parish</span>
                <h2>We are here to listen, guide, and pray with you</h2>
                <p class="mt-3">Share your intentions, plan a celebration, or simply say hello. Our parish team in Tiaong is ready to accompany you with warmth and compassion.</p>
            </div>
            <div class="contact-wrapper">
                <div class="row g-0">
                    <div class="col-lg-6 contact-form-panel">
                        <h3 class="mb-4">Send us a message</h3>
                        <p class="mb-4">Let us know how we can support you. Messages go directly to our parish staff who respond within one business day.</p>
                        <form class="contact_form" id="contact-form" action="contact_process.php" method="post" novalidate>
                            <div class="form-group">
                                <label for="contact-name">Name *</label>
                                <input class="form-control" type="text" id="contact-name" name="name" placeholder="Full name" required>
                            </div>
                            <div class="form-group">
                                <label for="contact-email">Email *</label>
                                <input class="form-control" type="email" id="contact-email" name="email" placeholder="name@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="contact-phone">Phone</label>
                                <input class="form-control" type="tel" id="contact-phone" name="phone" placeholder="(042) 545-9244">
                            </div>
                            <div class="form-group">
                                <label for="contact-message">Message *</label>
                                <textarea class="form-control" id="contact-message" name="message" rows="5" placeholder="How can we help you?" required></textarea>
                            </div>
                            <button type="submit" class="boxed-btn3"><i class="fa fa-send"></i> Send Message</button>
                            <div id="contact-success" class="alert alert-success mt-4 d-none" role="alert" tabindex="-1">
                                Thank you for reaching out! Our parish staff will respond as soon as possible.
                            </div>
                            <div id="contact-error" class="alert alert-danger mt-4 d-none" role="alert" tabindex="-1">
                                We could not send your message. Please try again later or contact us by phone.
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-6 contact-details-panel">
                        <h3 class="mb-4">Visit the parish office</h3>
                        <p class="mb-4">Find us along Maharlika Highway near the Tiaong town plaza. Tricycles and jeepneys pass the church regularly, and parking is available beside the parish hall.</p>
                        <div class="contact-card">
                            <div class="contact-icon-circle" aria-hidden="true"><i class="fa fa-map-marker"></i></div>
                            <h5 class="mb-2" style="color: white;">Our Address</h5>
                            <p class="mb-0">Maharlika Highway, Barangay Poblacion II,<br>Tiaong, Quezon, Philippines</p>
                        </div>
                        <div class="contact-card">
                            <div class="contact-icon-circle" aria-hidden="true"><i class="fa fa-phone"></i></div>
                            <h5 class="mb-2" style="color: white;">Call or Message</h5>
                            <p class="mb-0"><a href="tel:+63425459244">(042) 545-9244</a><br><a href="mailto:stjohnbaptisttiaongparish@gmail.com">stjohnbaptisttiaongparish@gmail.com</a></p>
                        </div>
                        <div class="contact-card">
                            <div class="contact-icon-circle" aria-hidden="true"><i class="fa fa-clock-o"></i></div>
                            <h5 class="mb-2" style="color: white;">Office Hours</h5>
                            <p class="mb-0">Tuesday – Sunday<br>8:00 AM – 5:00 PM (or by appointment)</p>
                        </div>
                        <div class="mt-4">
                            <div class="mapouter">
                                <div class="gmap_canvas">
                                    <iframe width="100%" height="260" src="https://maps.google.com/maps?q=St.%20John%20The%20Baptist%20Parish%20Tiaong%20Quezon&t=&z=15&ie=UTF8&iwloc=&output=embed" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" title="Map to St. John the Baptist Parish"></iframe>
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
    <script src="js/main.js"></script>
    <script src="js/contact-form.js"></script>
</body>

</html>
