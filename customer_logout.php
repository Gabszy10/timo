<?php
require_once __DIR__ . '/includes/customer_auth.php';

log_out_customer();

header('Location: customer_login.php');
exit;
