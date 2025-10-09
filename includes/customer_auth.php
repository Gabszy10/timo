<?php
/**
 * Helper functions for managing customer authentication sessions.
 */

declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

/**
 * Ensure a PHP session is active for customer pages.
 */
function customer_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Store customer details in the session after login.
 *
 * @param array<string, mixed> $customer
 */
function log_in_customer(array $customer): void
{
    customer_session_start();

    $_SESSION['customer_id'] = isset($customer['id']) ? (int) $customer['id'] : 0;
    $_SESSION['customer_name'] = isset($customer['name']) ? (string) $customer['name'] : '';
    $_SESSION['customer_email'] = isset($customer['email']) ? (string) $customer['email'] : '';
    $_SESSION['customer_address'] = isset($customer['address']) ? (string) $customer['address'] : '';
}

/**
 * Fetch the currently logged-in customer from the session, if any.
 *
 * @return array<string, mixed>|null
 */
function get_logged_in_customer(): ?array
{
    customer_session_start();

    if (!isset($_SESSION['customer_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['customer_id'],
        'name' => (string) ($_SESSION['customer_name'] ?? ''),
        'email' => (string) ($_SESSION['customer_email'] ?? ''),
        'address' => (string) ($_SESSION['customer_address'] ?? ''),
    ];
}

/**
 * Clear the current customer session.
 */
function log_out_customer(): void
{
    customer_session_start();

    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['customer_address']);
}
