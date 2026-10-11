<?php

declare(strict_types=1);
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'success',
    'csrf_token' => csrfToken(),
    'google_enabled' => GOOGLE_CLIENT_ID !== '',
    'google_client_id' => GOOGLE_CLIENT_ID,
    'facebook_enabled' => FACEBOOK_APP_ID !== '' && FACEBOOK_APP_SECRET !== '',
    'facebook_app_id' => FACEBOOK_APP_ID,
    'facebook_graph_version' => FACEBOOK_GRAPH_VERSION,
    'phone_enabled' => TWILIO_ACCOUNT_SID !== '' && TWILIO_AUTH_TOKEN !== '' && TWILIO_VERIFY_SERVICE_SID !== '',
]);
