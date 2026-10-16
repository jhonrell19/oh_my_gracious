<?php

declare(strict_types=1);
require_once __DIR__.'/authorization.php';require_once __DIR__.'/csrf.php';require_once __DIR__.'/booking-helper.php';requireCustomer();verifyCsrf();
$id=(int)($_POST['booking_id']??0);$userId=(int)$_SESSION['user_id'];
try{
    $conn->begin_transaction();$stmt=$conn->prepare('SELECT id,status,booking_date,payment_status FROM bookings WHERE id=? AND user_id=? FOR UPDATE');$stmt->bind_param('ii',$id,$userId);$stmt->execute();$b=$stmt->get_result()->fetch_assoc();
    if(!$b||!in_array($b['status'],['Pending','Approved'],true)||$b['booking_date']<date('Y-m-d')){throw new RuntimeException('This booking cannot be cancelled.');}
    if($b['payment_status']==='Paid')throw new RuntimeException('A paid booking must be handled by salon staff.');
    $u=$conn->prepare("UPDATE bookings SET status='Cancelled',payment_status='Cancelled' WHERE id=?");$u->bind_param('i',$id);$u->execute();releaseBookingCapacity($conn,$id);$p=$conn->prepare("UPDATE payments SET status='Cancelled' WHERE booking_id=? AND status<>'Paid'");$p->bind_param('i',$id);$p->execute();audit($conn,'booking_cancelled','booking',$id);$conn->commit();redirectTo('booking-history.php?cancelled=1');
}catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}redirectTo('booking-history.php?error='.urlencode($e->getMessage()));}
