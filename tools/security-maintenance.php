<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../backend/db.php';
$conn->query('DELETE FROM security_attempts WHERE created_at<NOW()-INTERVAL 1 DAY');
$conn->query('DELETE FROM login_attempts WHERE created_at<NOW()-INTERVAL 90 DAY');
echo "Expired security attempts removed.\n";
