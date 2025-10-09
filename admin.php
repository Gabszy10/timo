<?php
session_start();

require_once __DIR__ . '/includes/db_connection.php';

const ADMIN_LOGIN_ACTION = 'login';
const ADMIN_STATUS_UPDATE_ACTION = 'update_status';

/**
 * Fetch all reservations ordered by creation date.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_reservations(): array
{
    $connection = get_db_connection();

    $query = 'SELECT id, name, email, phone, event_type, preferred_date, preferred_time, status, notes, created_at FROM reservations ORDER BY created_at DESC';
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        $error = 'Unable to fetch reservations: ' . mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $reservations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reservations[] = $row;
    }

    mysqli_free_result($result);
    mysqli_close($connection);

    return $reservations;
}

/**
 * Update the reservation status for the provided id.
 */
function update_reservation_status(int $reservationId, string $status): void
{
    $allowedStatuses = ['pending', 'approved', 'declined'];
    if (!in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Invalid reservation status provided.');
    }

    if ($reservationId <= 0) {
        throw new InvalidArgumentException('Invalid reservation selected.');
    }

    $connection = get_db_connection();

    $query = 'UPDATE reservations SET status = ? WHERE id = ?';
    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare reservation update: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($statement, 'si', $status, $reservationId);

    if (!mysqli_stmt_execute($statement)) {
        $error = 'Unable to update reservation status: ' . mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);
}

$loginError = '';
$submittedUsername = '';

function admin_credentials_are_valid(string $username, string $password): bool
{
    return $username === 'admin' && $password === 'admin';
}

// logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

$flashSuccess = $_SESSION['admin_flash_success'] ?? '';
$flashError = $_SESSION['admin_flash_error'] ?? '';
unset($_SESSION['admin_flash_success'], $_SESSION['admin_flash_error']);

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === ADMIN_LOGIN_ACTION) {
        $submittedUsername = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (admin_credentials_are_valid($submittedUsername, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $submittedUsername;
            header('Location: admin.php');
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }

    if ($action === ADMIN_STATUS_UPDATE_ACTION) {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            $_SESSION['admin_flash_error'] = 'You must be logged in to perform this action.';
            header('Location: admin.php');
            exit;
        }

        $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';

        try {
            if ($reservationId === false || $reservationId === null) {
                throw new InvalidArgumentException('Invalid reservation selected.');
            }

            update_reservation_status($reservationId, $status);
            $_SESSION['admin_flash_success'] = 'Reservation status updated successfully.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash_error'] = $exception->getMessage();
        }

        header('Location: admin.php');
        exit;
    }
}

$isLoggedIn = ($_SESSION['admin_logged_in'] ?? false) === true;
$reservations = $isLoggedIn ? fetch_reservations() : [];

/**
 * Render a bootstrap styled badge for the reservation status.
 */
function render_status_badge(string $status): string
{
    $badgeClasses = [
        'pending' => 'badge badge-warning',
        'approved' => 'badge badge-success',
        'declined' => 'badge badge-danger',
    ];

    $normalizedStatus = strtolower($status);
    $class = $badgeClasses[$normalizedStatus] ?? 'badge badge-secondary';
    $label = ucfirst($normalizedStatus);

    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Group reservations by status to make it easier to render the dashboard columns.
 *
 * @param array<int, array<string, mixed>> $reservations
 * @return array<string, array<int, array<string, mixed>>>
 */
function group_reservations_by_status(array $reservations): array
{
    $groups = [
        'pending' => [],
        'approved' => [],
        'declined' => [],
    ];

    foreach ($reservations as $reservation) {
        $status = strtolower((string) ($reservation['status'] ?? 'pending'));
        if (!isset($groups[$status])) {
            $groups[$status] = [];
        }
        $groups[$status][] = $reservation;
    }

    return $groups;
}

/**
 * Present a human readable date string when possible.
 */
function format_reservation_date(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '—';
    }

    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('M j, Y');
    } catch (Exception $exception) {
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Present a human readable time string when possible.
 */
function format_reservation_time(?string $time): string
{
    if ($time === null) {
        return '—';
    }

    $trimmed = trim($time);
    if ($trimmed === '') {
        return '—';
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        return date('g:i A', $timestamp);
    }

    return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}

/**
 * Format the created at timestamp for display.
 */
function format_reservation_created_at(?string $createdAt): string
{
    if ($createdAt === null || trim($createdAt) === '') {
        return '—';
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp !== false) {
        return date('M j, Y g:i A', $timestamp);
    }

    return htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | St. Helena Parish</title>
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fc;
        }

        .admin-wrapper {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .logout-link {
            color: #dc3545;
        }

        .login-card {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .dashboard-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .dashboard-meta .meta-card {
            flex: 1 1 200px;
            background: #f1f4ff;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .dashboard-meta .meta-card h2 {
            margin: 0 0 4px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6c63ff;
        }

        .dashboard-meta .meta-card span {
            font-size: 24px;
            font-weight: 600;
            color: #2d2a44;
        }

        .status-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .status-column {
            position: relative;
            padding: 22px 22px 28px;
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff 0%, #f6f8ff 100%);
            box-shadow: 0 18px 35px rgba(94, 86, 232, 0.1);
            overflow: hidden;
            min-height: 260px;
        }

        .status-column::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0.08;
            pointer-events: none;
        }

        .status-column h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .status-column p {
            margin-bottom: 18px;
            color: #6b6f82;
            font-size: 14px;
        }

        .status-column .empty-state {
            color: #9aa0b9;
            font-style: italic;
        }

        .status-column-pending::before {
            background: linear-gradient(145deg, #ffc107, #ff8a00);
        }

        .status-column-approved::before {
            background: linear-gradient(145deg, #28a745, #13d8a7);
        }

        .status-column-declined::before {
            background: linear-gradient(145deg, #dc3545, #ff6b6b);
        }

        .reservation-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px rgba(82, 95, 225, 0.08);
            border: 1px solid rgba(108, 99, 255, 0.08);
        }

        .reservation-card:last-child {
            margin-bottom: 0;
        }

        .reservation-card h3 {
            font-size: 18px;
            margin-bottom: 6px;
            color: #2d2a44;
        }

        .reservation-card .reservation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #5b5e72;
        }

        .reservation-card .reservation-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reservation-card .reservation-meta i {
            color: #6c63ff;
        }

        .reservation-card .reservation-meta a {
            color: #2d2a44;
            font-weight: 600;
        }

        .reservation-card .reservation-notes {
            font-size: 14px;
            line-height: 1.5;
            color: #43455c;
            margin-bottom: 14px;
            white-space: pre-wrap;
        }

        .reservation-card .status-badge {
            margin-bottom: 12px;
        }

        .status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .status-actions form {
            display: inline-flex;
        }

        .status-actions .btn {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
        }

        @media (max-width: 767px) {
            .admin-wrapper {
                margin: 20px;
                padding: 24px;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>

<body>

    <?php if (!$isLoggedIn): ?>
        <!-- LOGIN VIEW (unchanged styling) -->
        <div class="login-card">
            <h3 class="mb-3">Admin Sign In</h3>

            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($loginError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="<?php echo ADMIN_LOGIN_ACTION; ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                        value="<?php echo htmlspecialchars($submittedUsername, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
        </div>

    <?php else: ?>
        <!-- DASHBOARD VIEW (your original sections kept) -->
        <div class="admin-wrapper">
            <div class="admin-header">
                <h1>Reservations Dashboard</h1>
                <a class="logout-link" href="admin.php?logout=1">Logout</a>
            </div>

            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (count($reservations) === 0): ?>
                <p class="text-muted mb-0">No reservations have been submitted yet.</p>
            <?php else: ?>
                <?php
                $groupedReservations = group_reservations_by_status($reservations);
                $totals = [
                    'total' => count($reservations),
                    'pending' => count($groupedReservations['pending']),
                    'approved' => count($groupedReservations['approved']),
                    'declined' => count($groupedReservations['declined']),
                ];

                $statusMeta = [
                    'pending' => [
                        'title' => 'Pending Review',
                        'subtitle' => 'Reservations awaiting your decision.',
                        'class' => 'status-column-pending',
                        'empty' => 'No pending reservations at the moment.',
                    ],
                    'approved' => [
                        'title' => 'Approved',
                        'subtitle' => 'Confirmed reservations ready to proceed.',
                        'class' => 'status-column-approved',
                        'empty' => 'No reservations have been approved yet.',
                    ],
                    'declined' => [
                        'title' => 'Declined',
                        'subtitle' => 'Reservations that were not accepted.',
                        'class' => 'status-column-declined',
                        'empty' => 'No declined reservations.',
                    ],
                ];
                ?>
                <div class="dashboard-meta">
                    <div class="meta-card">
                        <h2>Total Requests</h2>
                        <span><?php echo htmlspecialchars((string) $totals['total'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="meta-card">
                        <h2>Pending</h2>
                        <span><?php echo htmlspecialchars((string) $totals['pending'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="meta-card">
                        <h2>Approved</h2>
                        <span><?php echo htmlspecialchars((string) $totals['approved'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="meta-card">
                        <h2>Declined</h2>
                        <span><?php echo htmlspecialchars((string) $totals['declined'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="status-columns">
                    <?php foreach ($statusMeta as $statusKey => $meta): ?>
                        <div class="status-column <?php echo htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                            <h2><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>

                            <?php if (count($groupedReservations[$statusKey]) === 0): ?>
                                <p class="empty-state"><?php echo htmlspecialchars($meta['empty'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php else: ?>
                                <?php foreach ($groupedReservations[$statusKey] as $reservation): ?>
                                    <div class="reservation-card">
                                        <div class="status-badge"><?php echo render_status_badge($reservation['status']); ?></div>
                                        <h3>#<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?> ·
                                            <?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <div class="reservation-meta">
                                            <span><i class="fa fa-envelope"></i><a
                                                    href="mailto:<?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                                            <span><i class="fa fa-phone"></i><a
                                                    href="tel:<?php echo htmlspecialchars($reservation['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservation['phone'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                                            <span><i
                                                    class="fa fa-calendar"></i><?php echo format_reservation_date($reservation['preferred_date'] ?? ''); ?></span>
                                            <span><i
                                                    class="fa fa-clock-o"></i><?php echo format_reservation_time($reservation['preferred_time'] ?? ''); ?></span>
                                            <span><i
                                                    class="fa fa-tag"></i><?php echo htmlspecialchars($reservation['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><i
                                                    class="fa fa-history"></i><?php echo format_reservation_created_at($reservation['created_at'] ?? ''); ?></span>
                                        </div>
                                        <?php if (!empty($reservation['notes'])): ?>
                                            <div class="reservation-notes">
                                                <?php echo nl2br(htmlspecialchars($reservation['notes'], ENT_QUOTES, 'UTF-8')); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <?php if ($reservation['status'] !== 'approved'): ?>
                                                <form method="post" action="admin.php">
                                                    <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] !== 'declined'): ?>
                                                <form method="post" action="admin.php">
                                                    <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="status" value="declined">
                                                    <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] !== 'pending'): ?>
                                                <form method="post" action="admin.php">
                                                    <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?php echo htmlspecialchars((string) $reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Mark Pending</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script src="js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="js/vendor/jquery-1.12.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>

</html>