<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();
if (GOOGLE_CLIENT_ID === '') jsonResponse('error','Google login is not configured.',[],503);
$credential=trim((string)($_POST['credential']??''));
if($credential==='') jsonResponse('error','Google credential missing.',[],422);
try{
  $profile=httpGetJson('https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($credential));
  if(($profile['aud']??'')!==GOOGLE_CLIENT_ID || !in_array((string)($profile['email_verified']??''),['true','1'],true)) throw new RuntimeException('Google token verification failed.');
  $providerId=(string)$profile['sub']; $email=strtolower((string)$profile['email']); $name=trim((string)($profile['name']??$email));
  $stmt=$conn->prepare("SELECT u.* FROM auth_identities ai JOIN users u ON u.id=ai.user_id WHERE ai.provider='google' AND ai.provider_user_id=? LIMIT 1");$stmt->bind_param('s',$providerId);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();
  if(!$user){$stmt=$conn->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$stmt->bind_param('s',$email);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();
    if($user){$link=$conn->prepare("INSERT IGNORE INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'google',?)");$link->bind_param('is',$user['id'],$providerId);$link->execute();$conn->query('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()), account_status=IF(account_status="pending_verification","active",account_status) WHERE id='.(int)$user['id']);}
    else{$role='user';$status='active';$conn->begin_transaction();$c=$conn->prepare('INSERT INTO users(full_name,email,role,account_status,email_verified_at) VALUES (?,?,?,?,NOW())');$c->bind_param('ssss',$name,$email,$role,$status);$c->execute();$id=(int)$conn->insert_id;$l=$conn->prepare("INSERT INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'google',?)");$l->bind_param('is',$id,$providerId);$l->execute();$conn->commit();$user=['id'=>$id,'full_name'=>$name,'email'=>$email,'role'=>'user','account_status'=>'active'];}
  }
  $result=createLoginSession($user); if($result['status']==='error') jsonResponse('error',$result['message'],[],403); jsonResponse('success','Google login successful.',['redirect'=>$result['redirect']]);
}catch(Throwable $e){error_log('Google auth: '.$e->getMessage());jsonResponse('error','Google login could not be completed.',[],401);}
