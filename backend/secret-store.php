<?php
declare(strict_types=1);
// Config calls this only after loading .env. No session or database dependency.
function encryptionKey(): string {
    $key = base64_decode((string)env('APP_KEY', ''), true);
    if ($key === false || strlen($key) !== 32) throw new RuntimeException('APP_KEY must be a base64-encoded random 32-byte key.');
    return $key;
}
function sealSecret(string $plain, string $context): string {
    $iv=random_bytes(12); $tag='';
    $encrypted=openssl_encrypt($plain,'aes-256-gcm',encryptionKey(),OPENSSL_RAW_DATA,$iv,$tag,$context);
    if ($encrypted===false) throw new RuntimeException('Encryption failed.');
    return base64_encode($iv.$tag.$encrypted);
}
function openSecret(string $encoded, string $context): string {
    $raw=base64_decode($encoded,true);
    if ($raw===false || strlen($raw)<28) throw new RuntimeException('Invalid encrypted settings.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',encryptionKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
    if ($plain===false) throw new RuntimeException('Cannot decrypt settings. Restore the original APP_KEY.');
    return $plain;
}
function paymentStorePath(): ?string {
    $configured=(string)env('PRIVATE_STORAGE_DIR','');
    if ($configured==='') return null;
    $dir=realpath($configured); $root=realpath(dirname(__DIR__));
    if (!$dir || !$root || !is_dir($dir)) throw new RuntimeException('Create PRIVATE_STORAGE_DIR outside the web root first.');
    $a=strtolower(str_replace('\\','/',$dir).'/');$b=strtolower(str_replace('\\','/',$root).'/');
    if (str_starts_with($a,$b)) throw new RuntimeException('Private storage must be outside the application web root.');
    $documentRoot=!empty($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
    if($documentRoot){$public=strtolower(str_replace('\\','/',$documentRoot).'/');if(str_starts_with($a,$public))throw new RuntimeException('Private storage must be outside the server document root.');}
    return $dir.'/paymongo.enc';
}
function savedPaymentConfig(): ?array {
    $path=paymentStorePath(); if (!$path || !is_file($path)) return null;
    $data=json_decode(openSecret((string)file_get_contents($path),'paymongo-config'),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Invalid payment configuration.');
    return $data;
}
function savePaymentConfig(array $data): void {
    $path=paymentStorePath(); if (!$path) throw new RuntimeException('Configure PRIVATE_STORAGE_DIR first.');
    $temp=tempnam(dirname($path),'omg-'); if ($temp===false) throw new RuntimeException('Private storage is not writable.');
    try {
        chmod($temp,0600);
        if(file_put_contents($temp,sealSecret(json_encode($data,JSON_THROW_ON_ERROR),'paymongo-config'),LOCK_EX)===false || !rename($temp,$path)) throw new RuntimeException('Could not save payment settings.');
    } finally {if(is_file($temp))unlink($temp);}
}
