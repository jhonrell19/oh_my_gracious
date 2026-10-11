<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';require_once __DIR__ . '/booking-helper.php';
requireLogin();
if (!isCustomer() && !isRegularAdmin()) jsonResponse('error','Access denied.',[],403);
$date=trim((string)($_GET['date']??''));$idsRaw=(string)($_GET['service_ids']??'');$ids=array_filter(explode(',',$idsRaw),fn($v)=>ctype_digit($v));
if(!$date||!validBookingDate($conn,$date)||!$ids)jsonResponse('error','Choose a valid date and at least one service.',[],422);
$services=loadServicesByIds($conn,$ids);if(count($services)!==count(array_unique(array_map('intval',$ids))))jsonResponse('error','One or more selected services are unavailable.',[],422);
$totals=bookingTotals($services);$times=candidateStartTimes($conn,$date,$totals['duration']);
jsonResponse('success','Availability loaded.',['times'=>$times,'total_amount'=>$totals['amount'],'total_duration'=>$totals['duration']]);
