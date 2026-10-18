<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/payment-validation.php';
$checks=0;
function expect(bool $ok,string $label): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('FAIL: '.$label); }
function rejects(callable $fn,string $label): void { try {$fn();} catch (Throwable $e) {expect(true,$label);return;} expect(false,$label); }
$now=1700000000; $raw='{"sample":true}';$secret='test-fixture-secret';
$sig=hash_hmac('sha256',$now.'.'.$raw,$secret);
expect(validPaymongoSignature($raw,"t=$now,te=$sig,li=",$secret,false,$now),'valid test signature');
expect(!validPaymongoSignature($raw,"t=$now,te=$sig,li=",$secret,true,$now),'test signature rejected in live mode');
expect(!validPaymongoSignature($raw.' ',"t=$now,te=$sig",$secret,false,$now),'tampered payload');
expect(!validPaymongoSignature($raw,"t=$now,te=$sig",$secret,false,$now+601),'expired signature');
expect(!validPaymongoSignature($raw,"t=$now,te=$sig",'wrong',false,$now),'wrong secret');
expect(!validPaymongoSignature($raw,'', $secret,false,$now),'missing signature');
$session=['id'=>'cs_fixture','attributes'=>['reference_number'=>'OMG-BOOKING-12','payments'=>[
 ['id'=>'pay_good','attributes'=>['status'=>'paid','amount'=>79900,'currency'=>'PHP','source'=>['type'=>'gcash']]],
 ['id'=>'pay_failed','attributes'=>['status'=>'failed','amount'=>79900,'currency'=>'PHP']]
]]];
$attrs=['type'=>'checkout_session.payment.paid','livemode'=>false,'data'=>$session];
$new=['data'=>$attrs];$old=['data'=>['id'=>'evt_123','type'=>'event','attributes'=>$attrs]];
foreach ([$new,$old] as $event) {
 $paid=parsePaidCheckout($event,false);
 expect($paid['payment_id']==='pay_good','select successful payment, not last attempt');
 expect($paid['booking_id']===12 && $paid['amount']===79900,'booking and centavos');
}
rejects(fn()=>parsePaidCheckout($new,true),'mode mismatch');
$bad=$new;$bad['data']['data']['attributes']['payments'][0]['attributes']['currency']='USD';
rejects(fn()=>parsePaidCheckout($bad,false),'wrong currency');
$bad=$new;$bad['data']['data']['attributes']['payments'][0]['attributes']['amount']='79900';
rejects(fn()=>parsePaidCheckout($bad,false),'non-integer centavos');
$bad=$new;$bad['data']['data']['attributes']['payments'][1]['attributes']['status']='paid';
rejects(fn()=>parsePaidCheckout($bad,false),'multiple paid attempts');
$bad=$new;$bad['data']['data']['attributes']['reference_number']='OTHER-12';
rejects(fn()=>parsePaidCheckout($bad,false),'invalid booking reference');
$ignored=['data'=>['type'=>'other.event','livemode'=>false]];
expect(parsePaidCheckout($ignored,false)===null,'ignore unrelated event');
$rows = [
 ['service_name'=>'Gel Manicure','unit_price'=>'599.00','quantity'=>1,'line_total'=>'599.00'],
 ['service_name'=>'French Tip','unit_price'=>'50.00','quantity'=>4,'line_total'=>'200.00']
];
$items=paymongoLineItems($rows,79900,79900);
expect($items[1]['quantity']===4 && $items[1]['amount']===5000,'per nail quantity and unit centavos');
rejects(fn()=>paymongoLineItems($rows,64900,64900),'reject quantity-ignored total');
rejects(fn()=>paymongoLineItems($rows,79900,80000),'reject payment mismatch');
rejects(fn()=>paymongoLineItems([],0,0),'reject empty booking');
$rows[1]['line_total']='50.00';
rejects(fn()=>paymongoLineItems($rows,79900,79900),'reject bad stored line total');
echo "PASS: $checks payment validation checks\n";
