<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../backend/config.php';
$failed = 0;
function check(bool $ok, string $label): void { global $failed; echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL; if (!$ok) $failed++; }
check(version_compare(PHP_VERSION,'8.2','>='), 'PHP 8.2+');
foreach (['mysqli','curl','openssl','mbstring'] as $ext) check(extension_loaded($ext), 'Extension: '.$ext);
$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;
check(class_exists('Google\Client'), 'Google API client installed');
check(class_exists('PHPMailer\PHPMailer\PHPMailer'), 'PHPMailer installed');
check(APP_ENV==='production', 'Production environment');
check(parse_url(APP_URL,PHP_URL_SCHEME)==='https', 'HTTPS APP_URL');
check(env('DB_USER','root')!=='root' && env('DB_PASSWORD','')!=='', 'Dedicated database account');
check(GOOGLE_CLIENT_ID!=='', 'Google client ID configured');
check(env('SMTP_HOST','')!=='' && env('SMTP_USERNAME','')!=='' && env('SMTP_PASSWORD','')!=='', 'SMTP configured');
check(filter_var(env('MAIL_FROM',''),FILTER_VALIDATE_EMAIL)!==false, 'Mail sender configured');
check(is_file(dirname(__DIR__).'/models/nail-seg.onnx'), 'AR model present');
check(is_writable(dirname(__DIR__).'/uploads/designs'), 'Design storage writable');
if (env('PAYMONGO_ENABLED','false')==='true') {
 check(paymongoEnabled(), 'PayMongo credentials and webhook secret');
 echo str_starts_with(PAYMONGO_SECRET_KEY,'sk_live_') ? "Payment mode: LIVE\n" : "Payment mode: TEST (no real money)\n";
}
if (extension_loaded('mysqli')) {
 require_once __DIR__.'/../backend/db.php';
 foreach (['payment_checkout_requests','payment_webhook_events'] as $table) {
   $result=$conn->query("SHOW TABLES LIKE '".$table."'"); check($result->num_rows===1,'Table: '.$table);
 }
 $owner=$conn->query("SELECT id FROM users WHERE role='super_admin' AND account_status='active' LIMIT 1");
 check($owner->num_rows===1,'Active owner account');
}
echo "External payments, email delivery, HTTPS and role workflows still require real acceptance tests.\n";
exit($failed ? 1 : 0);
