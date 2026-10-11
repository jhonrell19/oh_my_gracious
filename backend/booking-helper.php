<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function loadServicesByIds(mysqli $conn, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("SELECT id,service_name,price,duration_minutes FROM services WHERE status='active' AND id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function bookingTotals(array $services): array
{
    $amount = 0.0;
    $duration = 0;
    foreach ($services as $service) {
        $amount += (float)$service['price'];
        $duration += (int)$service['duration_minutes'];
    }
    return ['amount' => $amount, 'duration' => max(30, $duration)];
}


function validBookingDate(mysqli $conn, string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) return false;
    $today = new DateTime('today');
    $max = (clone $today)->modify('+' . max(1, (int)getSetting($conn, 'booking_days_ahead', '90')) . ' days');
    return $parsed >= $today && $parsed <= $max;
}

function timeToMinutes(string $time): int
{
    [$h,$m] = array_map('intval', explode(':', substr($time,0,5)));
    return ($h*60)+$m;
}

function minutesToTime(int $minutes): string
{
    return sprintf('%02d:%02d:00', intdiv($minutes,60), $minutes%60);
}

function requiredSlotTimes(string $startTime, int $durationMinutes, int $interval): array
{
    $start = timeToMinutes($startTime);
    $count = (int)ceil($durationMinutes / $interval);
    $slots = [];
    for ($i=0; $i<$count; $i++) $slots[] = minutesToTime($start + ($i*$interval));
    return $slots;
}

function candidateStartTimes(mysqli $conn, string $date, int $durationMinutes): array
{
    $open = getSetting($conn,'open_time','10:00');
    $close = getSetting($conn,'close_time','22:00');
    $interval = max(15,(int)getSetting($conn,'slot_interval_minutes','30'));
    $openMin = timeToMinutes($open);
    $closeMin = timeToMinutes($close);
    $lastStart = $closeMin - $durationMinutes;
    $occupied = [];
    $stmt = $conn->prepare('SELECT slot_time FROM booking_slots WHERE slot_date=?');
    $stmt->bind_param('s',$date);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$occupied[substr($row['slot_time'],0,5)]=true;
    $out=[];
    for($start=$openMin;$start<=$lastStart;$start+=$interval){
        $time=minutesToTime($start);$needed=requiredSlotTimes($time,$durationMinutes,$interval);$free=true;
        foreach($needed as $slot){if(isset($occupied[substr($slot,0,5)])){$free=false;break;}}
        if($free)$out[]=['value'=>substr($time,0,5),'label'=>date('g:i A',strtotime($time))];
    }
    return $out;
}
