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
                                        <li><a class="active" href="reservation.php">Reservations</a></li>
                                        <li><a href="schedule.php">Schedule</a></li>
                                        <li><a href="services.php">Services</a></li>
                                        <li><a href="gallery.php">Gallery</a></li>
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
                        <p>Please complete the form below with as much detail as possible. A member of our pastoral staff will follow up within two business days to confirm availability and discuss next steps.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="reservation_form_area pb-120">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="reservation_calendar mb-5">
                        <h4 class="mb-4">Availability Preview</h4>
                        <p class="mb-4">The calendar below shows the current status of select dates. Dates marked as <span class="badge badge-success">Available</span> are open for reservations. Dates marked as <span class="badge badge-danger">Booked</span> already have scheduled events.</p>
                        <div class="calendar_legend mb-3">
                            <span><span class="legend available"></span> Available</span>
                            <span><span class="legend pending"></span> Pending</span>
                            <span><span class="legend booked"></span> Booked</span>
                        </div>
                        <div class="availability_calendar" id="availability-calendar"></div>
                    </div>
                </div>
                <div class="col-12 col-xl-10 mx-auto">
                    <form id="reservation-form" class="reservation_form">
                        <h4 class="mb-4">Reservation Details</h4>
                        <div class="form-group">
                            <label for="reservation-name">Name of person reserving *</label>
                            <input type="text" id="reservation-name" class="form-control" placeholder="Full name" required>
                        </div>
                        <div class="form-group">
                            <label for="reservation-email">Email *</label>
                            <input type="email" id="reservation-email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="reservation-phone">Contact number *</label>
                            <input type="tel" id="reservation-phone" class="form-control" placeholder="(123) 456-7890" required>
                        </div>
                        <div class="form-group">
                            <label for="reservation-type">Type of event *</label>
                            <select id="reservation-type" class="form-control" required>
                                <option value="" disabled selected>Select an option</option>
                                <option>Wedding</option>
                                <option>Baptism</option>
                                <option>Funeral Mass</option>
                                <option>Confirmation</option>
                                <option>Quinceañera</option>
                                <option>Home or Business Blessing</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="reservation-date">Preferred date *</label>
                                <input type="text" id="reservation-date" class="form-control datepicker" placeholder="Select date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="reservation-time">Preferred time *</label>
                                <input type="time" id="reservation-time" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reservation-notes">Additional notes or requests</label>
                            <textarea id="reservation-notes" class="form-control" rows="4" placeholder="Tell us about your celebration"></textarea>
                        </div>
                        <button type="submit" class="boxed-btn3 w-100">Submit Reservation Request</button>
                        <div id="reservation-confirmation" class="alert alert-success mt-4 d-none" role="alert" tabindex="-1">
                            Thank you! Your reservation request has been received. We will contact you soon to confirm the details.
                        </div>
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
                                <p>Together we complete the required forms, schedule rehearsals, and plan the liturgy.</p>
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
                            <p>Saturday Vigil – 5:00 PM<br>Sunday – 8:00 AM, 10:00 AM, 6:00 PM<br>Weekdays – 12:10 PM</p>
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
