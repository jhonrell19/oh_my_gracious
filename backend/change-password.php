<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';require_once __DIR__ . '/csrf.php';requireCustomer();verifyCsrf();
$userId=(int)$_SESSION['user_id'];$current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
if($new!==$confirm||strlen($new)<8||!preg_match('/[A-Z]/',$new)||!preg_match('/[a-z]/',$new)||!preg_match('/[0-9]/',$new)||!preg_match('/[^A-Za-z0-9]/',$new))redirectTo('account.php?error='.urlencode('New password does not meet the security rules.'));
$s=$conn->prepare('SELECT password_hash,email FROM users WHERE id=?');$s->bind_param('i',$userId);$s->execute();$u=$s->get_result()->fetch_assoc();if(!$u||empty($u['password_hash'])||!password_verify($current,$u['password_hash']))redirectTo('account.php?error='.urlencode('Current password is incorrect.'));
$hash=password_hash($new,PASSWORD_DEFAULT);$up=$conn->prepare('UPDATE users SET password_hash=? WHERE id=?');$up->bind_param('si',$hash,$userId);$up->execute();audit($conn,'password_changed','user',$userId);redirectTo('account.php?password=1');
