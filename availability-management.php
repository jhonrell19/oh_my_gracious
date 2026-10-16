<?php
require_once __DIR__.'/backend/authorization.php';
require_once __DIR__.'/backend/db.php';
require_once __DIR__.'/backend/csrf.php';
require_once __DIR__.'/backend/booking-helper.php';
requireRegularAdmin();

$message='';$type='success';
$selectedDate=trim((string)($_GET['date']??$_POST['date']??date('Y-m-d')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$selectedDate))$selectedDate=date('Y-m-d');

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();$action=(string)($_POST['action']??'');
    try{
        if($action==='service_availability'){
            $id=(int)($_POST['service_id']??0);$availability=(string)($_POST['booking_availability']??'available');$reason=trim((string)($_POST['unavailable_reason']??''));
            if($id<=0||!in_array($availability,['available','unavailable'],true))throw new RuntimeException('Invalid service availability update.');
            if($availability==='available')$reason='';
            $s=$conn->prepare('UPDATE services SET booking_availability=?,unavailable_reason=? WHERE id=? AND status="active"');$s->bind_param('ssi',$availability,$reason,$id);$s->execute();
            audit($conn,'service_booking_availability_'.$availability,'service',$id,['reason'=>$reason]);$message='Service availability updated.';
        }elseif($action==='slot_override'){
            $time=normalizeSlotTime((string)($_POST['slot_time']??''));$status=(string)($_POST['slot_status']??'open');$reason=trim((string)($_POST['reason']??''));$capacity=max(1,min(50,(int)($_POST['capacity']??1)));
            if($time===''||!in_array($status,['open','closed'],true))throw new RuntimeException('Invalid time-slot update.');
            $conn->begin_transaction();
            ensureInventoryRowForUpdate($conn,$selectedDate,$time);
            $current=$conn->prepare('SELECT COUNT(*) total FROM bookings WHERE booking_date=? AND booking_time=? AND status IN ("Pending","Approved","Checked-in")');$current->bind_param('ss',$selectedDate,$time);$current->execute();$reserved=(int)$current->get_result()->fetch_assoc()['total'];
            if($status==='open'&&$capacity<$reserved)throw new RuntimeException('Capacity cannot be lower than the '.$reserved.' active booking(s) already in this slot.');
            $u=$conn->prepare('UPDATE booking_slot_inventory SET capacity=?,status=?,reason=?,reserved_count=?,updated_by=? WHERE slot_date=? AND slot_time=?');
            $uid=(int)$_SESSION['user_id'];$u->bind_param('issiiss',$capacity,$status,$reason,$reserved,$uid,$selectedDate,$time);$u->execute();
            audit($conn,'booking_slot_override','slot',null,['date'=>$selectedDate,'time'=>$time,'capacity'=>$capacity,'status'=>$status,'reason'=>$reason]);$conn->commit();$message='Daily time-slot availability updated.';
        }
    }catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}$message=$e->getMessage();$type='error';}
}
$services=$conn->query("SELECT id,service_name,category,booking_availability,unavailable_reason FROM services WHERE status='active' ORDER BY category,display_order,service_name");
$slots=effectiveSlotRows($conn,$selectedDate);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Availability | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Front Desk</div><nav><a href="admin-dashboard.php">Dashboard</a><a href="admin-bookings.php">Appointments</a><a href="admin-create-booking.php">Create Booking</a><a class="active" href="availability-management.php">Availability</a><a href="customers.php">Customers</a><a href="logout.php" class="logout-btn">Logout</a></nav></header>
<main class="page-wrap"><div class="page-heading"><div><p class="eyebrow">OPERATIONS CONTROL</p><h1>Service & Time Availability</h1><p>Temporarily close services when staff are unavailable, and close or limit individual booking times for a selected date.</p></div></div>
<?php if($message):?><div class="alert <?=$type?>"><?=h($message)?></div><?php endif;?>
<section class="panel"><div class="section-heading"><div><h2>Service Availability</h2><p>This does not delete or change owner pricing. It only controls whether customers can currently book the service.</p></div></div><div class="table-scroll"><table><thead><tr><th>Category</th><th>Service</th><th>Booking Status</th><th>Reason</th><th>Save</th></tr></thead><tbody><?php while($s=$services->fetch_assoc()):$fid='svc-'.$s['id'];?><tr><td><?=h($s['category'])?></td><td><strong><?=h($s['service_name'])?></strong></td><td><select form="<?=$fid?>" name="booking_availability"><option value="available" <?=$s['booking_availability']==='available'?'selected':''?>>Available</option><option value="unavailable" <?=$s['booking_availability']==='unavailable'?'selected':''?>>Unavailable</option></select></td><td><input form="<?=$fid?>" name="unavailable_reason" value="<?=h($s['unavailable_reason'])?>" placeholder="e.g. Stylist unavailable"></td><td><form id="<?=$fid?>" method="POST"><?=csrfInput()?><input type="hidden" name="action" value="service_availability"><input type="hidden" name="service_id" value="<?= (int)$s['id']?>"><input type="hidden" name="date" value="<?=h($selectedDate)?>"><button class="btn secondary tiny">Save</button></form></td></tr><?php endwhile;?></tbody></table></div></section>
<section class="panel"><div class="section-heading"><div><h2>Daily Time Slots</h2><p>Use this for staff shortages, events, equipment issues, or unusually busy days.</p></div><form method="GET" class="inline-form"><input type="date" name="date" value="<?=h($selectedDate)?>"><button class="btn ghost small">Load Date</button></form></div><div class="table-scroll"><table><thead><tr><th>Time</th><th>Period</th><th>Booked</th><th>Capacity</th><th>Status</th><th>Reason</th><th>Save</th></tr></thead><tbody><?php foreach($slots as $slot):$fid='slot-'.str_replace(':','-',substr($slot['slot_time'],0,5));?><tr><td><strong><?=h(date('g:i A',strtotime($slot['slot_time'])))?></strong></td><td><?=h($slot['period'])?></td><td><?= (int)$slot['reserved_count']?> / <?= (int)$slot['capacity']?></td><td><input form="<?=$fid?>" type="number" name="capacity" min="1" max="50" value="<?= (int)$slot['capacity']?>"></td><td><select form="<?=$fid?>" name="slot_status"><option value="open" <?=$slot['slot_status']==='open'?'selected':''?>>Open</option><option value="closed" <?=$slot['slot_status']==='closed'?'selected':''?>>Closed</option></select></td><td><input form="<?=$fid?>" name="reason" value="<?=h($slot['reason'])?>" placeholder="Optional reason"></td><td><form id="<?=$fid?>" method="POST"><?=csrfInput()?><input type="hidden" name="action" value="slot_override"><input type="hidden" name="date" value="<?=h($selectedDate)?>"><input type="hidden" name="slot_time" value="<?=h($slot['slot_time'])?>"><button class="btn secondary tiny">Save</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
</main></body></html>
