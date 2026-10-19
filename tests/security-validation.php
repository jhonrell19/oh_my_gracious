<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('APP_ENV=local');putenv('PRIVATE_STORAGE_DIR=');putenv('APP_KEY='.base64_encode(str_repeat('k',32)));
require __DIR__.'/../backend/security.php';
$n=0;
function expect(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException('FAIL: '.$name);$n++;}
// RFC 6238 SHA-1 vectors, truncated to six digits as configured for this app.
$key=base32Encode('12345678901234567890');
foreach([59=>'287082',1111111109=>'081804',1111111111=>'050471',1234567890=>'005924',2000000000=>'279037',20000000000=>'353130'] as $time=>$code){expect(totpCode($key,intdiv($time,30))===$code,'RFC TOTP vector '.$time);}
expect(base32Decode($key)==='12345678901234567890','base32 round trip');
$step=matchingTotpStep($key,'287082',-1,59);expect($step===1,'matching step');
expect(matchingTotpStep($key,'287082',1,59)===null,'TOTP replay rejected');
expect(matchingTotpStep($key,'287082',-1,180)===null,'expired TOTP rejected');
expect(matchingTotpStep($key,'ABCDEF',-1,59)===null,'malformed code rejected');
$sealed=sealSecret('sensitive payload','totp:12');expect(openSecret($sealed,'totp:12')==='sensitive payload','encrypted round trip');
try{openSecret($sealed,'totp:13');expect(false,'context binding');}catch(RuntimeException $e){expect(true,'context binding');}
$raw=base64_decode($sealed);$raw[28]=chr(ord($raw[28])^1);
try{openSecret(base64_encode($raw),'totp:12');expect(false,'tamper rejection');}catch(RuntimeException $e){expect(true,'tamper rejection');}
$_ENV['APP_KEY']=base64_encode(str_repeat('x',32));
try{openSecret($sealed,'totp:12');expect(false,'wrong key');}catch(RuntimeException $e){expect(true,'wrong key');}
echo "PASS: $n security checks\n";
