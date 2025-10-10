<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Schedule | St. Helena Parish</title>
    <meta name="description" content="View the parish schedule and upcoming sacramental celebrations at St. Helena Parish.">
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
    <link rel="stylesheet" href="css/schedule.css">
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
                                        <li><a class="active" href="schedule.php">Schedule</a></li>
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
                                    <a class="boxed-btn3" href="reservation.php">Reserve Now</a>
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
        <h3>Parish Schedule</h3>
    </div>

    <section class="schedule_intro pt-120 pb-60">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center">
                    <div class="section_title mb-40">
                        <span>Stay up-to-date</span>
                        <p>Review the sacramental schedule that matches our online reservation options. Filter by weddings, baptisms, or funerals to see when each celebration is typically available.</p>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="schedule_directions_card">
                        <span class="schedule_directions_label">How to review reservations</span>
                        <h4 class="mb-3">Check availability before you submit a request</h4>
                        <p class="mb-4">Follow the steps below to confirm when sacraments are normally offered and which specific dates already have confirmed reservations.</p>
                        <ul class="schedule_directions_list">
                            <li>Use the reservation type filters below to focus on weddings, baptisms, or funerals.</li>
                            <li>Select any date on the calendar to see existing bookings and the standard time windows for that day.</li>
                            <li>Once you have a preferred schedule, proceed to the reservation form to submit your complete requirements.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="schedule_filters pb-60">
        <div class="container">
            <div class="filter_buttons text-center">
                <button type="button" class="filter_btn active" data-filter="all" aria-pressed="true">
                    <span class="filter_icon"><i class="fa fa-calendar"></i></span>
                    <span>All Reservation Types</span>
                </button>
                <button type="button" class="filter_btn" data-filter="wedding" aria-pressed="false">
                    <span class="filter_icon"><i class="fa fa-heart"></i></span>
                    <span>Weddings</span>
                </button>
                <button type="button" class="filter_btn" data-filter="baptism" aria-pressed="false">
                    <span class="filter_icon"><i class="fa fa-tint"></i></span>
                    <span>Baptisms</span>
                </button>
                <button type="button" class="filter_btn" data-filter="funeral" aria-pressed="false">
                    <span class="filter_icon"><i class="fa fa-leaf"></i></span>
                    <span>Funerals</span>
                </button>
            </div>
        </div>
    </section>

    <section class="schedule_calendar pb-120">
        <div class="container">
            <div class="row align-items-start">
                <div class="col-lg-7 order-2 order-lg-1 mb-4 mb-lg-0">
                    <div class="calendar_board" data-calendar>
                        <div class="calendar_header">
                            <div>
                                <span class="calendar_header_label">Availability calendar</span>
                                <h3 class="calendar_month" data-calendar-month></h3>
                            </div>
                            <p class="calendar_hint">Select a date to view confirmed reservations and parish time windows.</p>
                        </div>
                        <div class="calendar_weekdays" aria-hidden="true">
                            <span>Sun</span>
                            <span>Mon</span>
                            <span>Tue</span>
                            <span>Wed</span>
                            <span>Thu</span>
                            <span>Fri</span>
                            <span>Sat</span>
                        </div>
                        <div class="calendar_grid" data-calendar-grid role="grid" aria-live="polite"></div>
                    </div>
                </div>
                <div class="col-lg-5 order-1 order-lg-2 mb-4 mb-lg-0">
                    <div class="calendar_details" data-calendar-details-panel>
                        <h4 class="calendar_selected_date" data-calendar-date>Select a date on the calendar</h4>
                        <p class="calendar_selected_hint" data-calendar-date-hint>We&rsquo;ll highlight any confirmed reservations and remind you of the standard time windows for that weekday.</p>
                        <div class="calendar_details_section" data-calendar-reservations>
                            <h5>Reservations on this date</h5>
                            <ul class="calendar_event_list" data-calendar-event-list>
                                <li class="calendar_event_list_empty">No reservations are recorded for this date yet.</li>
                            </ul>
                        </div>
                        <div class="calendar_details_section" data-calendar-availability>
                            <h5>Standard availability</h5>
                            <ul class="calendar_availability_list" data-calendar-availability-list>
                                <li class="calendar_event_list_empty">Select a day to see the typical time windows.</li>
                            </ul>
                        </div>
                        <div class="calendar_cta">
                            <p class="mb-3">Ready to submit your information? Continue to the reservation form to request an available schedule.</p>
                            <a class="boxed-btn3 calendar_btn" href="registrations.php">Go to reservation form</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="schedule_events pb-120">
        <div class="container">
            <div class="row" id="schedule-events">
                <div class="col-lg-4 col-md-6 mb-4 schedule_item" data-type="wedding">
                    <div class="schedule_card h-100">
                        <div class="schedule_card_header">
                            <span class="schedule_badge schedule_badge--wedding"><i class="fa fa-heart"></i> Wedding</span>
                            <span class="schedule_time"><i class="fa fa-clock-o"></i> 7:30 AM – 10:00 AM</span>
                        </div>
                        <div class="schedule_card_body">
                            <span class="event_date">Monday – Saturday</span>
                            <h4>Morning Wedding Reservations</h4>
                            <ul class="schedule_details">
                                <li>Primary window for nuptial Masses and church ceremonies.</li>
                                <li>Submit complete wedding requirements through the reservation form.</li>
                                <li>Coordinate rehearsal details with the parish office after confirmation.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 schedule_item" data-type="wedding">
                    <div class="schedule_card h-100">
                        <div class="schedule_card_header">
                            <span class="schedule_badge schedule_badge--wedding"><i class="fa fa-heart"></i> Wedding</span>
                            <span class="schedule_time"><i class="fa fa-clock-o"></i> 3:00 PM – 5:00 PM</span>
                        </div>
                        <div class="schedule_card_body">
                            <span class="event_date">Monday – Saturday</span>
                            <h4>Overflow Afternoon Weddings</h4>
                            <ul class="schedule_details">
                                <li>Opens when morning schedules are filled to accommodate additional couples.</li>
                                <li>Ideal for celebrations needing later preparations or travel time.</li>
                                <li>Confirm availability with the parish office during your reservation.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 schedule_item" data-type="baptism">
                    <div class="schedule_card h-100">
                        <div class="schedule_card_header">
                            <span class="schedule_badge schedule_badge--baptism"><i class="fa fa-tint"></i> Baptism</span>
                            <span class="schedule_time"><i class="fa fa-clock-o"></i> 11:00 AM – 12:00 PM</span>
                        </div>
                        <div class="schedule_card_body">
                            <span class="event_date">Saturday &amp; Sunday</span>
                            <h4>Weekend Baptism Celebrations</h4>
                            <ul class="schedule_details">
                                <li>Group baptisms take place after the late morning Mass.</li>
                                <li>Parents and sponsors should review required documents in advance.</li>
                                <li>Please arrive early for check-in and catechesis before the rite.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 schedule_item" data-type="funeral">
                    <div class="schedule_card h-100">
                        <div class="schedule_card_header">
                            <span class="schedule_badge schedule_badge--funeral"><i class="fa fa-leaf"></i> Funeral</span>
                            <span class="schedule_time"><i class="fa fa-clock-o"></i> 1:00 PM &amp; 2:00 PM</span>
                        </div>
                        <div class="schedule_card_body">
                            <span class="event_date">Sunday &amp; Monday</span>
                            <h4>Afternoon Funeral Masses</h4>
                            <ul class="schedule_details">
                                <li>Select a 1:00 PM or 2:00 PM liturgy when coordinating with the parish office.</li>
                                <li>Ideal for families expecting out-of-town arrivals on the weekend.</li>
                                <li>Finalize the reservation at least one day before burial to avoid delays.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 schedule_item" data-type="funeral">
                    <div class="schedule_card h-100">
                        <div class="schedule_card_header">
                            <span class="schedule_badge schedule_badge--funeral"><i class="fa fa-leaf"></i> Funeral</span>
                            <span class="schedule_time"><i class="fa fa-clock-o"></i> 8:00 AM · 9:00 AM · 10:00 AM</span>
                        </div>
                        <div class="schedule_card_body">
                            <span class="event_date">Tuesday – Saturday</span>
                            <h4>Morning Funeral Liturgies</h4>
                            <ul class="schedule_details">
                                <li>Three morning slots are offered for weekday funeral Masses.</li>
                                <li>Choose the time that best aligns with cemetery or memorial plans.</li>
                                <li>Share any procession details with the office when submitting documents.</li>
                            </ul>
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
    <script src="js/schedule.js"></script>
</body>

</html>
