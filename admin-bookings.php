<?php
require_once __DIR__.'/backend/authorization.php';require_once __DIR__.'/backend/db.php';require_once __DIR__.'/backend/csrf.php';requireRegularAdmin();
$status=trim((string)($_GET['status']??''));$date=trim((string)($_GET['date']??''));$where=[];$params=[];$types='';
$allowedStatuses=['Pending','Approved','Checked-in','Completed','Rejected','Cancelled','No-show'];
if($status!==''&&in_array($status,$allowedStatuses,true)){$where[]='b.status=?';$params[]=$status;$types.='s';}
if($date!==''){$where[]='b.booking_date=?';$params[]=$date;$types.='s';}
$sql="SELECT b.*,p.gateway p_gateway,p.method p_method,p.status p_status,p.reference_number,GROUP_CONCAT(CONCAT(bi.service_name,IF(bi.quantity>1,CONCAT(' x',bi.quantity),'')) ORDER BY bi.id SEPARATOR ', ') services FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id LEFT JOIN booking_items bi ON bi.booking_id=b.id".($where?' WHERE '.implode(' AND ',$where):'')." GROUP BY b.id ORDER BY b.booking_date DESC,b.booking_time DESC";
$stmt=$conn->prepare($sql);if($params)$stmt->bind_param($types,...$params);$stmt->execute();$bookings=$stmt->get_result();
function actionForm($id,$action,$label,$class,$confirm=''){return '<form class="inline-form" method="POST" action="backend/update-booking-status.php"'.($confirm?' onsubmit="return confirm(\''.h($confirm).'\')"':'').'>'.csrfInput().'<input type="hidden" name="booking_id" value="'.(int)$id.'"><input type="hidden" name="action" value="'.h($action).'"><button type="submit" class="btn '.h($class).' tiny">'.h($label).'</button></form>';}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Appointments | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Front Desk</div><nav><a href="admin-dashboard.php">Dashboard</a><a class="active" href="admin-bookings.php">Appointments</a><a href="availability-management.php">Availability</a><a href="customers.php">Customers</a><a href="logout.php" class="logout-btn">Logout</a></nav></header>
<main class="page-wrap"><div class="page-heading"><div><p class="eyebrow">APPOINTMENT OPERATIONS</p><h1>Manage Appointments</h1><p>Front desk handles booking approval, rescheduling, check-in, completion, no-shows, cancellations, and in-salon payment records.</p></div></div>
<?php if(!empty($_GET['error'])):?><div class="alert error"><?=h((string)$_GET['error'])?></div><?php endif;?><?php if(isset($_GET['updated'])):?><div class="alert success">Appointment updated.</div><?php endif;?>
<form class="filter-bar" method="GET"><select name="status"><option value="">All statuses</option><?php foreach($allowedStatuses as $s):?><option <?=$status===$s?'selected':''?>><?=h($s)?></option><?php endforeach;?></select><input type="date" name="date" value="<?=h($date)?>"><button class="btn secondary small">Filter</button><a class="btn ghost small" href="admin-bookings.php">Clear</a></form>
<div class="table-card"><div class="table-scroll"><table><thead><tr><th>Customer</th><th>Services</th><th>Schedule</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if(!$bookings->num_rows):?><tr><td colspan="7" class="empty-cell">No matching appointments.</td></tr><?php endif;?>
<?php while($b=$bookings->fetch_assoc()):$pstatus=$b['p_status']?:$b['payment_status'];$pmethod=$b['p_method']?:$b['payment_method'];?><tr>
<td><strong><?=h($b['full_name'])?></strong><br><small><?=h($b['phone_number']?:$b['email'])?></small><br><span class="muted"><?=h(ucwords(str_replace('_',' ',$b['booking_source']??'customer')))?></span></td>
<td><?=h($b['services']?:$b['service_type'])?><?php if((int)$b['total_duration_minutes']>0):?><br><small>Est. ~<?= (int)$b['total_duration_minutes']?> min</small><?php endif;?></td>
<td><?=h(date('M j, Y',strtotime($b['booking_date'])))?><br><small><?=h(date('g:i A',strtotime($b['booking_time'])))?></small></td>
<td><?= !empty($b['amount_is_estimate'])?'From ':''?>₱<?=number_format((float)$b['amount'],2)?></td>
<td><strong><?=h($pmethod)?></strong><br><span class="status neutral"><?=h($pstatus)?></span></td>
<td><span class="status neutral"><?=h($b['status'])?></span></td>
<td><div class="action-stack">
<?php if(in_array($b['status'],['Pending','Approved'],true)):?><a class="btn ghost tiny" href="admin-reschedule.php?id=<?= (int)$b['id']?>">Reschedule</a><?php endif;?>
<?php if($b['status']==='Pending'):?><?=actionForm($b['id'],'approve','Approve','approve')?><?=actionForm($b['id'],'reject','Reject','danger','Reject this booking?')?>
<?php elseif($b['status']==='Approved'):?><?=actionForm($b['id'],'check_in','Check In','primary')?><?=actionForm($b['id'],'no_show','No-show','danger','Mark this customer as a no-show?')?><?=actionForm($b['id'],'cancel','Cancel','danger','Cancel this appointment?')?>
<?php elseif($b['status']==='Checked-in'):?><?=actionForm($b['id'],'complete','Complete','primary')?><?php endif;?>
<?php if($b['p_gateway']==='manual'&&$pstatus!=='Paid'&&in_array($b['status'],['Approved','Checked-in','Completed'],true)):?><?=actionForm($b['id'],'mark_paid','Mark Paid','approve','Confirm payment was received at the salon?')?><?php endif;?>
<?php if($pstatus==='Paid'):?><a class="btn ghost tiny" href="receipt.php?id=<?= (int)$b['id']?>">Receipt</a><?php endif;?>
</div></td></tr><?php endwhile;?></tbody></table></div></div></main></body></html>
