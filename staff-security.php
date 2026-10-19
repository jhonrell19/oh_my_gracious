<?php
require_once __DIR__.'/backend/authorization.php';
require_once __DIR__.'/backend/csrf.php';
requireLogin(true);
if(!in_array($_SESSION['role'],['admin','super_admin'],true))redirectTo('user-dashboard.php');
$id=(int)$_SESSION['user_id'];$row=securityRow($conn,$id);$message='';$codes=[];
if(empty($_SESSION['mfa_verified']) && time()-(int)$_SESSION['signed_in_at']>600){$_SESSION=[];session_destroy();redirectTo('login.html');}
try {
    encryptionKey();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verifyCsrf();$action=(string)($_POST['action']??'');
        if($action==='verify' && !empty($row['totp_secret'])){
            if(!verifySecondFactor($conn,$id,(string)($_POST['code']??'')))throw new RuntimeException('Invalid or already-used code. Wait for a new code, or use an unused recovery code.');
            session_regenerate_id(true);$_SESSION['mfa_verified']=true;unset($_SESSION['csrf_token']);
            audit($conn,'staff_mfa_verified','user',$id);redirectTo(roleRedirect($_SESSION['role']));
        }elseif($action==='enroll' && empty($row['totp_secret'])){
            confirmPassword($conn,$id,(string)($_POST['password']??''));securityThrottle($conn,'mfa',(string)$id);
            $secret=(string)($_SESSION['enrollment_secret']??'');
            $step=$secret!==''?matchingTotpStep($secret,trim((string)($_POST['code']??''))):null;
            if($step===null)throw new RuntimeException('The authenticator code did not match. Check your phone time and try a new code.');
            for($i=0;$i<10;$i++)$codes[]=strtoupper(bin2hex(random_bytes(8)));
            $hashes=json_encode(array_map(fn($c)=>hash('sha256',$c),$codes));$enc=sealSecret($secret,'totp:'.$id);
            $s=$conn->prepare('UPDATE user_security SET totp_secret=?,last_totp_step=?,recovery_hashes=?,session_version=session_version+1 WHERE user_id=? AND totp_secret IS NULL');
            $s->bind_param('sisi',$enc,$step,$hashes,$id);$s->execute();
            if($s->affected_rows!==1)throw new RuntimeException('Setup changed in another session. Sign in again.');
            $row=securityRow($conn,$id);$_SESSION['security_version']=(int)$row['session_version'];$_SESSION['mfa_verified']=true;unset($_SESSION['enrollment_secret']);session_regenerate_id(true);
            clearSecurityThrottle($conn,'mfa',(string)$id);audit($conn,'staff_mfa_enrolled','user',$id);
            $_SESSION['new_recovery_codes']=$codes;redirectTo('staff-security.php');
        }elseif(in_array($action,['password','revoke','recovery','reset_mfa'],true) && !empty($_SESSION['mfa_verified'])){
            confirmPassword($conn,$id,(string)($_POST['password']??''));
            if(!verifySecondFactor($conn,$id,(string)($_POST['code']??'')))throw new RuntimeException('Enter a fresh authenticator code or an unused recovery code.');
            if($action==='password'){
                $new=(string)($_POST['new_password']??'');
                if(strlen($new)<12 || strlen($new)>72 || $new!==(string)($_POST['confirm_password']??''))throw new RuntimeException('Use matching passwords of 12–72 characters.');
                $hash=password_hash($new,PASSWORD_DEFAULT);$s=$conn->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->bind_param('si',$hash,$id);$s->execute();
            }
            if($action==='reset_mfa'){
                $s=$conn->prepare('UPDATE user_security SET totp_secret=NULL,last_totp_step=-1,recovery_hashes=NULL WHERE user_id=?');$s->bind_param('i',$id);$s->execute();
            }
            if($action==='recovery'){
                for($i=0;$i<10;$i++)$codes[]=strtoupper(bin2hex(random_bytes(8)));
                $hashes=json_encode(array_map(fn($c)=>hash('sha256',$c),$codes));$s=$conn->prepare('UPDATE user_security SET recovery_hashes=? WHERE user_id=?');$s->bind_param('si',$hashes,$id);$s->execute();
                $_SESSION['new_recovery_codes']=$codes;audit($conn,'recovery_codes_replaced','user',$id);redirectTo('staff-security.php');
            }
            $s=$conn->prepare('UPDATE user_security SET session_version=session_version+1 WHERE user_id=?');$s->bind_param('i',$id);$s->execute();audit($conn,'staff_'.$action,'user',$id);
            $_SESSION=[];session_destroy();redirectTo('login.html');
        }else throw new RuntimeException('That action is not available.');
    }
} catch(Throwable $e){$message=$e instanceof RuntimeException?$e->getMessage():'Security action failed. Please try again.';}
$ready=true;try{encryptionKey();}catch(Throwable $e){$ready=false;}
if($ready && empty($row['totp_secret']) && empty($_SESSION['enrollment_secret']))$_SESSION['enrollment_secret']=base32Encode(random_bytes(20));
if(isset($_SESSION['new_recovery_codes'])){$codes=$_SESSION['new_recovery_codes'];unset($_SESSION['new_recovery_codes']);}
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Staff Security | Oh My Gracious</title><link rel="stylesheet" href="css/style.css"></head><body>
<header class="navbar"><div class="logo">Staff Security</div><nav><?php if(!empty($_SESSION['mfa_verified'])):?><a href="<?=h(roleRedirect($_SESSION['role']))?>">Dashboard</a><?php endif;?><a href="logout.php">Sign out</a></nav></header>
<main class="page-wrap narrow"><h1>Protect your staff account</h1><p>Signed in as <?=h($_SESSION['email'])?>. Staff access requires your password and an authenticator code.</p>
<?php if($message):?><div class="alert error"><?=h($message)?></div><?php endif;?>
<?php if(!$ready):?><section class="panel"><h2>Server setup required</h2><p>The administrator must configure APP_KEY using START_HERE_PHASE3.md. Your account and bookings have not been removed.</p></section>
<?php elseif(empty($row['totp_secret'])):?><section class="panel"><h2>Set up your authenticator</h2><ol><li>Open an authenticator app on your phone.</li><li>Add an account manually; choose a time-based code.</li><li>Use your email as the account name and enter the setup key below.</li><li>Enter your current password and the six-digit code to finish.</li></ol><p><strong>Setup key (keep private):</strong></p><p style="overflow-wrap:anywhere"><code><?=h($_SESSION['enrollment_secret'])?></code></p><form method="post" class="stack"><?=csrfInput()?><input type="hidden" name="action" value="enroll"><label>Current password<input type="password" name="password" autocomplete="current-password" required></label><label>Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></label><button class="btn primary">Enable two-factor authentication</button></form></section>
<?php elseif(empty($_SESSION['mfa_verified'])):?><section class="panel"><h2>Verify your sign-in</h2><form method="post" class="stack"><?=csrfInput()?><input type="hidden" name="action" value="verify"><label>Authenticator code or recovery code<input name="code" autocomplete="one-time-code" maxlength="32" required autofocus></label><button class="btn primary">Verify and continue</button></form><p>Codes can only be used once. Sign-in verification expires after 10 minutes.</p></section>
<?php else:?>
<?php if($codes):?><section class="panel"><h2>Save your recovery codes now</h2><p>These are shown once. Store them privately away from this computer. Each works once with your password if your phone is lost.</p><pre><?=h(implode("
",$codes))?></pre><a class="btn primary" href="<?=h(roleRedirect($_SESSION['role']))?>">I saved my codes — continue</a></section><?php endif;?>
<section class="panel"><h2>Two-factor authentication is active</h2><p>Staff sessions expire after 30 minutes idle or 8 hours total. Password changes sign out existing sessions.</p><form method="post" class="stack"><?=csrfInput()?><label>Current password<input type="password" name="password" autocomplete="current-password" required></label><label>Fresh authenticator or recovery code<input name="code" autocomplete="one-time-code" required></label><label>New password (only for password changes)<input type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password"></label><button name="action" value="password" class="btn primary">Change password and sign out</button><button name="action" value="revoke" class="btn secondary">Sign out all sessions, including this one</button><button name="action" value="reset_mfa" class="btn secondary">Replace authenticator and sign out</button><button name="action" value="recovery" class="btn secondary">Replace recovery codes</button></form></section>
<?php endif;?></main></body></html>
