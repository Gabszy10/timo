<?php
/**
 * Helper functions for sending SMS notifications via the IPROG SMS API.
 */

if (!defined('IPROG_SMS_API_URL')) {
    define('IPROG_SMS_API_URL', 'https://sms.iprogtech.com/api/v1/sms_messages');
}

if (!defined('IPROG_SMS_API_TOKEN')) {
    define('IPROG_SMS_API_TOKEN', 'ca0243cac17c1a885b045895abc154b2852ecdc4');
}

if (!defined('RESERVATION_ADMIN_SMS_PHONE')) {
    define('RESERVATION_ADMIN_SMS_PHONE', '639655821448');
}

/**
 * Normalize a phone number into the expected 63XXXXXXXXXX format.
 */
function normalize_sms_phone_number(string $phoneNumber): string
{
    $digitsOnly = preg_replace('/[^0-9+]/', '', $phoneNumber);
    if ($digitsOnly === null) {
        $digitsOnly = $phoneNumber;
    }

    $digitsOnly = trim($digitsOnly);
    if ($digitsOnly === '') {
        return '';
    }

    if (strpos($digitsOnly, '+') === 0) {
        $digitsOnly = substr($digitsOnly, 1);
    }

    if (strpos($digitsOnly, '0') === 0 && strlen($digitsOnly) === 11) {
        $digitsOnly = '63' . substr($digitsOnly, 1);
    }

    return $digitsOnly;
}

/**
 * Send an SMS message via the configured provider.
 */
function send_sms_notification(string $phoneNumber, string $message): void
{
    $normalizedPhone = normalize_sms_phone_number($phoneNumber);
    $trimmedMessage = trim($message);

    if ($normalizedPhone === '' || $trimmedMessage === '') {
        return;
    }

    $payload = [
        'api_token' => IPROG_SMS_API_TOKEN,
        'message' => $trimmedMessage,
        'phone_number' => $normalizedPhone,
    ];

    $ch = curl_init(IPROG_SMS_API_URL);
    if ($ch === false) {
        error_log('SMS notification failed: Unable to initialize cURL.');
        return;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('SMS notification failed: ' . $error);
        return;
    }

    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpStatus < 200 || $httpStatus >= 300) {
        error_log(sprintf('SMS notification unexpected status %d: %s', $httpStatus, $response));
    }
}
