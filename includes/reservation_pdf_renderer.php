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

        $statusNormalized = strtolower(trim((string) ($reservation['status'] ?? 'pending')));
        if ($statusNormalized === '') {
            $statusNormalized = 'pending';
        }

        $statusDisplay = ucfirst($statusNormalized);

        $statusStyles = [
            'approved' => [
                'accent' => [0.13, 0.54, 0.38],
                'badge_bg' => [0.78, 0.93, 0.84],
                'badge_text' => [0.10, 0.38, 0.24],
            ],
            'declined' => [
                'accent' => [0.70, 0.22, 0.22],
                'badge_bg' => [0.98, 0.84, 0.84],
                'badge_text' => [0.56, 0.12, 0.12],
            ],
            'pending' => [
                'accent' => [0.29, 0.33, 0.69],
                'badge_bg' => [0.85, 0.87, 0.98],
                'badge_text' => [0.30, 0.32, 0.64],
            ],
        ];

        $style = $statusStyles[$statusNormalized] ?? $statusStyles['pending'];

        $detailItems = [
            ['label' => 'Reservation ID', 'value' => $reservationId > 0 ? '#' . $reservationId : 'Pending assignment'],
            ['label' => 'Guest Name', 'value' => self::formatValue($reservation['name'] ?? '', 'Not provided')],
            ['label' => 'Contact Email', 'value' => self::formatValue($reservation['email'] ?? '', 'Not provided')],
            ['label' => 'Contact Phone', 'value' => self::formatValue($reservation['phone'] ?? '', 'Not provided')],
            ['label' => 'Event Type', 'value' => self::formatValue($reservation['event_type'] ?? '', 'Not specified')],
            ['label' => 'Preferred Date', 'value' => self::formatDateValue($reservation['preferred_date'] ?? null)],
            ['label' => 'Preferred Time', 'value' => self::formatTimeValue($reservation['preferred_time'] ?? null)],
            ['label' => 'Status', 'value' => self::formatValue($statusDisplay, 'Pending')],
            ['label' => 'Submitted On', 'value' => self::formatDateTimeValue($reservation['created_at'] ?? null)],
        ];

        $notesLines = self::prepareNotesLines($reservation['notes'] ?? null);
        $attachmentLines = self::prepareAttachmentLines($reservation['attachments'] ?? []);

        $pdf = self::buildPdfDocument($documentTitle, $detailItems, $notesLines, $attachmentLines, $statusDisplay, $style);

        $fileName = 'reservation-' . ($reservationId > 0 ? $reservationId : 'summary') . '.pdf';
        self::streamPdfToBrowser($pdf, $fileName);
    }

    /**
     * @param array<int, array{label: string, value: string}> $detailItems
     * @param array<int, string> $notesLines
     * @param array<int, string> $attachmentLines
     * @param array{accent: array{float, float, float}, badge_bg: array{float, float, float}, badge_text: array{float, float, float}} $statusStyle
     */
    private static function buildPdfDocument(string $title, array $detailItems, array $notesLines, array $attachmentLines, string $statusDisplay, array $statusStyle): string
    {
        $contentLines = [];

        // Header background
        $contentLines[] = 'q';
        $contentLines[] = self::formatColor($statusStyle['accent']) . ' rg';
        $contentLines[] = '36 720 540 68 re';
        $contentLines[] = 'f';
        $contentLines[] = 'Q';

        // Header text
        $contentLines[] = 'BT';
        $contentLines[] = '/F1 24 Tf';
        $contentLines[] = '1 1 1 rg';
        $contentLines[] = '72 756 Td';
        $contentLines[] = '(' . self::escapeText($title) . ') Tj';
        $contentLines[] = '/F2 12 Tf';
        $contentLines[] = '0 -18 Td';
        $contentLines[] = '(' . self::escapeText('Generated on ' . date('F j, Y')) . ') Tj';
        $contentLines[] = 'ET';

        // Status badge
        $contentLines[] = 'q';
        $contentLines[] = self::formatColor($statusStyle['badge_bg']) . ' rg';
        $contentLines[] = '420 742 156 24 re';
        $contentLines[] = 'f';
        $contentLines[] = 'Q';

        $contentLines[] = 'BT';
        $contentLines[] = '/F2 12 Tf';
        $contentLines[] = self::formatColor($statusStyle['badge_text']) . ' rg';
        $contentLines[] = '430 748 Td';
        $contentLines[] = '(' . self::escapeText(strtoupper($statusDisplay)) . ') Tj';
        $contentLines[] = 'ET';

        $cursorY = 680;

        foreach ($detailItems as $detailItem) {
            $label = strtoupper($detailItem['label']);
            $value = $detailItem['value'];

            $contentLines[] = 'BT';
            $contentLines[] = '/F2 10 Tf';
            $contentLines[] = '0.45 0.51 0.67 rg';
            $contentLines[] = '72 ' . $cursorY . ' Td';
            $contentLines[] = '(' . self::escapeText($label) . ') Tj';
            $contentLines[] = '/F2 13 Tf';
            $contentLines[] = '0 -16 Td';
            $contentLines[] = '0 0 0 rg';
            $contentLines[] = '(' . self::escapeText($value) . ') Tj';
            $contentLines[] = 'ET';

            // Divider line
            $contentLines[] = 'q';
            $contentLines[] = '0.88 0.92 0.97 rg';
            $contentLines[] = '72 ' . ($cursorY - 4) . ' 468 1.1 re';
            $contentLines[] = 'f';
            $contentLines[] = 'Q';

            $cursorY -= 40;
        }

        if (!empty($notesLines)) {
            $cursorY -= 12;
            if ($cursorY < 180) {
                $cursorY = 180;
            }

            $contentLines[] = 'BT';
            $contentLines[] = '/F1 14 Tf';
            $contentLines[] = '0 0 0 rg';
            $contentLines[] = '72 ' . $cursorY . ' Td';
            $contentLines[] = '(' . self::escapeText('Notes & Special Requests') . ') Tj';
            $contentLines[] = 'ET';

            $cursorY -= 24;

            $contentLines[] = 'q';
            $contentLines[] = '0.96 0.97 1 rg';
            $contentLines[] = '60 ' . ($cursorY - 8) . ' 492 ' . (count($notesLines) * 16 + 28) . ' re';
            $contentLines[] = 'f';
            $contentLines[] = 'Q';

            $contentLines[] = 'BT';
            $contentLines[] = '/F2 12 Tf';
            $contentLines[] = '0.13 0.16 0.24 rg';
            $contentLines[] = '72 ' . $cursorY . ' Td';

            foreach ($notesLines as $notesLine) {
                $contentLines[] = '(' . self::escapeText($notesLine) . ') Tj';
                $contentLines[] = '0 -16 Td';
            }

            $contentLines[] = 'ET';
            $cursorY -= 32 + (count($notesLines) * 16);
        }

        if (!empty($attachmentLines)) {
            if ($cursorY < 160) {
                $cursorY = 160;
            }

            $contentLines[] = 'BT';
            $contentLines[] = '/F1 14 Tf';
            $contentLines[] = '0 0 0 rg';
            $contentLines[] = '72 ' . $cursorY . ' Td';
            $contentLines[] = '(' . self::escapeText('Attachments') . ') Tj';
            $contentLines[] = 'ET';

            $cursorY -= 24;

            $contentLines[] = 'q';
            $contentLines[] = '0.96 0.97 1 rg';
            $contentLines[] = '60 ' . ($cursorY - 8) . ' 492 ' . (count($attachmentLines) * 16 + 28) . ' re';
            $contentLines[] = 'f';
            $contentLines[] = 'Q';

            $contentLines[] = 'BT';
            $contentLines[] = '/F2 12 Tf';
            $contentLines[] = '0.13 0.16 0.24 rg';
            $contentLines[] = '72 ' . $cursorY . ' Td';

            foreach ($attachmentLines as $attachmentLine) {
                $contentLines[] = '(' . self::escapeText($attachmentLine) . ') Tj';
                $contentLines[] = '0 -16 Td';
            }

            $contentLines[] = 'ET';
        }

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

    /**
     * @param mixed $notes
     * @return array<int, string>
     */
    private static function prepareNotesLines($notes): array
    {
        $notesText = trim((string) ($notes ?? ''));
        if ($notesText === '') {
            $notesText = 'No additional notes provided.';
        }

        return self::wrapMultilineText($notesText);
    }

    /**
     * @param array<int, array{label?: string, file_name?: string, stored_path?: string}> $attachments
     * @return array<int, string>
     */
    private static function prepareAttachmentLines(array $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        $lines = [];
        $total = count($attachments);

        foreach ($attachments as $index => $attachment) {
            $label = trim((string) ($attachment['label'] ?? ''));
            if ($label === '') {
                $label = 'Attachment';
                if ($total > 1) {
                    $label .= ' #' . ($index + 1);
                }
            }

            $fileName = trim((string) ($attachment['file_name'] ?? ''));
            if ($fileName !== '') {
                $label .= ' · ' . $fileName;
            }

            $lines = array_merge($lines, self::wrapMultilineText($label));
        }

        return array_values($lines);
    }

    /**
     * @return array<int, string>
     */
    private static function wrapMultilineText(string $text, int $width = 90): array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($normalized === '') {
            return [];
        }

        $lines = [];
        foreach (explode("\n", $normalized) as $segment) {
            $wrapped = wordwrap($segment, $width, "\n", true);
            $lines = array_merge($lines, explode("\n", $wrapped === '' ? $segment : $wrapped));
        }

        $lines = array_filter($lines, static function (string $line): bool {
            return trim($line) !== '';
        });

        return array_values($lines);
    }

    private static function streamPdfToBrowser(string $pdf, string $fileName): void
    {
        self::clearOutputBuffers();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $length = function_exists('mb_strlen') ? mb_strlen($pdf, '8bit') : strlen($pdf);
        header('Content-Length: ' . $length);

        echo $pdf;
    }

    private static function clearOutputBuffers(): void
    {
        if (ob_get_level() > 0 && ob_get_length() !== false && ob_get_length() > 0) {
            @ob_clean();
        }
    }

    private static function escapeText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color
     */
    private static function formatColor(array $color): string
    {
        $components = array_map(static function (float $value): string {
            $clamped = max(0.0, min(1.0, $value));
            return number_format($clamped, 3, '.', '');
        }, $color);

        return implode(' ', $components);
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
