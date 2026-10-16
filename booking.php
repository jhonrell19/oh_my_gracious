<?php
require_once __DIR__.'/backend/authorization.php';
require_once __DIR__.'/backend/db.php';
require_once __DIR__.'/backend/csrf.php';
requireCustomer();

$uid=(int)$_SESSION['user_id'];
$profileStmt=$conn->prepare('SELECT phone_number FROM users WHERE id=?');$profileStmt->bind_param('i',$uid);$profileStmt->execute();$profile=$profileStmt->get_result()->fetch_assoc();
$needsProfile=empty($profile['phone_number']);
$services=$conn->query("SELECT id,service_name,category,description,price,duration_minutes,booking_availability,unavailable_reason FROM services WHERE status='active' ORDER BY category,display_order,service_name");
$designStmt=$conn->prepare('SELECT id,design_name,nail_color,nail_shape,nail_finish FROM saved_designs WHERE user_id=? ORDER BY created_at DESC');
$designStmt->bind_param('i',$uid);$designStmt->execute();$designs=$designStmt->get_result();
$days=(int)getSetting($conn,'booking_days_ahead','90');$prefDesign=(int)($_GET['design_id']??0);$selectedDate=(string)($_GET['date']??date('Y-m-d'));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Book Appointment | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Oh My Gracious</div><nav><a href="user-dashboard.php">Dashboard</a><a class="active" href="booking.php">Book</a><a href="booking-history.php">History</a><a href="saved-designs.php">Saved Designs</a><a href="account.php">My Account</a><a href="logout.php" class="logout-btn">Logout</a></nav></header>
<main class="page-wrap narrow"><div class="page-heading"><div><p class="eyebrow">APPOINTMENTS</p><h1>Book your salon visit</h1><p>Select one or more available services, then choose a fixed booking time with remaining salon capacity.</p></div></div>
<?php if(!empty($_GET['error'])):?><div class="alert error"><?=h((string)$_GET['error'])?></div><?php endif;?>
<?php if($needsProfile):?><div class="alert warning"><strong>Complete your contact profile first.</strong> Google-created accounts must add a contact number before booking. <a href="account.php?complete=1">Open My Account</a>.</div><?php else:?>
<form id="bookingForm" action="backend/create-booking.php" method="POST" class="panel stack-lg"><?=csrfInput()?>
<section><div class="section-heading"><div><h2>1. Choose services</h2><p>Unavailable services stay visible so you know what the salon offers, but they cannot be selected until staff re-opens them.</p></div></div><div class="service-grid">
<?php $lastCategory=null; while($s=$services->fetch_assoc()): $available=$s['booking_availability']==='available'; if($lastCategory!==$s['category']): $lastCategory=$s['category'];?><div class="service-category-label"><?=h($lastCategory?:'General')?></div><?php endif;?>
<label class="service-option <?=$available?'':'unavailable'?>"><input class="service-check" type="checkbox" name="service_ids[]" value="<?= (int)$s['id']?>" data-price="<?=h($s['price'])?>" data-duration="<?= (int)$s['duration_minutes']?>" <?=$available?'':'disabled'?>>
<span><strong><?=h($s['service_name'])?></strong><small><?=h($s['description'])?></small><b>₱<?=number_format((float)$s['price'],2)?><?= (int)$s['duration_minutes']>0?' · ~'.(int)$s['duration_minutes'].' min':''?></b><?php if(!$available):?><em>Unavailable<?= $s['unavailable_reason']?' — '.h($s['unavailable_reason']):''?></em><?php endif;?></span></label>
<?php endwhile;?>
</div><div class="booking-summary"><div><span>Estimated service time</span><strong id="durationText">0 min</strong><small>Informational only; actual service time may vary.</small></div><div><span>Estimated total</span><strong id="totalText">₱0.00</strong></div></div></section>
<section><h2>2. Date and booking time</h2><div class="form-grid two"><div class="field"><label>Date</label><input id="bookingDate" name="booking_date" type="date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+'.$days.' days'))?>" value="<?=h($selectedDate)?>" required></div><div class="field"><label>Available Time</label><select id="bookingTime" name="booking_time" required><option value="">Select services and date first</option></select></div></div><p class="field-help">Fixed appointment times use salon capacity instead of strict per-service durations. A time becomes unavailable when it is closed by staff or reaches its configured customer limit.</p></section>
<section><h2>3. Design preference</h2><div class="form-grid two"><div class="field"><label>Saved AR Design</label><select name="selected_design_id"><option value="">No saved design</option><?php while($d=$designs->fetch_assoc()):?><option value="<?= (int)$d['id']?>" <?= $prefDesign===(int)$d['id']?'selected':''?>><?=h($d['design_name'].' — '.$d['nail_shape'].' '.$d['nail_finish'])?></option><?php endwhile;?></select></div><div class="field"><label>Notes</label><textarea name="notes" rows="3" placeholder="Length, colors, special requests, etc."></textarea></div></div></section>
<section><h2>4. Payment</h2><input type="hidden" name="payment_method" value="Cash"><div class="payment-option static-payment"><span><strong>Pay at Salon</strong><small>Online payment integration is intentionally disabled in this pre-payment release. Front desk can record payment after service.</small></span></div></section>
<button class="btn primary full" type="submit">Submit Booking Request</button></form>
<?php endif;?></main>
<?php if(!$needsProfile):?><script>
const checks=[...document.querySelectorAll('.service-check')],dateEl=document.getElementById('bookingDate'),timeEl=document.getElementById('bookingTime'),totalEl=document.getElementById('totalText'),durationEl=document.getElementById('durationText');
function selectedIds(){return checks.filter(c=>c.checked).map(c=>c.value)}
function updateSummary(){let total=0,dur=0;checks.forEach(c=>{if(c.checked){total+=Number(c.dataset.price);dur+=Number(c.dataset.duration)}});totalEl.textContent='₱'+total.toFixed(2);durationEl.textContent=dur>0?'~'+dur+' min':'Varies';loadTimes();}
async function loadTimes(){const ids=selectedIds();timeEl.innerHTML='<option value="">Loading availability…</option>';if(!ids.length||!dateEl.value){timeEl.innerHTML='<option value="">Select services and date first</option>';return;}try{const r=await fetch('backend/available-times.php?date='+encodeURIComponent(dateEl.value)+'&service_ids='+ids.join(','));const d=await r.json();timeEl.innerHTML='';if(d.status!=='success'){timeEl.innerHTML='<option value="">'+(d.message||'Availability unavailable')+'</option>';return;}timeEl.append(new Option('Select booking time',''));d.times.forEach(t=>{const o=new Option(t.label,t.value);o.disabled=!t.available;timeEl.append(o);});}catch(e){timeEl.innerHTML='<option value="">Availability unavailable</option>';}}
checks.forEach(c=>c.addEventListener('change',updateSummary));dateEl.addEventListener('change',loadTimes);document.getElementById('bookingForm').addEventListener('submit',e=>{if(!selectedIds().length){e.preventDefault();alert('Select at least one available service.')}});updateSummary();
</script><?php endif;?></body></html>
