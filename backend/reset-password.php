<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();
$token=trim((string)($_POST['token']??''));$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
if($token===''||$password===''||$password!==$confirm)jsonResponse('error','Invalid reset request.',[],422);
if(strlen($password)>72||strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password)||!preg_match('/[^A-Za-z0-9]/',$password))jsonResponse('error','Password does not meet the security rules.',[],422);
$hash=hash('sha256',$token);
$newHash=password_hash($password,PASSWORD_DEFAULT);
try{
    $conn->begin_transaction();
    $stmt=$conn->prepare('SELECT id,user_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE');$stmt->bind_param('s',$hash);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    if(!$row){$conn->rollback();jsonResponse('error','This reset link is invalid or expired.',[],400);}
    $uid=(int)$row['user_id'];
    $u=$conn->prepare('UPDATE users SET password_hash=? WHERE id=?');$u->bind_param('si',$newHash,$uid);$u->execute();
    $em=$conn->prepare('SELECT email FROM users WHERE id=?');$em->bind_param('i',$uid);$em->execute();$email=(string)($em->get_result()->fetch_assoc()['email']??'');
    if($email!==''){$a=$conn->prepare("INSERT INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'password',?) ON DUPLICATE KEY UPDATE provider_user_id=VALUES(provider_user_id)");$a->bind_param('is',$uid,$email);$a->execute();}
    $t=$conn->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=?');$t->bind_param('i',$uid);$t->execute();audit($conn,'password_reset','user',$uid);$conn->commit();jsonResponse('success','Password updated. You can sign in now.');
}catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}error_log('Password reset: '.$e->getMessage());jsonResponse('error','Password reset failed.',[],500);}
