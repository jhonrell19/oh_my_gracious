<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';
function paymentConfigFingerprint(string $key,string $secret): string {return hash('sha256',$key."\0".$secret."\0".APP_URL);}
function checkMerchantConnection(string $key,string $secret): array {
    if(!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/',$key) || !str_starts_with($secret,'whsk_'))throw new RuntimeException('Enter a valid secret API key and webhook signing secret.');
    if(parse_url(APP_URL,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Set your HTTPS APP_URL before verifying PayMongo.');
    $ch=curl_init('https://api.paymongo.com/v1/webhooks');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_USERPWD=>$key.':',CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($body===false || $status!==200)throw new RuntimeException('PayMongo verification failed. Check the key, account permissions, and server internet access. Existing settings were kept.');
    $response=json_decode($body,true);if(!is_array($response['data']??null))throw new RuntimeException('Unexpected response from PayMongo.');
    $live=str_starts_with($key,'sk_live_');
    foreach($response['data'] as $hook){$a=$hook['attributes']??[];
        if(($a['url']??'')!==appUrl('webhooks/paymongo.php') || ($a['status']??'')!=='enabled' || ($a['livemode']??null)!==$live || !in_array('checkout_session.payment.paid',$a['events']??[],true))continue;
        if(!isset($a['secret_key']) || !hash_equals($secret,(string)$a['secret_key']))continue;
        return ['hook_id'=>$hook['id'],'checked_at'=>date(DATE_ATOM),'fingerprint'=>paymentConfigFingerprint($key,$secret)];
    }
    throw new RuntimeException('No matching enabled webhook was verified. Check the exact URL, test/live mode, paid-checkout event, and signing secret in PayMongo.');
}
