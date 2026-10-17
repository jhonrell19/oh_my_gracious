<?php
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'error',
    'message' => 'Phone/SMS authentication has been removed. Phone number is used only as contact information.'
]);
