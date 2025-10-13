<?php

require_once __DIR__ . '/db_connection.php';

/**
 * Fetch all reservations ordered by creation date.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_reservations(): array
{
    $connection = get_db_connection();

    $query = 'SELECT id, name, email, phone, event_type, preferred_date, preferred_time, status, notes, created_at '
        . 'FROM reservations ORDER BY created_at DESC';
    $result = mysqli_query($connection, $query);

    if ($result === false) {
        $error = 'Unable to fetch reservations: ' . mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $reservations = [];
    $reservationIndexById = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $reservationId = isset($row['id']) ? (int) $row['id'] : 0;
        $row['attachments'] = [];
        $reservations[] = $row;

        if ($reservationId > 0) {
            $reservationIndexById[$reservationId] = count($reservations) - 1;
        }
    }

    mysqli_free_result($result);

    if (!empty($reservationIndexById)) {
        $reservationIds = array_keys($reservationIndexById);
        $idList = implode(',', array_map('intval', $reservationIds));

        if ($idList !== '') {
            $attachmentsQuery = 'SELECT reservation_id, label, file_name, stored_path '
                . 'FROM reservation_attachments WHERE reservation_id IN (' . $idList . ') ORDER BY id ASC';
            $attachmentsResult = mysqli_query($connection, $attachmentsQuery);

            if ($attachmentsResult instanceof mysqli_result) {
                while ($attachmentRow = mysqli_fetch_assoc($attachmentsResult)) {
                    $reservationId = isset($attachmentRow['reservation_id']) ? (int) $attachmentRow['reservation_id'] : 0;
                    if (!isset($reservationIndexById[$reservationId])) {
                        continue;
                    }

                    $storedPath = isset($attachmentRow['stored_path']) ? (string) $attachmentRow['stored_path'] : '';
                    $fileName = isset($attachmentRow['file_name']) ? (string) $attachmentRow['file_name'] : '';

                    if ($storedPath === '' || $fileName === '') {
                        continue;
                    }

                    $reservations[$reservationIndexById[$reservationId]]['attachments'][] = [
                        'label' => isset($attachmentRow['label']) ? (string) $attachmentRow['label'] : '',
                        'file_name' => $fileName,
                        'stored_path' => $storedPath,
                    ];
                }

                mysqli_free_result($attachmentsResult);
            }
        }
    }

    mysqli_close($connection);

    return $reservations;
}

/**
 * Fetch a single reservation by id.
 *
 * @return array<string, mixed>|null
 */
function fetch_reservation_by_id(int $reservationId): ?array
{
    if ($reservationId <= 0) {
        return null;
    }

    $connection = get_db_connection();

    $query = 'SELECT id, name, email, phone, event_type, preferred_date, preferred_time, status, notes, created_at '
        . 'FROM reservations WHERE id = ? LIMIT 1';
    $statement = mysqli_prepare($connection, $query);

    if ($statement === false) {
        mysqli_close($connection);
        throw new Exception('Unable to prepare reservation lookup: ' . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($statement, 'i', $reservationId);

    if (!mysqli_stmt_execute($statement)) {
        $error = 'Unable to fetch reservation: ' . mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        mysqli_close($connection);
        throw new Exception($error);
    }

    $result = mysqli_stmt_get_result($statement);
    $reservation = null;

    if ($result instanceof mysqli_result) {
        $reservation = mysqli_fetch_assoc($result) ?: null;
        mysqli_free_result($result);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);

    return $reservation ?: null;
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
