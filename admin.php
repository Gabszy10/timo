<?php
session_start();

require_once __DIR__ . '/includes/db_connection.php';

const ADMIN_LOGIN_ACTION = 'login';
const ADMIN_STATUS_UPDATE_ACTION = 'update_status';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin';

/**
 * Determine if the current session is authenticated as an admin user.
 */
function is_admin_authenticated(): bool
{
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

/**
 * Authenticate the admin user using simple static credentials.
 */
function authenticate_admin(string $username, string $password): bool
{
    return $username === ADMIN_USERNAME && $password === ADMIN_PASSWORD;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === ADMIN_LOGIN_ACTION) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $loginError = 'Please provide a username and password.';
        } else {
            if (authenticate_admin($username, $password)) {
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_flash'] = 'Welcome back!';
                header('Location: admin.php');
                exit;
            }
            $loginError = 'Invalid username or password.';
        }
    } elseif ($action === ADMIN_STATUS_UPDATE_ACTION && is_admin_authenticated()) {
        $reservationId = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $status = (string) ($_POST['status'] ?? '');

        try {
            update_reservation_status($reservationId, $status);
            $_SESSION['admin_flash'] = 'Reservation status updated successfully.';
        } catch (Exception $exception) {
            $_SESSION['admin_flash'] = $exception->getMessage();
        }

        header('Location: admin.php');
        exit;
    }
}

$flashMessage = $_SESSION['admin_flash'] ?? '';
if ($flashMessage !== '') {
    unset($_SESSION['admin_flash']);
}

$reservations = [];
if (is_admin_authenticated()) {
    try {
        $reservations = fetch_reservations();
    } catch (Exception $exception) {
        $flashMessage = $exception->getMessage();
    }
}

function render_status_badge(string $status): string
{
    $badgeClasses = [
        'pending' => 'badge badge-warning',
        'approved' => 'badge badge-success',
        'declined' => 'badge badge-danger',
    ];

    $class = $badgeClasses[$status] ?? 'badge badge-secondary';

    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>';
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

        .reservation-notes {
            max-width: 320px;
            white-space: pre-wrap;
        }

        .status-actions button {
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <?php if (!is_admin_authenticated()) : ?>
        <div class="login-card">
            <h2 class="text-center mb-4">Admin Login</h2>
            <?php if ($loginError !== '') : ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="<?php echo ADMIN_LOGIN_ACTION; ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
            <p class="text-center text-muted mt-4 mb-0">Use username <strong>admin</strong> and password <strong>admin</strong>.</p>
        </div>
    <?php else : ?>
        <div class="admin-wrapper">
            <div class="admin-header">
                <h1>Reservations Dashboard</h1>
                <a class="logout-link" href="admin.php?logout=1">Logout</a>
            </div>
            <?php if ($flashMessage !== '') : ?>
                <div class="alert alert-info" role="alert">
                    <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if (count($reservations) === 0) : ?>
                <p class="text-muted mb-0">No reservations have been submitted yet.</p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Status</th>
                                <th scope="col">Notes</th>
                                <th scope="col" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation) : ?>
                                <tr>
                                    <th scope="row"><?php echo htmlspecialchars($reservation['id'], ENT_QUOTES, 'UTF-8'); ?></th>
                                    <td><?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                    <td><a href="tel:<?php echo htmlspecialchars($reservation['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reservation['phone'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                    <td><?php echo htmlspecialchars($reservation['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['preferred_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['preferred_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo render_status_badge($reservation['status']); ?></td>
                                    <td class="reservation-notes"><?php echo htmlspecialchars($reservation['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="status-actions text-center">
                                        <form method="post" action="admin.php" class="d-inline">
                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-sm btn-success" <?php echo $reservation['status'] === 'approved' ? 'disabled' : ''; ?>>Approve</button>
                                        </form>
                                        <form method="post" action="admin.php" class="d-inline">
                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="status" value="declined">
                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo $reservation['status'] === 'declined' ? 'disabled' : ''; ?>>Decline</button>
                                        </form>
                                        <form method="post" action="admin.php" class="d-inline">
                                            <input type="hidden" name="action" value="<?php echo ADMIN_STATUS_UPDATE_ACTION; ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" class="btn btn-sm btn-secondary" <?php echo $reservation['status'] === 'pending' ? 'disabled' : ''; ?>>Reset</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
