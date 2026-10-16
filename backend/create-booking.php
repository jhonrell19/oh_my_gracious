<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/booking-helper.php';
requireCustomer();
verifyCsrf();

$userId=(int)$_SESSION['user_id'];
$date=trim((string)($_POST['booking_date']??''));
$time=normalizeSlotTime((string)($_POST['booking_time']??''));
$serviceIds=$_POST['service_ids']??[];
$notes=trim((string)($_POST['notes']??''));
$designIdRaw=trim((string)($_POST['selected_design_id']??''));
if(!is_array($serviceIds)||!$serviceIds||$date===''||$time==='')redirectTo('booking.php?error='.urlencode('Complete all booking fields.'));
if(!validBookingDate($conn,$date))redirectTo('booking.php?error='.urlencode('Choose a valid booking date.'));

$stmt=$conn->prepare('SELECT full_name,email,phone_number FROM users WHERE id=? AND role="user" AND account_status="active"');$stmt->bind_param('i',$userId);$stmt->execute();$profile=$stmt->get_result()->fetch_assoc();
if(!$profile)redirectTo('logout.php');
if(empty($profile['phone_number']))redirectTo('account.php?complete=1&error='.urlencode('Add a contact number before booking.'));

$designId=null;
if($designIdRaw!==''&&ctype_digit($designIdRaw)){$d=(int)$designIdRaw;$s=$conn->prepare('SELECT id FROM saved_designs WHERE id=? AND user_id=?');$s->bind_param('ii',$d,$userId);$s->execute();if($s->get_result()->num_rows===1)$designId=$d;}

$status='Pending';$paymentMethod='Cash';$paymentStatus='To Pay';$reference='';$source='customer';
try{
    $conn->begin_transaction();
    $services=loadServicesByIds($conn,$serviceIds,true);
    if(count($services)!==count(array_unique(array_map('intval',$serviceIds)))||!selectedServicesAreBookable($services))throw new RuntimeException('One or more selected services are unavailable.');
    $totals=bookingTotals($services);
    $summary=implode(', ',array_column($services,'service_name'));
    $b=$conn->prepare('INSERT INTO bookings(user_id,full_name,email,phone_number,service_type,booking_date,booking_time,total_duration_minutes,amount,payment_method,payment_status,payment_reference,notes,selected_design_id,status,booking_source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $duration=(int)$totals['duration'];$amount=(float)$totals['amount'];
    $b->bind_param('issssssidssssiss',$userId,$profile['full_name'],$profile['email'],$profile['phone_number'],$summary,$date,$time,$duration,$amount,$paymentMethod,$paymentStatus,$reference,$notes,$designId,$status,$source);
    $b->execute();$bookingId=(int)$conn->insert_id;
    foreach($services as $service){$i=$conn->prepare('INSERT INTO booking_items(booking_id,service_id,service_name,unit_price,duration_minutes) VALUES (?,?,?,?,?)');$sid=(int)$service['id'];$price=(float)$service['price'];$dur=(int)$service['duration_minutes'];$i->bind_param('iisdi',$bookingId,$sid,$service['service_name'],$price,$dur);$i->execute();}
    reserveCapacitySlot($conn,$date,$time,$bookingId);
    $p=$conn->prepare("INSERT INTO payments(booking_id,method,status,amount,reference_number,gateway) VALUES (?,'Cash','To Pay',?,?,'manual')");$p->bind_param('ids',$bookingId,$amount,$reference);$p->execute();
    audit($conn,'booking_created','booking',$bookingId,['services'=>$summary,'date'=>$date,'time'=>$time,'source'=>$source]);
    $conn->commit();redirectTo('booking-history.php?created=1');
}catch(Throwable $e){
    try{$conn->rollback();}catch(Throwable $ignored){}
    error_log('Booking create: '.$e->getMessage());
    $message=str_contains(strtolower($e->getMessage()),'fully booked')||str_contains(strtolower($e->getMessage()),'closed')?$e->getMessage():'Booking could not be created.';
    redirectTo('booking.php?date='.urlencode($date).'&error='.urlencode($message));
}
