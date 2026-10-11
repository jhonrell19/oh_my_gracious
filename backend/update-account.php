<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';require_once __DIR__ . '/csrf.php';requireCustomer();verifyCsrf();
$userId=(int)$_SESSION['user_id'];$full=trim((string)($_POST['full_name']??''));$phone=normalizePhilippinePhone((string)($_POST['phone_number']??''));$dob=trim((string)($_POST['date_of_birth']??''));$techRaw=trim((string)($_POST['preferred_technician_id']??''));$prefs=trim((string)($_POST['nail_preferences']??''));$allergies=trim((string)($_POST['allergy_notes']??''));
if($full===''||$phone===null)redirectTo('account.php?error='.urlencode('Full name and a valid phone number are required.'));
$dobValue=null;if($dob!==''){$d=DateTime::createFromFormat('Y-m-d',$dob);if(!$d||$d->format('Y-m-d')!==$dob||$d>new DateTime('today'))redirectTo('account.php?error='.urlencode('Invalid date of birth.'));$dobValue=$dob;}
$tech=null;if($techRaw!==''&&ctype_digit($techRaw)){$tech=(int)$techRaw;$s=$conn->prepare("SELECT id FROM technicians WHERE id=? AND status='active'");$s->bind_param('i',$tech);$s->execute();if($s->get_result()->num_rows!==1)$tech=null;}
$dup=$conn->prepare('SELECT id FROM users WHERE phone_number=? AND id<>? LIMIT 1');$dup->bind_param('si',$phone,$userId);$dup->execute();if($dup->get_result()->num_rows)redirectTo('account.php?error='.urlencode('That phone number is already used by another account.'));
$stmt=$conn->prepare('UPDATE users SET full_name=?,phone_number=?,date_of_birth=?,preferred_technician_id=?,nail_preferences=?,allergy_notes=? WHERE id=?');$stmt->bind_param('sssissi',$full,$phone,$dobValue,$tech,$prefs,$allergies,$userId);$stmt->execute();$_SESSION['full_name']=$full;audit($conn,'profile_updated','user',$userId);redirectTo('account.php?saved=1');
