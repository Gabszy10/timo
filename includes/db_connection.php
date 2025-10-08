<?php
// Database connection settings for the reservation form.
// Update the credentials below to match your XAMPP/MySQL configuration.

define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'st_helena_parish');

function get_db_connection()
{
    $connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if (!$connection) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }

    if (!mysqli_set_charset($connection, 'utf8mb4')) {
        throw new Exception('Error setting database charset: ' . mysqli_error($connection));
    }

    return $connection;
}
