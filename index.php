<?php
require_once __DIR__ . '/includes/db_connection.php';

/** @var mysqli|null $connection */
$connection = null;
$announcements = [];
$announcementsLoadError = false;
$defaultAnnouncementImages = [
    'img/offers/1.png',
    'img/offers/2.png',
    'img/offers/3.png',
];

/**
 * Format an announcement timestamp for the home page.
 */
function format_home_announcement_date(?string $createdAt): string
{
    if (empty($createdAt)) {
        return '';
    }

    try {
        $date = new DateTime($createdAt);

        return $date->format('F j, Y');
    } catch (Exception $exception) {
        return '';
    }
}

try {
    $connection = get_db_connection();

    $query = 'SELECT title, body, image_path, created_at FROM announcements WHERE show_on_home = 1 ORDER BY created_at DESC LIMIT 6';
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        // If the announcements table does not yet exist, gracefully fall back to an empty list.
        if (mysqli_errno($connection) !== 1146) {
            throw new Exception('Unable to load announcements: ' . mysqli_error($connection));
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $announcements[] = $row;
        }

        mysqli_free_result($result);
    }
} catch (Exception $exception) {
    $announcementsLoadError = true;
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>St. John the Baptist Parish | Tiaong, Quezon</title>
    <meta name="description"
        content="St. John the Baptist Parish in Tiaong, Quezon is a welcoming Catholic community offering worship, sacraments, and pastoral care.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- <link rel="manifest" href="site.webmanifest"> -->
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">
    <!-- Place favicon.ico in the root directory -->

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
    <link rel="stylesheet" href="css/home.css">
    <!-- <link rel="stylesheet" href="css/responsive.css"> -->
</head>

<body>
    <!--[if lte IE 9]>
            <p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="https://browsehappy.com/">upgrade your browser</a> to improve your experience and security.</p>
        <![endif]-->

    <!-- header-start -->
    <header>
        <div class="header-area ">
            <div id="sticky-header" class="main-header-area">
                <div class="container-fluid p-0">
                    <div class="row align-items-center no-gutters">
                        <div class="col-xl-5 col-lg-6">
                            <div class="main-menu  d-none d-lg-block">
                                <nav>
                                    <ul id="navigation">
                                        <li><a class="active" href="index.php">Home</a></li>
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
    <!-- header-end -->

    <!-- slider_area_start -->
    <div class="slider_area hero_parish">
        <div class="slider_active hero_slider owl-carousel">
            <div class="single_slider hero_slide d-flex align-items-center slider_bg_1">
                <div class="hero_overlay"></div>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-xl-10 col-lg-11">
                            <div class="slider_text text-center hero_text">
                                <span class="hero_kicker">A sacred place for every milestone</span>
                                <h1 style="color: white;">Welcome to St. John the Baptist Parish</h1>
                                <p>Serving the faithful of Tiaong with joyful worship, heartfelt sacraments, and a thriving
                                    community centered on Christ.</p>
                                <div class="hero_actions">
                                    <a class="boxed-btn3 hero_btn hero_btn--primary" href="reservation.php"><i
                                            class="fa fa-calendar-check-o"></i> Plan a Sacrament</a>
                                    <a class="boxed-btn3 hero_btn hero_btn--secondary" href="schedule.php"><i
                                            class="fa fa-clock-o"></i> View Worship Schedule</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="single_slider hero_slide d-flex align-items-center slider_bg_2">
                <div class="hero_overlay"></div>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-xl-10 col-lg-11">
                            <div class="slider_text text-center hero_text">
                                <span class="hero_kicker">Gather · Pray · Celebrate</span>
                                <h1 style="color: white;">Experience a joyful parish life</h1>
                                <p>Book a baptism, wedding, funeral, or blessing and find the support of a compassionate
                                    pastoral team ready to walk with you.</p>
                                <div class="hero_actions">
                                    <a class="boxed-btn3 hero_btn hero_btn--primary" href="contact.php"><i
                                            class="fa fa-envelope-open"></i> Connect with Us</a>
                                
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <section class="parish_info_strip">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="info_strip_item">
                        <span class="info_strip_icon"><i class="fa fa-map-marker"></i></span>
                        <div>
                            <p class="info_strip_label">Visit Us</p>
                            <p class="info_strip_value">San Agustin St., Poblacion 1, Tiaong, Quezon</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info_strip_item">
                        <span class="info_strip_icon"><i class="fa fa-bell-o"></i></span>
                        <div>
                            <p class="info_strip_label">Parish Office Hours</p>
                            <p class="info_strip_value">Monday – Saturday · 8:00 AM – 5:00 PM</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info_strip_item">
                        <span class="info_strip_icon"><i class="fa fa-commenting-o"></i></span>
                        <div>
                            <p class="info_strip_label">Need Assistance?</p>
                            <p class="info_strip_value">Call <a href="tel:+63425459244">(042) 545 9244</a> or message us on
                                Facebook</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- slider_area_end -->

    <!-- welcome_area_start -->
    <div class="about_area pt-120 pb-90">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6 col-lg-6">
                    <div class="about_thumb d-flex mb-30">
                        <div class="img_1">
                            <img src="img/about/about_1.png" alt="Parish exterior" class="img-fluid rounded shadow">
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="about_info">
                        <div class="section_title mb-20px">
                            <span>Our Parish Family</span>
                            <h3>Faithful, welcoming, and centered on Christ</h3>
                        </div>
                        <p>St. John the Baptist Parish stands at the heart of Tiaong, Quezon as a home for prayer,
                            celebration, and service. Generations of families have gathered here to receive the
                            sacraments, accompany one another in faith, and extend compassion to our wider community.
                            Whether you are planning a sacrament, searching for a spiritual home, or simply exploring
                            the Catholic faith, we are blessed to walk with you.</p>
                        <ul class="about_highlights">
                            <li><span class="about_highlight_icon"><i class="fa fa-check"></i></span>Warm, welcoming
                                liturgies and sacraments</li>
                            <li><span class="about_highlight_icon"><i class="fa fa-check"></i></span>Compassionate
                                pastoral care for families and individuals</li>
                            <li><span class="about_highlight_icon"><i class="fa fa-check"></i></span>Vibrant ministries
                                for service, formation, and outreach</li>
                        </ul>
                        <a href="about.php" class="line-button">Discover Our Story</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- welcome_area_end -->

    <!-- events_preview_start -->
    <div class="features_room pt-120 pb-120">
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="section_title text-center mb-70">
                        <span>Parish News &amp; Updates</span>
                        <h3>Announcements from our parish team</h3>
                        <p class="section_subtitle">Stay informed about upcoming liturgies, gatherings, and important
                            reminders for our faith community.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $index => $announcement): ?>
                        <?php
                        $imagePath = trim((string) ($announcement['image_path'] ?? ''));
                        $imageToDisplay = $imagePath !== ''
                            ? $imagePath
                            : $defaultAnnouncementImages[$index % count($defaultAnnouncementImages)];
                        $formattedDate = format_home_announcement_date($announcement['created_at'] ?? null);
                        $imageAlt = 'Announcement image for ' . ($announcement['title'] ?? 'parish announcement');
                        ?>
                        <div class="col-xl-4 col-md-6">
                            <article class="single_rooms schedule_card announcement_card">
                                <div class="announcement_media">
                                    <img src="<?php echo htmlspecialchars($imageToDisplay, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="announcement_badge"><i class="fa fa-bullhorn"></i> Announcement</span>
                                </div>
                                <div class="announcement_body">
                                    <?php if ($formattedDate !== ''): ?>
                                        <div class="announcement_meta">
                                            <span><i class="fa fa-calendar"></i> <?php echo htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars((string) ($announcement['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="announcement_excerpt"><?php echo nl2br(htmlspecialchars((string) ($announcement['body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-xl-8 col-lg-9 mx-auto">
                        <div class="announcement_empty">
                            <div class="announcement_empty_icon"><i class="fa fa-bullhorn"></i></div>
                            <h4>Announcements are coming soon</h4>
                            <p>Our team is preparing new updates about upcoming Masses and parish events. Please check
                                back shortly or view the worship schedule for the latest information.</p>
                            <div class="announcement_empty_actions">
                                <a class="boxed-btn3" href="schedule.php"><i class="fa fa-clock-o"></i> View worship schedule</a>
                                <a class="boxed-btn3 dark" href="contact.php"><i class="fa fa-envelope-open" ></i> Contact the parish office</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($announcementsLoadError): ?>
                <div class="row">
                    <div class="col-12">
                        <p class="announcement_error">We’re unable to display announcements right now. Please try again
                            later.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <section class="pillars_area section_padding">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-9">
                    <div class="section_title text-center mb-60" style="padding: 50px;">
                        <span>Parish pillars</span>
                        <h3>Rooted in faith, animated by service</h3>
                        <p class="section_subtitle">Every ministry, celebration, and outreach is grounded in these core
                            values that shape our community.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-sm-6">
                    <div class="pillar_card">
                        <div class="pillar_icon"><i class="fa fa-sun-o"></i></div>
                        <h4>Worship</h4>
                        <p>Celebrate the Eucharist reverently, with sacred music and prayerful participation.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pillar_card">
                        <div class="pillar_icon"><i class="fa fa-graduation-cap"></i></div>
                        <h4>Formation</h4>
                        <p>Nurture disciples through catechesis, Bible studies, and gatherings for all ages.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pillar_card">
                        <div class="pillar_icon"><i class="fa fa-heart"></i></div>
                        <h4>Service</h4>
                        <p>Reach out to families in need with compassionate programs and parish missions.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pillar_card">
                        <div class="pillar_icon"><i class="fa fa-comments"></i></div>
                        <h4>Community</h4>
                        <p>Gather for fellowship, support, and joyful celebrations throughout the liturgical year.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="ministries_area section_padding mt-5">
        <div class="container">
            <div class="row align-items-center mb-60">
                <div class="col-lg-6">
                    <div class="ministry_showcase">
                        <span class="ministry_badge">Ministry spotlight</span>
                        <h3 class="ministry_title">Where hearts are moved to serve</h3>
                        <p class="ministry_description">Discover ministries that bring the Gospel to life—from
                            liturgical music and youth formation to our social action teams. There is a place for your
                            gifts and your story.</p>
                        <ul class="ministry_list">
                            <li><i class="fa fa-microphone"></i> Choir rehearsals every Thursday at 7:00 PM</li>
                            <li><i class="fa fa-book"></i> Youth catechesis &amp; formation every Saturday</li>
                            <li><i class="fa fa-shopping-basket"></i> Weekly pantry preparations for outreach</li>
                        </ul>
                        <a href="services.php" class="boxed-btn3">Meet the ministries</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row ministry_cards">
                        <div class="col-sm-6">
                            <div class="ministry_card">
                                <img src="img/offers/1.png" alt="Family celebrating" class="img-fluid">
                                <div class="ministry_card_body">
                                    <span class="ministry_card_tag">Families</span>
                                    <h4>Family life apostolate</h4>
                                    <p>Retreats, counseling, and joyful gatherings to strengthen every household.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ministry_card">
                                <img src="img/offers/2.png" alt="Youth ministry" class="img-fluid">
                                <div class="ministry_card_body">
                                    <span class="ministry_card_tag">Youth</span>
                                    <h4>Young disciples</h4>
                                    <p>Dynamic camps, service projects, and prayer circles for teens and young adults.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="ministry_card">
                                <img src="img/offers/3.png" alt="Community outreach" class="img-fluid">
                                <div class="ministry_card_body">
                                    <span class="ministry_card_tag">Outreach</span>
                                    <h4>Caritas &amp; social action</h4>
                                    <p>Serve the wider Tiaong community through feeding programs and relief missions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row align-items-center parish_quote">
                <div class="col-lg-7">
                    <blockquote>
                        “Each sacrament we celebrate and every hand we hold reminds us that Christ is alive in the heart
                        of Tiaong. Come and encounter Him in our parish family.”
                        <cite>— Rev. Fr. Juanito M. Aguilar, Parish Priest</cite>
                    </blockquote>
                </div>
                <div class="col-lg-5">
                    <div class="parish_stats_card">
                        <h4>Parish at a glance</h4>
                        <ul>
                            <li><strong>1896</strong><span>Year founded</span></li>
                            <li><strong>12</strong><span>Regular Masses each week</span></li>
                            <li><strong>25+</strong><span>Active ministries &amp; volunteer groups</span></li>
                            <li><strong>1000+</strong><span>Families journeying together</span></li>
                        </ul>
                        <a href="contact.php" class="line-button">Plan your visit</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="cta_area section_padding">
        <div class="container">
            <div class="cta_wrapper">
                <span class="cta_badge">We're here for you</span>
                <h3 style="color: white;">Ready to celebrate a sacrament or need prayers?</h3>
                <p>Our parish team is ready to welcome you with open doors and joyful hearts. Reach out today and let
                    us journey with you.</p>
                <div class="cta_actions">
                    <a href="reservation.php" class="boxed-btn3 cta_btn"><i class="fa fa-calendar"></i> Book a
                        reservation</a>
                    <a href="contact.php" class="cta_link">Message the parish office <i class="fa fa-long-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>
    <!-- events_preview_end -->

    <!-- footer -->
    <?php include 'includes/footer.php'; ?>



    <!-- JS here -->
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

    <!--contact js-->
    <script src="js/contact.js"></script>
    <script src="js/jquery.ajaxchimp.min.js"></script>
    <script src="js/jquery.form.js"></script>
    <script src="js/jquery.validate.min.js"></script>
    <script src="js/mail-script.js"></script>

    <script src="js/main.js"></script>
</body>

</html>