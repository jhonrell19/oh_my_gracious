<?php
require_once __DIR__.'/backend/authorization.php';require_once __DIR__.'/backend/csrf.php';requireSuperAdmin();
require_once __DIR__.'/backend/payment-settings-helper.php';
$message=(string)($_SESSION['payment_notice']??'');unset($_SESSION['payment_notice']);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();
 try {
    $id=(int)$_SESSION['user_id'];confirmPassword($conn,$id,(string)($_POST['password']??''));
    if(!verifySecondFactor($conn,$id,(string)($_POST['code']??'')))throw new RuntimeException('Enter a fresh authenticator code or unused recovery code.');
    $action=(string)($_POST['action']??'verify');
    $key=trim((string)($_POST['api_key']??''));$secret=trim((string)($_POST['webhook_secret']??''));
    if($key==='')$key=PAYMONGO_SECRET_KEY;if($secret==='')$secret=PAYMONGO_WEBHOOK_SECRET;
    if($action==='verify'){
        $verified=checkMerchantConnection(PAYMONGO_SECRET_KEY,PAYMONGO_WEBHOOK_SECRET);
        setSetting($conn,'payment_connection_check',json_encode($verified),$id);
        audit($conn,'payment_connection_verified','settings');
        $_SESSION['payment_notice']='Connected successfully. API credentials and webhook registration verified. Complete a payment test to verify delivery; payout readiness is checked separately in PayMongo.';
    }elseif($action==='save'){
        $enabled=isset($_POST['enabled']);
        $methods=array_values(array_intersect(['card','gcash','paymaya','qrph'],(array)($_POST['methods']??[])));
        if(!$methods)throw new RuntimeException('Choose at least one account-enabled payment method.');
        if(str_starts_with($key,'sk_live_') && !isset($_POST['live_confirm']))throw new RuntimeException('Confirm owner approval and live-payment readiness before saving a live key.');
        $verified=checkMerchantConnection($key,$secret);
        $lock=$conn->query("SELECT GET_LOCK('omg_payment_config',10) ok")->fetch_assoc();
        if((int)$lock['ok']!==1)throw new RuntimeException('Checkout is busy. Try again shortly.');
        try {
            $latest=savedPaymentConfig();
            if($latest && ($latest['key']!==PAYMONGO_SECRET_KEY || $latest['webhook']!==PAYMONGO_WEBHOOK_SECRET))throw new RuntimeException('Settings changed in another session. Reload before saving.');
            if($key!==PAYMONGO_SECRET_KEY || $secret!==PAYMONGO_WEBHOOK_SECRET){
                $pending=(int)$conn->query("SELECT COUNT(*) n FROM payment_checkout_requests r JOIN payments p ON p.booking_id=r.booking_id WHERE p.status NOT IN ('Paid','Refund Required','Refunded')")->fetch_assoc()['n'];
                if($pending>0)throw new RuntimeException('Unresolved online checkouts exist. Reconcile them with PayMongo before changing merchant credentials or mode. Do not delete payment records to bypass this check.');
            }
            audit($conn,'payment_configuration_change_requested','settings',null,['mode'=>str_starts_with($key,'sk_live_')?'live':'test','enabled'=>$enabled]);
            savePaymentConfig(['key'=>$key,'webhook'=>$secret,'methods'=>implode(',',$methods),'enabled'=>$enabled]);
            setSetting($conn,'payment_connection_check',json_encode($verified),$id);
        } finally {$conn->query("SELECT RELEASE_LOCK('omg_payment_config')");}
        $_SESSION['payment_notice']='Connected successfully. Verified payment settings saved. Payout destination and live readiness must still be confirmed in PayMongo.';
    }else throw new RuntimeException('Invalid action.');
    redirectTo('payment-settings.php');
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Settings could not be saved. Contact the administrator.';}
}
$check=json_decode(getSetting($conn,'payment_connection_check','{}'),true)?:[];
$matches=isset($check['fingerprint']) && hash_equals($check['fingerprint'],paymentConfigFingerprint(PAYMONGO_SECRET_KEY,PAYMONGO_WEBHOOK_SECRET));
$delivery=json_decode(getSetting($conn,'payment_verified_delivery','{}'),true)?:[];
$delivered=isset($delivery['fingerprint'])&&hash_equals($delivery['fingerprint'],paymentConfigFingerprint(PAYMONGO_SECRET_KEY,PAYMONGO_WEBHOOK_SECRET));
$mode=str_starts_with(PAYMONGO_SECRET_KEY,'sk_live_')?'LIVE — real payments':(str_starts_with(PAYMONGO_SECRET_KEY,'sk_test_')?'TEST — simulated card payments':'Not configured');
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Owner Payments | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body><header class="navbar"><div class="logo">Owner Console</div><nav><a href="super-admin-dashboard.php">Dashboard</a><a href="revenue-reports.php">Revenue</a><a href="staff-security.php">Security</a><a href="logout.php">Sign out</a></nav></header><main class="page-wrap narrow"><h1>Online payment settings</h1><p>Connect the salon owner's PayMongo merchant account. Customer payment methods and the owner's payout destination are separate.</p>
<?php if($message):?><div class="alert success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<section class="panel"><h2>Connection status</h2><p><strong>Mode:</strong> <?=h($mode)?></p><p><strong>New online checkouts:</strong> <?=paymongoEnabled()?'Enabled':'Disabled'?></p><p><strong>API key:</strong> <?=PAYMONGO_SECRET_KEY!==''?'Stored — hidden':'Not configured'?></p><p><strong>Webhook secret:</strong> <?=PAYMONGO_WEBHOOK_SECRET!==''?'Stored — hidden':'Not configured'?></p><p><strong>Connection check:</strong> <?=$matches?'Verified at '.h($check['checked_at']):'Not verified for the current configuration'?></p><p><strong>Matching paid webhook received:</strong> <?=$delivered?h($delivery['received_at']):'Not yet recorded by Phase 3'?></p><p><strong>Owner payout readiness:</strong> Confirm in PayMongo; this application does not verify bank ownership or settlement.</p><p>Webhook URL: <code style="overflow-wrap:anywhere"><?=h(appUrl('webhooks/paymongo.php'))?></code></p></section>
<section class="panel"><h2>Verify or update connection</h2><p>Leave secret fields blank to keep current values. Verification makes a read-only PayMongo request and does not charge anyone. Choose only payment methods enabled for the merchant account.</p><form method="post" class="stack" autocomplete="off"><?=csrfInput()?><label>Replacement secret API key<input name="api_key" type="password" autocomplete="new-password" maxlength="255"></label><label>Replacement webhook signing secret<input name="webhook_secret" type="password" autocomplete="new-password" maxlength="255"></label>
<?php foreach(['card'=>'Card','gcash'=>'GCash','paymaya'=>'Maya','qrph'=>'QR Ph'] as $v=>$label):?><label><input type="checkbox" name="methods[]" value="<?=h($v)?>" <?=in_array($v,explode(',',PAYMONGO_PAYMENT_METHODS),true)?'checked':''?>> <?=h($label)?></label><?php endforeach;?>
<label><input type="checkbox" name="enabled" <?=paymongoEnabled()?'checked':''?>> Enable new online checkouts</label><label><input type="checkbox" name="live_confirm"> For LIVE keys: the owner approved this account, payout setup is checked, and the site is ready for an authorized live test.</label><label>Current owner password<input type="password" name="password" autocomplete="current-password" required></label><label>Fresh authenticator or recovery code<input name="code" autocomplete="one-time-code" required></label><button class="btn secondary" name="action" value="verify">Verify current connection</button><button class="btn primary" name="action" value="save">Verify and save settings</button></form><p>Changes are encrypted in private server storage. Existing pending checkouts block credential or mode changes to protect payment reconciliation.</p></section>
<section class="panel"><h2>Where payments go</h2><p>Real payments belong to the merchant account associated with the live key. Configure the owner's wallet and eligible payout destination in PayMongo. This page does not connect PayPal or transfer money.</p><a class="btn secondary" href="https://dashboard.paymongo.com" target="_blank" rel="noopener noreferrer">Open PayMongo dashboard</a><p>Keep testing with test cards. Do not pay a test QR Ph code using a real bank or wallet app.</p></section></main></body></html>
