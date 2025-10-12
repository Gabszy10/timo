<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>About | St. John the Baptist Parish</title>
    <meta name="description" content="Learn about the history, mission, and pastoral team of St. John the Baptist Parish.">
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
    <style>
        :root {
            --about-primary: #3a7bd5;
            --about-secondary: #00d2ff;
            --about-dark: #102542;
            --about-light: #f5f9ff;
        }

        .about-hero {
            position: relative;
            padding: 140px 0 80px;
            color: #fff;
            background: linear-gradient(120deg, rgba(16, 37, 66, 0.85), rgba(58, 123, 213, 0.85)), url('img/banner/bradcam3.png') center/cover no-repeat;
            overflow: hidden;
        }

        .about-hero .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.35em;
            font-weight: 700;
            font-size: 13px;
            opacity: 0.85;
        }

        .about-hero .eyebrow::before,
        .about-hero .eyebrow::after {
            content: "";
            display: inline-block;
            width: 24px;
            height: 2px;
            background: rgba(255, 255, 255, 0.45);
        }

        .about-hero h1 {
            font-size: clamp(2.5rem, 5vw, 3.75rem);
            line-height: 1.1;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .about-hero p.lead {
            font-size: 1.1rem;
            max-width: 580px;
            opacity: 0.9;
        }

        .hero-accent-card {
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 45px rgba(10, 31, 68, 0.25);
        }

        .hero-accent-card blockquote {
            font-size: 1rem;
            line-height: 1.7;
            font-style: italic;
            color: rgba(255, 255, 255, 0.9);
        }

        .hero-stats {
            margin-top: 45px;
        }

        .about-hero-row {
            row-gap: 3rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 24px;
        }

        .stat-row {
            row-gap: 1.5rem;
        }

        .heritage-row {
            row-gap: 3rem;
        }

        .mission-row {
            row-gap: 2.5rem;
        }

        .team-row {
            row-gap: 2.5rem;
        }

        .stat-card {
            position: relative;
            padding: 26px 24px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            color: #fff;
            height: 100%;
            box-shadow: 0 16px 35px rgba(16, 37, 66, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 30px 45px rgba(16, 37, 66, 0.35);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
            margin-bottom: 8px;
        }

        .section-padding {
            padding: 110px 0;
        }

        .section-pretitle {
            color: var(--about-primary);
            text-transform: uppercase;
            letter-spacing: 0.32em;
            font-weight: 700;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .section-pretitle::before {
            content: "";
            width: 34px;
            height: 2px;
            background: linear-gradient(90deg, var(--about-secondary), rgba(16, 37, 66, 0.25));
        }

        .section-pretitle::after {
            content: "";
            width: 34px;
            height: 2px;
            background: linear-gradient(90deg, rgba(16, 37, 66, 0.25), var(--about-secondary));
        }

        .section-title-xl {
            font-weight: 800;
            font-size: clamp(2rem, 4vw, 3rem);
            color: var(--about-dark);
            margin-top: 20px;
        }

        .about-heritage .image-stack {
            position: relative;
        }

        .about-heritage .image-stack img {
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(16, 37, 66, 0.25);
        }

        .image-stack .stacked-img {
            position: absolute;
            bottom: -40px;
            left: 60px;
            width: 230px;
            border: 10px solid #fff;
        }

        .highlight-list li {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .highlight-list i {
            color: var(--about-primary);
            font-size: 1.1rem;
        }

        .quote-card {
            margin-top: 35px;
            padding: 30px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(58, 123, 213, 0.15), rgba(0, 210, 255, 0.2));
            border: 1px solid rgba(58, 123, 213, 0.25);
        }

        .about-timeline {
            position: relative;
            padding-left: 40px;
            margin: 40px 0 0;
            list-style: none;
        }

        .about-timeline::before {
            content: "";
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--about-secondary), rgba(16, 37, 66, 0.25));
        }

        .about-timeline li {
            position: relative;
            margin-bottom: 32px;
            padding-left: 30px;
        }

        .timeline-marker {
            position: absolute;
            left: -2px;
            top: 6px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid var(--about-primary);
            box-shadow: 0 0 0 6px rgba(58, 123, 213, 0.15);
        }

        .timeline-year {
            font-weight: 700;
            color: var(--about-primary);
            margin-bottom: 6px;
        }

        .mission-card {
            background: #fff;
            border-radius: 24px;
            padding: 36px;
            height: 100%;
            box-shadow: 0 25px 60px rgba(16, 37, 66, 0.08);
            border: 1px solid rgba(16, 37, 66, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .mission-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 65px rgba(16, 37, 66, 0.12);
        }

        .mission-card .icon-circle {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(58, 123, 213, 0.2), rgba(0, 210, 255, 0.35));
            color: var(--about-primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .pastoral-team .single_team_member {
            padding: 40px 30px;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 30px 60px rgba(16, 37, 66, 0.12);
            border: 1px solid rgba(16, 37, 66, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .pastoral-team .single_team_member:hover {
            transform: translateY(-10px);
            box-shadow: 0 38px 75px rgba(16, 37, 66, 0.18);
        }

        .pastoral-team .team_thumb img {
            width: 140px;
            height: 140px;
            object-fit: cover;
            box-shadow: 0 15px 30px rgba(16, 37, 66, 0.15);
        }

        .cta-panel {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 60px 50px;
            color: #fff;
            background: linear-gradient(135deg, var(--about-primary), var(--about-secondary));
            box-shadow: 0 35px 65px rgba(16, 37, 66, 0.2);
        }

        .cta-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.35), transparent 55%);
            opacity: 0.6;
        }

        .cta-panel > * {
            position: relative;
            z-index: 2;
        }

        .map_area {
            background: var(--about-light);
        }

        .contact_info_list li {
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        .contact_info_list i {
            color: var(--about-primary);
            font-size: 1.1rem;
        }

        @media (max-width: 991.98px) {
            .about-hero {
                padding: 110px 0 70px;
            }

            .hero-stats {
                margin-top: 30px;
            }

            .hero-actions {
                gap: 12px;
            }

            .image-stack .stacked-img {
                position: relative;
                bottom: -20px;
                left: 0;
                width: 70%;
                margin: 20px auto 0;
                display: block;
            }
        }

        @media (max-width: 575.98px) {
            .stat-card {
                padding: 20px 18px;
            }

            .mission-card {
                padding: 28px;
            }

            .cta-panel {
                padding: 40px 30px;
            }
        }
    </style>
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
                                        <li><a class="active" href="about.php">About</a></li>
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

    <section class="about-hero">
        <div class="container">
            <div class="row align-items-center about-hero-row">
                <div class="col-lg-7">
                    <span class="eyebrow">About St. John the Baptist Parish</span>
                    <h1>More than a century of steadfast faith and joyful service</h1>
                    <p class="lead">Our parish in Tiaong has journeyed with generations of families—celebrating sacraments, nurturing disciples, and extending Christ's compassion to every corner of the community.</p>
                    <div class="hero-actions">
                        <a class="boxed-btn3" href="schedule.php">View Mass Schedule</a>
                        <a class="boxed-btn3" href="reservation.php" style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.4);">Plan a Celebration</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-accent-card">
                        <blockquote>
                            “In every prayer, procession, and outreach effort, we recognize how the Holy Spirit continues to renew our parish family. Thank you for walking this mission with us.”
                        </blockquote>
                        <div class="d-flex align-items-center mt-4">
                            <div>
                                <h5 class="mb-0">Rev. Michael Carter</h5>
                                <small>Pastor</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hero-stats">
                <div class="row stat-row">
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <span class="stat-number">100+</span>
                            <small>Years serving Tiaong families</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <span class="stat-number">35</span>
                            <small>Active ministries and organizations</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <span class="stat-number">500+</span>
                            <small>Families gathered weekly in worship</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card text-center">
                            <span class="stat-number">24</span>
                            <small>Community outreach programs annually</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-heritage section-padding">
        <div class="container">
            <div class="row align-items-center heritage-row">
                <div class="col-lg-6">
                    <div class="image-stack text-center text-lg-start">
                        <img src="img/about/about_1.png" class="img-fluid" alt="Historic façade of St. John the Baptist Parish">
                        <img src="img/about/about_2.png" class="img-fluid stacked-img" alt="Interior of the parish church during Mass">
                    </div>
                </div>
                <div class="col-lg-6">
                    <span class="section-pretitle">Our Heritage</span>
                    <h2 class="section-title-xl">Faith that grew alongside our town</h2>
                    <p class="mt-3">From humble beginnings as a wooden chapel beside the fields to the vibrant parish we know today, St. John the Baptist Parish has lovingly walked with the people of Tiaong. Each decade brought new ministries, new families, and renewed dedication to the Gospel.</p>
                    <ul class="highlight-list mt-4">
                        <li><i class="fa fa-check-circle"></i><span>Guiding generations through the sacraments—from the joy of baptism to the comfort of memorial services.</span></li>
                        <li><i class="fa fa-check-circle"></i><span>Championing social outreach that supports farmers, students, and families in times of need.</span></li>
                        <li><i class="fa fa-check-circle"></i><span>Celebrating cultural traditions that keep our history, music, and devotions alive.</span></li>
                    </ul>
                    <div class="quote-card">
                        <h5 class="mb-2">“Built on faith, sustained by community.”</h5>
                        <p class="mb-0">Our parish continues to thrive because of volunteers, benefactors, and faithful parishioners who respond daily to God's call.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding" style="background: var(--about-light);">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="section-pretitle">Milestones</span>
                    <h2 class="section-title-xl">A timeline of grace-filled moments</h2>
                    <p class="mt-3">Every era has strengthened our parish's commitment to worship, education, and service. These highlights capture how the Holy Spirit has moved through our history.</p>
                </div>
            </div>
            <ul class="about-timeline">
                <li>
                    <span class="timeline-marker"></span>
                    <div class="timeline-year">1920s</div>
                    <p>The faithful of Tiaong gather to build the first chapel honoring St. John the Baptist, establishing a spiritual anchor for the town.</p>
                </li>
                <li>
                    <span class="timeline-marker"></span>
                    <div class="timeline-year">1954</div>
                    <p>The parish expands its worship space and launches catechetical teams, preparing hundreds of children for First Communion.</p>
                </li>
                <li>
                    <span class="timeline-marker"></span>
                    <div class="timeline-year">1987</div>
                    <p>Lay ministers and youth leaders open outreach missions to nearby barangays, offering medical aid and livelihood programs.</p>
                </li>
                <li>
                    <span class="timeline-marker"></span>
                    <div class="timeline-year">2005</div>
                    <p>A new parish pastoral center is dedicated, welcoming small faith communities, music ministries, and formation classes.</p>
                </li>
                <li>
                    <span class="timeline-marker"></span>
                    <div class="timeline-year">Today</div>
                    <p>With digital initiatives, stewardship drives, and sustainable projects, our parish joyfully prepares for the next century of mission.</p>
                </li>
            </ul>
        </div>
    </section>

    <section class="section-padding" style="padding-top: 80px;">
        <div class="container">
            <div class="row mission-row">
                <div class="col-lg-4 col-md-6">
                    <div class="mission-card h-100">
                        <div class="icon-circle"><i class="fa fa-heart"></i></div>
                        <h3>Our Mission</h3>
                        <p class="mt-3">We are a welcoming Catholic community that worships God through Word and Sacrament, forms disciples through prayer and education, and serves our neighbors through justice and charity.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="mission-card h-100">
                        <div class="icon-circle"><i class="fa fa-lightbulb-o"></i></div>
                        <h3>Our Vision</h3>
                        <p class="mt-3">Rooted in the Gospel, we strive to radiate Christ's love—nurturing families, empowering youth, and reaching out to the vulnerable with hope and compassion.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="mission-card h-100">
                        <div class="icon-circle"><i class="fa fa-leaf"></i></div>
                        <h3>Our Values</h3>
                        <p class="mt-3">Prayer, stewardship, and service guide every ministry we undertake, reminding us to care for creation and to lift one another up in Christ.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding pastoral-team" style="padding-top: 80px;">
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="section_title text-center mb-70">
                        <span class="section-pretitle d-inline-flex">Our Pastoral Team</span>
                        <h2 class="section-title-xl mt-3">Meet the ministers walking with you</h2>
                        <p class="mt-3">Our team accompanies parishioners through worship, formation, and outreach—ready to listen, guide, and pray with you.</p>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center team-row">
                <div class="col-lg-4 col-md-6">
                    <div class="single_team_member text-center h-100">
                        <div class="team_thumb mb-4 mx-auto">
                            <img src="img/blog/blog_1.png" alt="Portrait of Rev. Michael Carter" class="rounded-circle">
                        </div>
                        <h4 class="mb-1">Rev. Michael Carter</h4>
                        <span class="d-block mb-3">Pastor</span>
                        <p class="mb-0">Fr. Michael shepherds the parish community, presides at the sacraments, and leads pastoral care for families.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single_team_member text-center h-100">
                        <div class="team_thumb mb-4 mx-auto">
                            <img src="img/blog/blog_2.png" alt="Portrait of Sr. Angela Ruiz" class="rounded-circle">
                        </div>
                        <h4 class="mb-1">Sr. Angela Ruiz</h4>
                        <span class="d-block mb-3">Director of Faith Formation</span>
                        <p class="mb-0">Sister Angela accompanies catechists, youth ministers, and adult faith groups in deepening their love for Christ.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="single_team_member text-center h-100">
                        <div class="team_thumb mb-4 mx-auto">
                            <img src="img/blog/blog_3.png" alt="Portrait of Deacon Luis Mendoza" class="rounded-circle">
                        </div>
                        <h4 class="mb-1">Deacon Luis Mendoza</h4>
                        <span class="d-block mb-3">Outreach Coordinator</span>
                        <p class="mb-0">Deacon Luis organizes medical missions, livelihood programs, and parish relief operations across the barangays.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding" style="padding-top: 60px;">
        <div class="container">
            <div class="cta-panel text-center">
                <h2 class="section-title-xl text-white">Journey with us in faith</h2>
                <p class="mt-3 mb-4">Whether you're returning to the sacraments, seeking a parish family, or looking for ways to serve, there's a place for you at St. John the Baptist Parish.</p>
                <a class="boxed-btn3" href="contact.php" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.5);">Connect with our team</a>
            </div>
        </div>
    </section>

    <section class="map_area pb-120">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="section_title mb-30">
                        <span>Visit Us</span>
                        <h3>Our location</h3>
                    </div>
                    <p>St. John the Baptist Parish is located in Poblacion, Tiaong, Quezon—steps away from the
                        town plaza and municipal hall. Public transportation and tricycles regularly pass the
                        parish grounds, and secure parking is available beside the church.</p>
                    <ul class="list-unstyled contact_info_list">
                        <li><i class="fa fa-map-marker"></i> Maharlika Highway, Barangay Poblacion II, Tiaong, Quezon, Philippines</li>
                        <li><i class="fa fa-phone"></i> <a href="tel:+63425459244">(042) 545-9244</a></li>
                        <li><i class="fa fa-envelope"></i> <a href="mailto:stjohnbaptisttiaongparish@gmail.com">stjohnbaptisttiaongparish@gmail.com</a></li>
                        <li><i class="fa fa-clock-o"></i> Parish Office Hours: Tuesday – Sunday, 8:00 AM – 5:00 PM</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="mapouter">
                        <div class="gmap_canvas">
                            <iframe width="100%" height="350" id="gmap_canvas" src="https://maps.google.com/maps?q=St.%20John%20The%20Baptist%20Parish%20Tiaong%20Quezon&t=&z=15&ie=UTF8&iwloc=&output=embed" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" title="Map to St. John the Baptist Parish"></iframe>
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
</body>

</html>
