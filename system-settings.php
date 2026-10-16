<?php
require_once __DIR__.'/backend/authorization.php';require_once __DIR__.'/backend/db.php';require_once __DIR__.'/backend/csrf.php';require_once __DIR__.'/backend/booking-helper.php';requireSuperAdmin();
$message='';$type='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();$action=(string)($_POST['action']??'settings');
    try{
        if($action==='settings'){
            $settings=[
                'salon_name'=>trim((string)($_POST['salon_name']??'Oh My Gracious')),
                'booking_days_ahead'=>(string)max(1,min(365,(int)($_POST['booking_days_ahead']??90))),
                'default_slot_capacity'=>(string)max(1,min(50,(int)($_POST['default_slot_capacity']??5))),
            ];
            if($settings['salon_name']==='')$settings['salon_name']='Oh My Gracious';
            foreach($settings as $k=>$v)setSetting($conn,$k,$v,(int)$_SESSION['user_id']);
            $newDefault=(int)$settings['default_slot_capacity'];
            $u=$conn->prepare('UPDATE booking_time_slots SET default_capacity=?');$u->bind_param('i',$newDefault);$u->execute();
            audit($conn,'system_settings_updated','settings',null,array_keys($settings));$message='Salon booking settings updated.';
        }elseif($action==='slot_template'){
            $id=(int)($_POST['slot_id']??0);$capacity=max(1,min(50,(int)($_POST['capacity']??5)));$status=in_array($_POST['status']??'active',['active','inactive'],true)?$_POST['status']:'active';
            $s=$conn->prepare('UPDATE booking_time_slots SET default_capacity=?,status=? WHERE id=?');$s->bind_param('isi',$capacity,$status,$id);$s->execute();audit($conn,'slot_template_updated','booking_time_slot',$id,['capacity'=>$capacity,'status'=>$status]);$message='Default slot updated.';
        }
    }catch(Throwable $e){$message='Settings could not be saved.';$type='error';error_log($e->getMessage());}
}
$salonName=getSetting($conn,'salon_name','Oh My Gracious');$days=getSetting($conn,'booking_days_ahead','90');$defaultCapacity=getSetting($conn,'default_slot_capacity','5');$slots=bookingSlotTemplates($conn,false);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Salon Settings | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Owner Console</div><nav><a href="super-admin-dashboard.php">Dashboard</a><a href="revenue-reports.php">Revenue</a><a href="services-management.php">Services</a><a href="technician-management.php">Technicians</a><a href="admin-management.php">Staff Admins</a><a class="active" href="system-settings.php">Settings</a><a href="audit-logs.php">Activity</a><a href="logout.php" class="logout-btn">Logout</a></nav></header>
<main class="page-wrap narrow"><div class="page-heading"><div><p class="eyebrow">BUSINESS CONFIGURATION</p><h1>Salon Settings</h1><p>The scheduler uses fixed times and customer capacity. Estimated service duration is informational and does not block consecutive hours.</p></div></div>
<?php if($message):?><div class="alert <?=$type?>"><?=h($message)?></div><?php endif;?>
<section class="panel"><h2>General Booking Rules</h2><form method="POST" class="form-grid two"><?=csrfInput()?><input type="hidden" name="action" value="settings"><div class="field span-2"><label>Salon Name</label><input name="salon_name" maxlength="120" value="<?=h($salonName)?>"></div><div class="field"><label>Maximum Days Ahead</label><input type="number" min="1" max="365" name="booking_days_ahead" value="<?=h($days)?>"></div><div class="field"><label>Default Customers per Time Slot</label><input type="number" min="1" max="50" name="default_slot_capacity" value="<?=h($defaultCapacity)?>"><small>Front desk can override a specific date/time without changing this default.</small></div><div class="span-2"><button class="btn primary">Save Settings</button></div></form></section>
<section class="panel"><h2>Fixed Booking Times</h2><p class="muted">Current model: AM 9:00–11:00, PM 1:00–7:00. Owner controls the default capacity and whether each time is generally active.</p><div class="table-scroll"><table><thead><tr><th>Time</th><th>Period</th><th>Default Capacity</th><th>Status</th><th>Save</th></tr></thead><tbody><?php foreach($slots as $slot):$fid='template-'.$slot['id'];?><tr><td><strong><?=h(date('g:i A',strtotime($slot['slot_time'])))?></strong></td><td><?=h($slot['period'])?></td><td><input form="<?=$fid?>" name="capacity" type="number" min="1" max="50" value="<?= (int)$slot['default_capacity']?>"></td><td><select form="<?=$fid?>" name="status"><option value="active" <?=$slot['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$slot['status']==='inactive'?'selected':''?>>Inactive</option></select></td><td><form id="<?=$fid?>" method="POST"><?=csrfInput()?><input type="hidden" name="action" value="slot_template"><input type="hidden" name="slot_id" value="<?= (int)$slot['id']?>"><button class="btn secondary tiny">Save</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="panel"><h2>Payment Integration</h2><div class="alert info">Online payment is intentionally not integrated in this release. The operational system should be tested and accepted first. Current bookings use <strong>Pay at Salon</strong>, and front desk can record payment as Paid.</div></section>
<section class="panel"><h2>Google Sign-In</h2><p><strong>Status:</strong> <?=GOOGLE_CLIENT_ID!==''?'✅ Configured':'○ Not configured'?></p><p class="muted">The Google Client ID is stored in <code>.env</code>, never in this dashboard or GitHub.</p></section>
</main></body></html>
