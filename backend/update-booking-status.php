<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';
requireRegularAdmin();
verifyCsrf();

$id=(int)($_POST['booking_id']??0);
$action=(string)($_POST['action']??'');
$allowed=['approve','reject','check_in','complete','mark_paid','verify_payment','reject_payment'];
if($id<=0||!in_array($action,$allowed,true))redirectTo('admin-bookings.php?error='.urlencode('Invalid action.'));

try{
    $conn->begin_transaction();
    $s=$conn->prepare("SELECT b.id,b.status,b.payment_method,b.payment_status,p.gateway FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id WHERE b.id=? FOR UPDATE");
    $s->bind_param('i',$id);$s->execute();$b=$s->get_result()->fetch_assoc();
    if(!$b){$conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('Booking not found.'));}
    $uid=(int)$_SESSION['user_id'];

    if(in_array($action,['reject','reject_payment','mark_paid','verify_payment'],true)&&$b['payment_status']==='Paid'){
        $conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('Paid transactions cannot be changed from the front desk. Handle any refund through the owner/payment provider process.'));
    }
    if(in_array($action,['mark_paid','verify_payment','reject_payment'],true)&&($b['gateway']??'manual')==='paymongo'){
        $conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('PayMongo payment status is controlled by the verified PayMongo webhook, not by manual staff actions.'));
    }

    switch($action){
        case 'approve':
            $u=$conn->prepare("UPDATE bookings SET status='Approved',approved_by=? WHERE id=? AND status='Pending'");$u->bind_param('ii',$uid,$id);break;
        case 'reject':
            $u=$conn->prepare("UPDATE bookings SET status='Rejected' WHERE id=? AND status IN ('Pending','Approved')");$u->bind_param('i',$id);break;
        case 'check_in':
            $u=$conn->prepare("UPDATE bookings SET status='Checked-in',checked_in_by=?,check_in_at=NOW() WHERE id=? AND status='Approved'");$u->bind_param('ii',$uid,$id);break;
        case 'complete':
            $u=$conn->prepare("UPDATE bookings SET status='Completed',completed_by=?,completed_at=NOW() WHERE id=? AND status='Checked-in'");$u->bind_param('ii',$uid,$id);break;
        case 'mark_paid':
            $u=$conn->prepare("UPDATE bookings SET payment_status='Paid' WHERE id=?");$u->bind_param('i',$id);break;
        case 'verify_payment':
            $u=$conn->prepare("UPDATE bookings SET payment_status='Paid' WHERE id=? AND payment_status='Pending Verification'");$u->bind_param('i',$id);break;
        case 'reject_payment':
            $u=$conn->prepare("UPDATE bookings SET payment_status='Rejected' WHERE id=? AND payment_status='Pending Verification'");$u->bind_param('i',$id);break;
    }
    $u->execute();

    if(in_array($action,['reject','complete'],true)){
        $d=$conn->prepare('DELETE FROM booking_slots WHERE booking_id=?');$d->bind_param('i',$id);$d->execute();
    }
    if($action==='reject'){
        $p=$conn->prepare("UPDATE payments SET status=CASE WHEN status IN ('Pending Verification','Pending Online Payment') THEN 'Cancelled' ELSE status END WHERE booking_id=?");
        $p->bind_param('i',$id);$p->execute();
        $conn->query("UPDATE bookings SET payment_status=CASE WHEN payment_status IN ('Pending Verification','Pending Online Payment') THEN 'Cancelled' ELSE payment_status END WHERE id=".(int)$id);
    }
    if(in_array($action,['mark_paid','verify_payment'],true)){
        $p=$conn->prepare("UPDATE payments SET status='Paid',paid_at=NOW(),verified_by=? WHERE booking_id=?");$p->bind_param('ii',$uid,$id);$p->execute();
    }
    if($action==='reject_payment'){
        $p=$conn->prepare("UPDATE payments SET status='Rejected',verified_by=? WHERE booking_id=?");$p->bind_param('ii',$uid,$id);$p->execute();
    }

    audit($conn,'booking_'.$action,'booking',$id);
    $conn->commit();
    redirectTo('admin-bookings.php?updated=1');
}catch(Throwable $e){
    try{$conn->rollback();}catch(Throwable $ignored){}
    error_log('Booking action: '.$e->getMessage());
    redirectTo('admin-bookings.php?error='.urlencode('Update failed.'));
}
