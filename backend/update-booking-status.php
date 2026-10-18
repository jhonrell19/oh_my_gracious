<?php

declare(strict_types=1);
require_once __DIR__.'/authorization.php';require_once __DIR__.'/csrf.php';require_once __DIR__.'/booking-helper.php';requireRegularAdmin();verifyCsrf();
$id=(int)($_POST['booking_id']??0);$action=(string)($_POST['action']??'');$allowed=['approve','reject','check_in','complete','mark_paid','cancel','no_show'];
if($id<=0||!in_array($action,$allowed,true))redirectTo('admin-bookings.php?error='.urlencode('Invalid action.'));
try{
    $conn->begin_transaction();$s=$conn->prepare('SELECT b.id,b.status,b.payment_status FROM bookings b WHERE b.id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$b=$s->get_result()->fetch_assoc();if(!$b){$conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('Booking not found.'));}
    if ($action === 'mark_paid') {
        $p = $conn->prepare('SELECT gateway FROM payments WHERE booking_id=? FOR UPDATE');
        $p->bind_param('i', $id); $p->execute();
        if (($p->get_result()->fetch_assoc()['gateway'] ?? '') !== 'manual') {
            throw new RuntimeException('Online payments must be confirmed by PayMongo.');
        }
    }
    $uid=(int)$_SESSION['user_id'];$release=false;
    if(in_array($action,['reject','cancel'],true)&&$b['payment_status']==='Paid'){
        $conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('Paid appointments cannot be rejected or cancelled until payment/refund handling is resolved.'));
    }
    switch($action){
        case 'approve':$u=$conn->prepare("UPDATE bookings SET status='Approved',approved_by=? WHERE id=? AND status='Pending'");$u->bind_param('ii',$uid,$id);break;
        case 'reject':$u=$conn->prepare("UPDATE bookings SET status='Rejected' WHERE id=? AND status IN ('Pending','Approved')");$u->bind_param('i',$id);$release=true;break;
        case 'cancel':$u=$conn->prepare("UPDATE bookings SET status='Cancelled' WHERE id=? AND status IN ('Pending','Approved')");$u->bind_param('i',$id);$release=true;break;
        case 'no_show':$u=$conn->prepare("UPDATE bookings SET status='No-show' WHERE id=? AND status='Approved'");$u->bind_param('i',$id);$release=true;break;
        case 'check_in':$u=$conn->prepare("UPDATE bookings SET status='Checked-in',checked_in_by=?,check_in_at=NOW() WHERE id=? AND status='Approved'");$u->bind_param('ii',$uid,$id);break;
        case 'complete':$u=$conn->prepare("UPDATE bookings SET status='Completed',completed_by=?,completed_at=NOW() WHERE id=? AND status='Checked-in'");$u->bind_param('ii',$uid,$id);break;
        case 'mark_paid':$u=$conn->prepare("UPDATE bookings SET payment_status='Paid' WHERE id=? AND payment_status<>'Paid' AND status IN ('Approved','Checked-in','Completed')");$u->bind_param('i',$id);break;
    }
    $u->execute();$changed=$u->affected_rows>0;
    if(!$changed){$conn->rollback();redirectTo('admin-bookings.php?error='.urlencode('That action is not valid for the booking\'s current status.'));}
    if($release)releaseBookingCapacity($conn,$id);
    if(in_array($action,['reject','cancel','no_show'],true)){$p=$conn->prepare("UPDATE payments SET status=CASE WHEN status='Paid' THEN status ELSE 'Cancelled' END WHERE booking_id=?");$p->bind_param('i',$id);$p->execute();if($b['payment_status']!=='Paid'){$q=$conn->prepare("UPDATE bookings SET payment_status='Cancelled' WHERE id=?");$q->bind_param('i',$id);$q->execute();}}
    if($action==='mark_paid'){$p=$conn->prepare("UPDATE payments SET status='Paid',paid_at=NOW(),verified_by=? WHERE booking_id=?");$p->bind_param('ii',$uid,$id);$p->execute();}
    audit($conn,'booking_'.$action,'booking',$id);$conn->commit();redirectTo('admin-bookings.php?updated=1');
}catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}error_log('Booking action: '.$e->getMessage());redirectTo('admin-bookings.php?error='.urlencode('Update failed.'));}
