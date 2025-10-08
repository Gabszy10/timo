<?php
// Database connection settings for the reservation form.
// Update the credentials below to match your XAMPP/MySQL configuration.

define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'church');

function get_db_connection()
{
    $connection = mysqli_init();

    if ($connection === false) {
        throw new Exception('Database connection could not be initialized.');
    }

    if (!mysqli_real_connect($connection, DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME)) {
        $error = mysqli_connect_error();
        mysqli_close($connection);
        throw new Exception('Database connection failed: ' . $error);
    }

    if (!mysqli_set_charset($connection, 'utf8mb4')) {
        $error = mysqli_error($connection);
        mysqli_close($connection);
        throw new Exception('Error setting database charset: ' . $error);
    }

    return $connection;
}
