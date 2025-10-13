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

    $reservation = null;

    if (function_exists('mysqli_stmt_get_result')) {
        $result = mysqli_stmt_get_result($statement);
        if ($result instanceof mysqli_result) {
            $reservation = mysqli_fetch_assoc($result) ?: null;
            mysqli_free_result($result);
        }
    } else {
        $boundId = $boundName = $boundEmail = $boundPhone = $boundEventType = null;
        $boundPreferredDate = $boundPreferredTime = $boundStatus = $boundNotes = $boundCreatedAt = null;

        mysqli_stmt_bind_result(
            $statement,
            $boundId,
            $boundName,
            $boundEmail,
            $boundPhone,
            $boundEventType,
            $boundPreferredDate,
            $boundPreferredTime,
            $boundStatus,
            $boundNotes,
            $boundCreatedAt
        );

        if (mysqli_stmt_fetch($statement)) {
            $reservation = [
                'id' => $boundId,
                'name' => $boundName,
                'email' => $boundEmail,
                'phone' => $boundPhone,
                'event_type' => $boundEventType,
                'preferred_date' => $boundPreferredDate,
                'preferred_time' => $boundPreferredTime,
                'status' => $boundStatus,
                'notes' => $boundNotes,
                'created_at' => $boundCreatedAt,
            ];
        }
    }

    mysqli_stmt_close($statement);

    if ($reservation === null) {
        mysqli_close($connection);
        return null;
    }

    $reservation['attachments'] = [];

    $attachmentsQuery = 'SELECT label, file_name, stored_path FROM reservation_attachments WHERE reservation_id = ? ORDER BY id ASC';
    $attachmentsStatement = mysqli_prepare($connection, $attachmentsQuery);

    if ($attachmentsStatement instanceof mysqli_stmt) {
        mysqli_stmt_bind_param($attachmentsStatement, 'i', $reservationId);

        if (mysqli_stmt_execute($attachmentsStatement)) {
            if (function_exists('mysqli_stmt_get_result')) {
                $attachmentsResult = mysqli_stmt_get_result($attachmentsStatement);
                if ($attachmentsResult instanceof mysqli_result) {
                    while ($attachmentRow = mysqli_fetch_assoc($attachmentsResult)) {
                        $storedPath = isset($attachmentRow['stored_path']) ? (string) $attachmentRow['stored_path'] : '';
                        $fileName = isset($attachmentRow['file_name']) ? (string) $attachmentRow['file_name'] : '';

                        if ($storedPath === '' || $fileName === '') {
                            continue;
                        }

                        $reservation['attachments'][] = [
                            'label' => isset($attachmentRow['label']) ? (string) $attachmentRow['label'] : '',
                            'file_name' => $fileName,
                            'stored_path' => $storedPath,
                        ];
                    }

                    mysqli_free_result($attachmentsResult);
                }
            } else {
                $boundLabel = $boundFileName = $boundStoredPath = null;
                mysqli_stmt_bind_result($attachmentsStatement, $boundLabel, $boundFileName, $boundStoredPath);

                while (mysqli_stmt_fetch($attachmentsStatement)) {
                    $storedPath = isset($boundStoredPath) ? (string) $boundStoredPath : '';
                    $fileName = isset($boundFileName) ? (string) $boundFileName : '';

                    if ($storedPath === '' || $fileName === '') {
                        continue;
                    }

                    $reservation['attachments'][] = [
                        'label' => isset($boundLabel) ? (string) $boundLabel : '',
                        'file_name' => $fileName,
                        'stored_path' => $storedPath,
                    ];
                }
            }
        }

        mysqli_stmt_close($attachmentsStatement);
    }

    mysqli_close($connection);

    return $reservation;
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

    try {
        $preferredDate = $preferredTime = $eventType = null;
        $scheduleQuery = 'SELECT preferred_date, preferred_time, event_type FROM reservations WHERE id = ? LIMIT 1';
        $scheduleStatement = mysqli_prepare($connection, $scheduleQuery);

        if ($scheduleStatement === false) {
            throw new Exception('Unable to prepare reservation lookup: ' . mysqli_error($connection));
        }

        mysqli_stmt_bind_param($scheduleStatement, 'i', $reservationId);

        if (!mysqli_stmt_execute($scheduleStatement)) {
            $error = 'Unable to fetch reservation: ' . mysqli_stmt_error($scheduleStatement);
            mysqli_stmt_close($scheduleStatement);
            throw new Exception($error);
        }

        mysqli_stmt_bind_result($scheduleStatement, $preferredDate, $preferredTime, $eventType);

        if (!mysqli_stmt_fetch($scheduleStatement)) {
            mysqli_stmt_close($scheduleStatement);
            throw new Exception('Reservation not found.');
        }

        mysqli_stmt_close($scheduleStatement);

        if ($status === 'approved') {
            $date = $preferredDate !== null ? trim((string) $preferredDate) : '';
            $time = $preferredTime !== null ? trim((string) $preferredTime) : '';

            if ($date === '' || $time === '') {
                throw new Exception('A reservation must have a scheduled date and time before it can be approved.');
            }

            $conflictQuery = 'SELECT COUNT(*) FROM reservations WHERE preferred_date = ? AND preferred_time = ? AND status = ? AND id <> ?';
            $conflictStatement = mysqli_prepare($connection, $conflictQuery);

            if ($conflictStatement === false) {
                throw new Exception('Unable to prepare reservation conflict check: ' . mysqli_error($connection));
            }

            $approvedStatus = 'approved';
            mysqli_stmt_bind_param($conflictStatement, 'sssi', $date, $time, $approvedStatus, $reservationId);

            if (!mysqli_stmt_execute($conflictStatement)) {
                $error = 'Unable to verify reservation conflicts: ' . mysqli_stmt_error($conflictStatement);
                mysqli_stmt_close($conflictStatement);
                throw new Exception($error);
            }

            mysqli_stmt_bind_result($conflictStatement, $conflictCount);
            mysqli_stmt_fetch($conflictStatement);
            mysqli_stmt_close($conflictStatement);

            if ((int) $conflictCount > 0) {
                $eventLabel = $eventType !== null && trim((string) $eventType) !== ''
                    ? trim((string) $eventType)
                    : 'event';
                throw new Exception(sprintf(
                    'Another %s has already been approved for %s at %s. Please decline the other reservation before approving this one.',
                    $eventLabel,
                    $date,
                    $time
                ));
            }
        }

        $query = 'UPDATE reservations SET status = ? WHERE id = ?';
        $statement = mysqli_prepare($connection, $query);

        if ($statement === false) {
            throw new Exception('Unable to prepare reservation update: ' . mysqli_error($connection));
        }

        mysqli_stmt_bind_param($statement, 'si', $status, $reservationId);

        if (!mysqli_stmt_execute($statement)) {
            $error = 'Unable to update reservation status: ' . mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            throw new Exception($error);
        }

        mysqli_stmt_close($statement);
    } finally {
        mysqli_close($connection);
    }
}
