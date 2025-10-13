<?php
$url = 'https://sms.iprogtech.com/api/v1/sms_messages';

$firstName = "Jhon";
$lastName = "Doe";
$message = sprintf("Hi %s %s, Welcome to IPROG SMS API.", $firstName, $lastName);

$data = [
    'api_token' => 'ca0243cac17c1a885b045895abc154b2852ecdc4',
    'message' => $message,
    'phone_number' => '639655821448'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>