<?php

declare(strict_types=1);
require_once __DIR__.'/authorization.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/booking-helper.php';
requireRegularAdmin();verifyCsrf();

$adminId=(int)$_SESSION['user_id'];
$mode=($_POST['customer_mode']??'existing')==='guest'?'guest':'existing';
$userId=(int)($_POST['user_id']??0);
$date=trim((string)($_POST['booking_date']??''));$time=normalizeSlotTime((string)($_POST['booking_time']??''));$serviceIds=$_POST['service_ids']??[];$notes=trim((string)($_POST['notes']??''));
if(!is_array($serviceIds)||!$serviceIds||$date===''||$time==='')redirectTo('admin-create-booking.php?error='.urlencode('Complete all required fields.'));
if(!validBookingDate($conn,$date))redirectTo('admin-create-booking.php?error='.urlencode('Choose a valid booking date.'));

$bookingUserId=null;$fullName='';$email='';$phone='';$source='frontdesk';
if($mode==='existing'){
    if($userId<=0)redirectTo('admin-create-booking.php?error='.urlencode('Select a customer account.'));
    $p=$conn->prepare("SELECT id,full_name,email,phone_number FROM users WHERE id=? AND role='user' AND account_status='active'");$p->bind_param('i',$userId);$p->execute();$profile=$p->get_result()->fetch_assoc();if(!$profile)redirectTo('admin-create-booking.php?error='.urlencode('Customer account is unavailable.'));
    $bookingUserId=(int)$profile['id'];$fullName=(string)$profile['full_name'];$email=(string)($profile['email']??'');$phone=(string)($profile['phone_number']??'');
}else{
    $source='walk_in';$fullName=trim((string)($_POST['guest_name']??''));$email=strtolower(trim((string)($_POST['guest_email']??'')));$normalized=normalizePhilippinePhone((string)($_POST['guest_phone']??''));
    if($fullName===''||$normalized===null)redirectTo('admin-create-booking.php?error='.urlencode('Guest name and a valid Philippine contact number are required.'));
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))redirectTo('admin-create-booking.php?error='.urlencode('Guest email is invalid.'));
    $phone=$normalized;
}
$paymentStatus='To Pay';$method='Cash';$reference='';$status='Approved';
try{
    $conn->begin_transaction();
    $services=loadServicesByIds($conn,$serviceIds,true);
    if(count($services)!==count(array_unique(array_map('intval',$serviceIds)))||!selectedServicesAreBookable($services))throw new RuntimeException('One or more selected services are unavailable.');
    $totals=bookingTotals($services);$summary=implode(', ',array_column($services,'service_name'));
    $b=$conn->prepare('INSERT INTO bookings(user_id,full_name,email,phone_number,service_type,booking_date,booking_time,total_duration_minutes,amount,payment_method,payment_status,payment_reference,notes,status,approved_by,booking_source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $duration=(int)$totals['duration'];$amount=(float)$totals['amount'];
    $b->bind_param('issssssidsssssis',$bookingUserId,$fullName,$email,$phone,$summary,$date,$time,$duration,$amount,$method,$paymentStatus,$reference,$notes,$status,$adminId,$source);
    $b->execute();$bid=(int)$conn->insert_id;
    foreach($services as $service){$i=$conn->prepare('INSERT INTO booking_items(booking_id,service_id,service_name,unit_price,duration_minutes) VALUES (?,?,?,?,?)');$sid=(int)$service['id'];$price=(float)$service['price'];$dur=(int)$service['duration_minutes'];$i->bind_param('iisdi',$bid,$sid,$service['service_name'],$price,$dur);$i->execute();}
    reserveCapacitySlot($conn,$date,$time,$bid);
    $p=$conn->prepare("INSERT INTO payments(booking_id,method,status,amount,reference_number,gateway) VALUES (?,'Cash','To Pay',?,?,'manual')");$p->bind_param('ids',$bid,$amount,$reference);$p->execute();
    audit($conn,'frontdesk_booking_created','booking',$bid,['source'=>$source]);$conn->commit();redirectTo('admin-bookings.php?updated=1');
}catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}error_log('Front desk booking: '.$e->getMessage());redirectTo('admin-create-booking.php?error='.urlencode(str_contains(strtolower($e->getMessage()),'fully booked')||str_contains(strtolower($e->getMessage()),'closed')?$e->getMessage():'Booking could not be created.'));}
