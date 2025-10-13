<?php

class ReservationPdfRenderer
{
    /**
     * Stream a reservation summary PDF to the browser.
     *
     * @param array<string, mixed> $reservation
     */
    public static function stream(array $reservation): void
    {
        $reservationId = isset($reservation['id']) ? (int) $reservation['id'] : 0;
        $documentTitle = 'Reservation #' . ($reservationId > 0 ? $reservationId : 'Summary');

        $detailLines = [
            'Name: ' . self::formatValue($reservation['name'] ?? '', 'Not provided'),
            'Email: ' . self::formatValue($reservation['email'] ?? '', 'Not provided'),
            'Phone: ' . self::formatValue($reservation['phone'] ?? '', 'Not provided'),
            'Event type: ' . self::formatValue($reservation['event_type'] ?? '', 'Not specified'),
            'Preferred date: ' . self::formatDateValue($reservation['preferred_date'] ?? null),
            'Preferred time: ' . self::formatTimeValue($reservation['preferred_time'] ?? null),
            'Status: ' . self::formatValue(ucfirst(strtolower((string) ($reservation['status'] ?? 'Pending'))), 'Pending'),
            'Submitted: ' . self::formatDateTimeValue($reservation['created_at'] ?? null),
        ];

        $notes = trim((string) ($reservation['notes'] ?? ''));
        if ($notes === '') {
            $notes = 'No additional notes provided.';
        }

        $notesLines = explode("\n", wordwrap($notes, 90));

        $pdf = self::buildPdfDocument($documentTitle, $detailLines, $notesLines);

        header('Content-Type: application/pdf');
        $fileName = 'reservation-' . ($reservationId > 0 ? $reservationId : 'summary') . '.pdf';
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdf));

        echo $pdf;
    }

    /**
     * @param array<int, string> $detailLines
     * @param array<int, string> $notesLines
     */
    private static function buildPdfDocument(string $title, array $detailLines, array $notesLines): string
    {
        $contentLines = [];
        $contentLines[] = 'BT';
        $contentLines[] = '/F1 24 Tf';
        $contentLines[] = '72 760 Td';
        $contentLines[] = '(' . self::escapeText($title) . ') Tj';
        $contentLines[] = '/F2 12 Tf';
        $contentLines[] = '0 -30 Td';

        foreach ($detailLines as $detailLine) {
            $contentLines[] = '(' . self::escapeText($detailLine) . ') Tj';
            $contentLines[] = '0 -18 Td';
        }

        if (!empty($notesLines)) {
            $contentLines[] = '0 -10 Td';
            $contentLines[] = '/F1 14 Tf';
            $contentLines[] = '(' . self::escapeText('Notes') . ') Tj';
            $contentLines[] = '/F2 12 Tf';
            $contentLines[] = '0 -18 Td';

            foreach ($notesLines as $notesLine) {
                $contentLines[] = '(' . self::escapeText($notesLine) . ') Tj';
                $contentLines[] = '0 -16 Td';
            }
        }

        $contentLines[] = 'ET';

        $streamContent = implode("\n", $contentLines) . "\n";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>';
        $objects[] = '<< /Length ' . strlen($streamContent) . ' >>\nstream\n' . $streamContent . 'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $objectContent) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $objectContent . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= 'xref\n0 ' . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>\n';
        $pdf .= 'startxref\n' . $xrefPosition . "\n";
        $pdf .= '%%EOF';

        return $pdf;
    }

    private static function escapeText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private static function formatValue(string $value, string $fallback): string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? $fallback : $trimmed;
    }

    private static function formatDateValue(?string $value): string
    {
        if ($value === null) {
            return 'Not provided';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Not provided';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return date('F j, Y', $timestamp);
        }

        return $trimmed;
    }

    private static function formatTimeValue(?string $value): string
    {
        if ($value === null) {
            return 'Not provided';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Not provided';
        }

        if (strpos($trimmed, '-') !== false) {
            $parts = preg_split('/\s*-\s*/', $trimmed);
            if (is_array($parts) && count($parts) >= 2) {
                $formattedParts = [];
                foreach (array_slice($parts, 0, 2) as $segment) {
                    $formattedParts[] = self::formatSingleTime($segment);
                }
                return implode(' - ', $formattedParts);
            }
        }

        return self::formatSingleTime($trimmed);
    }

    private static function formatSingleTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('g:i A', $timestamp);
        }

        return $value;
    }

    private static function formatDateTimeValue(?string $value): string
    {
        if ($value === null) {
            return 'Not provided';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Not provided';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return date('F j, Y g:i A', $timestamp);
        }

        return $trimmed;
    }
}
