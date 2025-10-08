<?php
require_once __DIR__ . '/includes/db_connection.php';

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

$flashMessage = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === ADMIN_STATUS_UPDATE_ACTION) {
        $reservationId = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;
        $status = (string) ($_POST['status'] ?? '');

        try {
            update_reservation_status($reservationId, $status);
            $flashMessage = 'Reservation status updated successfully.';
            $flashType = 'success';
        } catch (Exception $exception) {
            $flashMessage = $exception->getMessage();
            $flashType = 'danger';
        }
    }
}

try {
    $reservations = fetch_reservations();
} catch (Exception $exception) {
    $flashMessage = $exception->getMessage();
    $flashType = 'danger';
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
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 30px;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 28px;
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
    <div class="admin-wrapper">
        <div class="admin-header">
            <h1>Reservations Dashboard</h1>
        </div>
        <?php if ($flashMessage !== '') : ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (empty($reservations)) : ?>
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

    <script src="js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="js/vendor/jquery-1.12.4.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>

</html>
