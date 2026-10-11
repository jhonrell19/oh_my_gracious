<?php

declare(strict_types=1);
require_once __DIR__.'/db.php';require_once __DIR__.'/helpers.php';require_once __DIR__.'/csrf.php';verifyCsrf();
if(TWILIO_ACCOUNT_SID===''||TWILIO_AUTH_TOKEN===''||TWILIO_VERIFY_SERVICE_SID==='')jsonResponse('error','SMS verification is not configured.',[],503);
$phone=normalizePhilippinePhone((string)($_POST['phone_number']??''));$mode=(string)($_POST['mode']??'login');if($phone===null||!in_array($mode,['login','register'],true))jsonResponse('error','Enter a valid Philippine mobile number.',[],422);
$s=$conn->prepare('SELECT id,account_status FROM users WHERE phone_number=? LIMIT 1');$s->bind_param('s',$phone);$s->execute();$existing=$s->get_result()->fetch_assoc();
if($mode==='login'&&!$existing)jsonResponse('error','No account is registered with that phone number.',[],404);
if($mode==='register'&&$existing)jsonResponse('error','That phone number already has an account. Use Phone Sign-In instead.',[],409);
if($existing&&$existing['account_status']==='suspended')jsonResponse('error','This account is suspended.',[],403);
try{httpPostFormJson('https://verify.twilio.com/v2/Services/'.rawurlencode(TWILIO_VERIFY_SERVICE_SID).'/Verifications',['To'=>$phone,'Channel'=>'sms'],[TWILIO_ACCOUNT_SID,TWILIO_AUTH_TOKEN]);jsonResponse('success','Verification code sent.');}catch(Throwable $e){error_log('Twilio send: '.$e->getMessage());jsonResponse('error','Verification code could not be sent.',[],502);}
