<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../backend/db.php';require __DIR__.'/../backend/helpers.php';require __DIR__.'/../backend/security.php';
$options=getopt('',['email:']);$email=strtolower(trim((string)($options['email']??'')));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Usage: php tools/recover-staff-mfa.php --email=owner@example.com\n");exit(1);}
$s=$conn->prepare("SELECT id,email,role FROM users WHERE email=? AND role IN ('admin','super_admin') AND account_status='active'");$s->bind_param('s',$email);$s->execute();$u=$s->get_result()->fetch_assoc();
if(!$u){fwrite(STDERR,"Active staff account not found.\n");exit(1);}
echo "AUTHORIZED SERVER ADMINISTRATORS ONLY. Verify the person's identity outside the website first.\nThis resets MFA and signs out all sessions for ".$u['email']." (".$u['role']."). It does not reset their password.\nType RECOVER ".$email." to continue: ";
if(trim((string)fgets(STDIN))!=='RECOVER '.$email){echo "Cancelled.\n";exit(1);}
$id=(int)$u['id'];securityRow($conn,$id);$conn->begin_transaction();
try{$s=$conn->prepare('UPDATE user_security SET totp_secret=NULL,recovery_hashes=NULL,last_totp_step=-1,session_version=session_version+1 WHERE user_id=?');$s->bind_param('i',$id);$s->execute();audit($conn,'staff_mfa_recovered_by_server_operator','user',$id);$conn->commit();echo "MFA reset. Sign in with the existing password and enroll a new authenticator.\n";}catch(Throwable $e){$conn->rollback();fwrite(STDERR,"Recovery failed.\n");exit(1);}
