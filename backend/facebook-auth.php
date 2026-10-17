<?php
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'error',
    'message' => 'Facebook authentication has been removed. Use email/password or Google.'
]);
