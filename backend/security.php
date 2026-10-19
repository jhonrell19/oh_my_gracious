<?php
declare(strict_types=1);
require_once __DIR__.'/helpers.php';
function securityRow(mysqli $conn,int $id): array {
    $s=$conn->prepare('INSERT IGNORE INTO user_security(user_id) VALUES (?)');$s->bind_param('i',$id);$s->execute();
    $s=$conn->prepare('SELECT * FROM user_security WHERE user_id=?');$s->bind_param('i',$id);$s->execute();return $s->get_result()->fetch_assoc();
}
function securityThrottle(mysqli $conn,string $purpose,string $identity,int $max=5): void {
    $bucket=hash('sha256',$purpose.':'.$identity);
    // Serialize attempts, including simultaneous requests. Reserve before verification.
    $s=$conn->prepare('SELECT GET_LOCK(?,5) ok');$s->bind_param('s',$bucket);$s->execute();
    if((int)$s->get_result()->fetch_assoc()['ok']!==1)throw new RuntimeException('Please try again shortly.');
    try {
        $s=$conn->prepare('SELECT COUNT(*) n FROM security_attempts WHERE bucket=? AND created_at>NOW()-INTERVAL 15 MINUTE');$s->bind_param('s',$bucket);$s->execute();
        if((int)$s->get_result()->fetch_assoc()['n'] >= $max)throw new RuntimeException('Too many attempts. Wait 15 minutes before trying again.');
        $s=$conn->prepare('INSERT INTO security_attempts(bucket) VALUES (?)');$s->bind_param('s',$bucket);$s->execute();
    } finally {$s=$conn->prepare('SELECT RELEASE_LOCK(?)');$s->bind_param('s',$bucket);$s->execute();}
}
function clearSecurityThrottle(mysqli $conn,string $purpose,string $identity): void {
    $b=hash('sha256',$purpose.':'.$identity);$s=$conn->prepare('DELETE FROM security_attempts WHERE bucket=?');$s->bind_param('s',$b);$s->execute();
}
function base32Encode(string $raw): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($raw) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
    $out='';foreach(str_split($bits,5) as $part)$out.=$alphabet[bindec(str_pad($part,5,'0'))];return $out;
}
function base32Decode(string $input): string {
    $bits='';foreach(str_split(strtoupper($input)) as $c){$v=strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$c);if($v===false)throw new RuntimeException('Invalid authenticator key.');$bits.=str_pad(decbin($v),5,'0',STR_PAD_LEFT);}
    $out='';foreach(str_split($bits,8) as $part)if(strlen($part)===8)$out.=chr(bindec($part));return $out;
}
function totpCode(string $secret,int $step): string {
    $hash=hash_hmac('sha1',pack('N2',intdiv($step,4294967296),$step%4294967296),base32Decode($secret),true);$offset=ord($hash[19])&15;
    $n=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);
}
function matchingTotpStep(string $secret,string $code,int $last=-1,?int $time=null): ?int {
    if(!preg_match('/^\d{6}$/',$code))return null;$now=intdiv($time??time(),30);
    for($step=$now-1;$step<=$now+1;$step++)if($step>$last&&hash_equals(totpCode($secret,$step),$code))return $step;
    return null;
}
function verifySecondFactor(mysqli $conn,int $id,string $code): bool {
    securityThrottle($conn,'mfa',(string)$id);
    $conn->begin_transaction();
    try {
        $s=$conn->prepare('SELECT * FROM user_security WHERE user_id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$valid=false;
        if($r && $r['totp_secret']) {
            $secret=openSecret($r['totp_secret'],'totp:'.$id);
            $step=matchingTotpStep($secret,trim($code),(int)$r['last_totp_step']);
            if($step!==null){$s=$conn->prepare('UPDATE user_security SET last_totp_step=? WHERE user_id=?');$s->bind_param('ii',$step,$id);$s->execute();$valid=true;}
            else {
                $hashes=json_decode($r['recovery_hashes']??'[]',true)?:[];$candidate=hash('sha256',strtoupper(trim($code)));
                foreach($hashes as $i=>$hash)if(hash_equals($hash,$candidate)){unset($hashes[$i]);$json=json_encode(array_values($hashes));$s=$conn->prepare('UPDATE user_security SET recovery_hashes=? WHERE user_id=?');$s->bind_param('si',$json,$id);$s->execute();$valid=true;break;}
            }
        }
        $conn->commit();if($valid)clearSecurityThrottle($conn,'mfa',(string)$id);return $valid;
    }catch(Throwable $e){$conn->rollback();throw $e;}
}
function confirmPassword(mysqli $conn,int $id,string $password): void {
    securityThrottle($conn,'reauth',(string)$id);
    $s=$conn->prepare('SELECT password_hash FROM users WHERE id=?');$s->bind_param('i',$id);$s->execute();$u=$s->get_result()->fetch_assoc();
    if(!$u || !password_verify($password,$u['password_hash']??''))throw new RuntimeException('Current password is incorrect.');
    clearSecurityThrottle($conn,'reauth',(string)$id);
}
