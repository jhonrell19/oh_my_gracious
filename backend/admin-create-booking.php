<?php

declare(strict_types=1);

require_once __DIR__.'/authorization.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/booking-helper.php';
requireRegularAdmin();
verifyCsrf();

$adminId=(int)$_SESSION['user_id'];
$userId=(int)($_POST['user_id']??0);
$date=trim((string)($_POST['booking_date']??''));
$time=trim((string)($_POST['booking_time']??''));
$serviceIds=$_POST['service_ids']??[];
$notes=trim((string)($_POST['notes']??''));
$method=trim((string)($_POST['payment_method']??'Cash'));
$reference='';

if($userId<=0||!is_array($serviceIds)||!$serviceIds||$date===''||$time===''){
    redirectTo('admin-create-booking.php?error='.urlencode('Complete all required fields.'));
}
if(!validBookingDate($conn,$date)){
    redirectTo('admin-create-booking.php?error='.urlencode('Choose a valid booking date.'));
}

$profileStmt=$conn->prepare("SELECT id,full_name,email FROM users WHERE id=? AND role='user' AND account_status='active'");
$profileStmt->bind_param('i',$userId);
$profileStmt->execute();
$profile=$profileStmt->get_result()->fetch_assoc();
if(!$profile){
    redirectTo('admin-create-booking.php?error='.urlencode('Customer account is unavailable.'));
}

$services=loadServicesByIds($conn,$serviceIds);
if(count($services)!==count(array_unique(array_map('intval',$serviceIds)))){
    redirectTo('admin-create-booking.php?error='.urlencode('Invalid service selection.'));
}
$totals=bookingTotals($services);
$available=array_column(candidateStartTimes($conn,$date,$totals['duration']),'value');
if(!in_array(substr($time,0,5),$available,true)){
    redirectTo('admin-create-booking.php?error='.urlencode('Time slot unavailable.'));
}

if(!in_array($method,['Cash','Card'],true))$method='Cash';
$paymentStatus='To Pay';
$summary=implode(', ',array_column($services,'service_name'));
$interval=max(15,(int)getSetting($conn,'slot_interval_minutes','30'));
$slots=requiredSlotTimes($time,$totals['duration'],$interval);
$status='Approved';
$gateway='manual';

try{
    $conn->begin_transaction();

    $b=$conn->prepare('INSERT INTO bookings(user_id,full_name,email,service_type,booking_date,booking_time,total_duration_minutes,amount,payment_method,payment_status,payment_reference,notes,status,approved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $b->bind_param('isssssidsssssi',$userId,$profile['full_name'],$profile['email'],$summary,$date,$time,$totals['duration'],$totals['amount'],$method,$paymentStatus,$reference,$notes,$status,$adminId);
    $b->execute();
    $bid=(int)$conn->insert_id;

    foreach($services as $service){
        $i=$conn->prepare('INSERT INTO booking_items(booking_id,service_id,service_name,unit_price,duration_minutes) VALUES (?,?,?,?,?)');
        $sid=(int)$service['id'];$price=(float)$service['price'];$dur=(int)$service['duration_minutes'];
        $i->bind_param('iisdi',$bid,$sid,$service['service_name'],$price,$dur);
        $i->execute();
    }

    foreach($slots as $slot){
        $s=$conn->prepare('INSERT INTO booking_slots(booking_id,slot_date,slot_time) VALUES (?,?,?)');
        $s->bind_param('iss',$bid,$date,$slot);
        $s->execute();
    }

    $p=$conn->prepare('INSERT INTO payments(booking_id,method,status,amount,reference_number,gateway) VALUES (?,?,?,?,?,?)');
    $p->bind_param('issdss',$bid,$method,$paymentStatus,$totals['amount'],$reference,$gateway);
    $p->execute();

    audit($conn,'frontdesk_booking_created','booking',$bid);
    $conn->commit();
    redirectTo('admin-bookings.php?updated=1');
}catch(mysqli_sql_exception $e){
    try{$conn->rollback();}catch(Throwable $ignored){}
    redirectTo('admin-create-booking.php?error='.urlencode((int)$e->getCode()===1062?'That time was just booked.':'Booking could not be created.'));
}
