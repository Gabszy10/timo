<?php

session_start();

if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/includes/reservation_repository.php';
require_once __DIR__ . '/includes/reservation_pdf_renderer.php';

$reservationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($reservationId === null || $reservationId === false || $reservationId <= 0) {
    http_response_code(400);
    echo 'Invalid reservation selected.';
    exit;
}

try {
    $reservation = fetch_reservation_by_id($reservationId);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Unable to load the reservation details.';
    exit;
}

if ($reservation === null) {
    http_response_code(404);
    echo 'Reservation not found.';
    exit;
}

ReservationPdfRenderer::stream($reservation);
exit;

