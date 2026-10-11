<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';require_once __DIR__ . '/helpers.php';require_once __DIR__ . '/csrf.php';verifyCsrf();
if(FACEBOOK_APP_ID===''||FACEBOOK_APP_SECRET==='')jsonResponse('error','Facebook login is not configured.',[],503);
$token=trim((string)($_POST['access_token']??''));if($token==='')jsonResponse('error','Facebook access token missing.',[],422);
try{
  $appToken=FACEBOOK_APP_ID.'|'.FACEBOOK_APP_SECRET;
  $debug=httpGetJson('https://graph.facebook.com/'.FACEBOOK_GRAPH_VERSION.'/debug_token?input_token='.urlencode($token).'&access_token='.urlencode($appToken));
  $d=$debug['data']??[];if(empty($d['is_valid'])||(string)($d['app_id']??'')!==(string)FACEBOOK_APP_ID)throw new RuntimeException('Invalid Facebook token');
  $p=httpGetJson('https://graph.facebook.com/'.FACEBOOK_GRAPH_VERSION.'/me?fields=id,name,email&access_token='.urlencode($token));
  $providerId=(string)($p['id']??'');$email=strtolower(trim((string)($p['email']??'')));$name=trim((string)($p['name']??'Facebook User'));if($providerId===''||$email==='')jsonResponse('error','Facebook did not provide an email. Use another sign-in method or add an email to Facebook.',[],422);
  $s=$conn->prepare("SELECT u.* FROM auth_identities ai JOIN users u ON u.id=ai.user_id WHERE ai.provider='facebook' AND ai.provider_user_id=? LIMIT 1");$s->bind_param('s',$providerId);$s->execute();$user=$s->get_result()->fetch_assoc();
  if(!$user){$s=$conn->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$s->bind_param('s',$email);$s->execute();$user=$s->get_result()->fetch_assoc();if($user){$l=$conn->prepare("INSERT IGNORE INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'facebook',?)");$l->bind_param('is',$user['id'],$providerId);$l->execute();}else{$role='user';$status='active';$conn->begin_transaction();$c=$conn->prepare('INSERT INTO users(full_name,email,role,account_status,email_verified_at) VALUES (?,?,?,?,NOW())');$c->bind_param('ssss',$name,$email,$role,$status);$c->execute();$id=(int)$conn->insert_id;$l=$conn->prepare("INSERT INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'facebook',?)");$l->bind_param('is',$id,$providerId);$l->execute();$conn->commit();$user=['id'=>$id,'full_name'=>$name,'email'=>$email,'role'=>'user','account_status'=>'active'];}}
  $result=createLoginSession($user);if($result['status']==='error')jsonResponse('error',$result['message'],[],403);jsonResponse('success','Facebook login successful.',['redirect'=>$result['redirect']]);
}catch(Throwable $e){error_log('Facebook auth: '.$e->getMessage());jsonResponse('error','Facebook login could not be completed.',[],401);}
