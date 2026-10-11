<?php
require_once __DIR__.'/backend/authorization.php';
require_once __DIR__.'/backend/db.php';
require_once __DIR__.'/backend/csrf.php';
requireSuperAdmin();

$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $open=trim((string)($_POST['open_time']??'10:00'));
    $close=trim((string)($_POST['close_time']??'22:00'));
    $settings=[
        'salon_name'=>trim((string)($_POST['salon_name']??'Oh My Gracious')),
        'open_time'=>$open,
        'close_time'=>$close,
        'slot_interval_minutes'=>(string)max(15,(int)($_POST['slot_interval_minutes']??30)),
        'booking_days_ahead'=>(string)max(1,min(365,(int)($_POST['booking_days_ahead']??90))),
    ];
    if($settings['salon_name']==='')$settings['salon_name']='Oh My Gracious';
    if(!preg_match('/^\\d{2}:\\d{2}$/',$open)||!preg_match('/^\\d{2}:\\d{2}$/',$close)||$open>=$close){
        $message='Opening time must be earlier than closing time.';
    }else{
        foreach($settings as $k=>$v)setSetting($conn,$k,$v,(int)$_SESSION['user_id']);
        audit($conn,'system_settings_updated','settings',null,array_keys($settings));
        $message='Salon settings updated.';
    }
}
$keys=['salon_name'=>'Oh My Gracious','open_time'=>'10:00','close_time'=>'22:00','slot_interval_minutes'=>'30','booking_days_ahead'=>'90'];
foreach($keys as $k=>$d)$keys[$k]=getSetting($conn,$k,$d);
$paymongoConfigured=paymongoEnabled();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Salon Settings | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Owner Console</div><nav><a href="super-admin-dashboard.php">Dashboard</a><a href="revenue-reports.php">Revenue</a><a href="services-management.php">Services</a><a href="technician-management.php">Technicians</a><a href="admin-management.php">Staff Admins</a><a class="active" href="system-settings.php">Settings</a><a href="audit-logs.php">Activity</a><a href="logout.php" class="logout-btn">Logout</a></nav></header>
<main class="page-wrap narrow"><div class="page-heading"><div><p class="eyebrow">BUSINESS CONFIGURATION</p><h1>Salon Settings</h1><p>Booking rules are stored in the database instead of being hard-coded in the website.</p></div></div>
<?php if($message):?><div class="alert <?=str_contains($message,'updated')?'success':'error'?>"><?=h($message)?></div><?php endif;?>
<section class="panel"><h2>Scheduling</h2><form method="POST" class="form-grid two"><?=csrfInput()?><div class="field span-2"><label>Salon Name</label><input name="salon_name" maxlength="120" value="<?=h($keys['salon_name'])?>"></div><div class="field"><label>Opening Time</label><input type="time" name="open_time" value="<?=h($keys['open_time'])?>" required></div><div class="field"><label>Closing Time</label><input type="time" name="close_time" value="<?=h($keys['close_time'])?>" required></div><div class="field"><label>Booking Slot Interval</label><select name="slot_interval_minutes"><?php foreach([15,30,60] as $v):?><option value="<?=$v?>" <?=$keys['slot_interval_minutes']===(string)$v?'selected':''?>><?=$v?> minutes</option><?php endforeach;?></select></div><div class="field"><label>Maximum Days Ahead</label><input type="number" min="1" max="365" name="booking_days_ahead" value="<?=h($keys['booking_days_ahead'])?>"></div><div class="span-2"><button class="btn primary">Save Settings</button></div></form></section>
<section class="panel"><h2>Online Payment Gateway</h2><p><strong>PayMongo:</strong> <?=$paymongoConfigured?'✅ Configured':'○ Not configured'?></p><p class="muted">PayMongo credentials are intentionally stored only in <code>.env</code>, not in this dashboard. This prevents the secret key and webhook secret from being exposed through the website interface.</p><?php if(!$paymongoConfigured):?><div class="alert info">Add <code>PAYMONGO_SECRET_KEY</code> and <code>PAYMONGO_WEBHOOK_SECRET</code> to your <code>.env</code>, then configure the public HTTPS webhook URL <code>/webhooks/paymongo.php</code> in PayMongo.</div><?php endif;?></section>
</main></body></html>
