<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function loadServicesByIds(mysqli $conn, array $ids, bool $forUpdate=false): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id,service_name,category,price,duration_minutes,booking_availability,unavailable_reason FROM services WHERE status='active' AND id IN ($placeholders)" . ($forUpdate ? " FOR UPDATE" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function selectedServicesAreBookable(array $services): bool
{
    foreach ($services as $service) {
        if (($service['booking_availability'] ?? 'available') !== 'available') return false;
    }
    return true;
}

function bookingTotals(array $services): array
{
    $amount = 0.0;
    $duration = 0;
    foreach ($services as $service) {
        $amount += (float)$service['price'];
        $duration += max(0, (int)$service['duration_minutes']);
    }
    return ['amount' => $amount, 'duration' => $duration];
}

function validBookingDate(mysqli $conn, string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) return false;
    $today = new DateTime('today');
    $max = (clone $today)->modify('+' . max(1, (int)getSetting($conn, 'booking_days_ahead', '90')) . ' days');
    return $parsed >= $today && $parsed <= $max;
}

function normalizeSlotTime(string $time): string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m)) return '';
    $h=(int)$m[1];$min=(int)$m[2];
    if($h<0||$h>23||$min<0||$min>59)return '';
    return sprintf('%02d:%02d:00',$h,$min);
}

function bookingSlotTemplates(mysqli $conn, bool $activeOnly=true): array
{
    $sql='SELECT id,slot_time,period,default_capacity,status,display_order FROM booking_time_slots';
    if($activeOnly)$sql.=" WHERE status='active'";
    $sql.=' ORDER BY display_order,slot_time';
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function effectiveSlotRows(mysqli $conn, string $date): array
{
    $stmt=$conn->prepare("SELECT t.id,t.slot_time,t.period,t.default_capacity,t.status template_status,t.display_order,
        COALESCE(i.capacity,t.default_capacity) capacity,
        COALESCE(i.status,'open') slot_status,
        COALESCE(i.reason,'') reason,
        COALESCE(i.reserved_count,0) reserved_count
        FROM booking_time_slots t
        LEFT JOIN booking_slot_inventory i ON i.slot_date=? AND i.slot_time=t.slot_time
        WHERE t.status='active'
        ORDER BY t.display_order,t.slot_time");
    $stmt->bind_param('s',$date);$stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function candidateStartTimes(mysqli $conn, string $date, int $unusedDurationMinutes=0): array
{
    $rows=effectiveSlotRows($conn,$date);$out=[];
    foreach($rows as $row){
        $capacity=max(1,(int)$row['capacity']);
        $reserved=max(0,(int)$row['reserved_count']);
        $remaining=max(0,$capacity-$reserved);
        $isClosed=$row['slot_status']==='closed';
        $isFull=$remaining<=0;
        $isPast=$date===date('Y-m-d') && strtotime($date.' '.$row['slot_time'])<=time();
        $label=date('g:i A',strtotime((string)$row['slot_time']));
        if($isPast)$label.=' — Time passed';
        elseif($isClosed)$label.=' — Closed'.($row['reason']!==''?' ('.$row['reason'].')':'');
        elseif($isFull)$label.=' — Fully booked';
        else $label.=' — '.$remaining.' slot'.($remaining===1?'':'s').' left';
        $status=$isPast?'passed':($isClosed?'closed':($isFull?'full':'available'));
        $out[]=[
            'value'=>substr((string)$row['slot_time'],0,5),
            'label'=>$label,
            'period'=>(string)$row['period'],
            'capacity'=>$capacity,
            'reserved'=>$reserved,
            'remaining'=>$remaining,
            'status'=>$status,
            'reason'=>(string)$row['reason'],
            'available'=>!$isPast&&!$isClosed&&!$isFull,
        ];
    }
    return $out;
}

function ensureInventoryRowForUpdate(mysqli $conn,string $date,string $time): array
{
    $time=normalizeSlotTime($time);
    if($time==='')throw new RuntimeException('Invalid time slot.');
    $template=$conn->prepare("SELECT slot_time,default_capacity,status FROM booking_time_slots WHERE slot_time=? LIMIT 1");
    $template->bind_param('s',$time);$template->execute();$t=$template->get_result()->fetch_assoc();
    if(!$t||$t['status']!=='active')throw new RuntimeException('This time slot is not active.');
    $capacity=max(1,(int)$t['default_capacity']);
    $insert=$conn->prepare("INSERT IGNORE INTO booking_slot_inventory(slot_date,slot_time,capacity,status,reserved_count) VALUES (?,?,?,'open',0)");
    $insert->bind_param('ssi',$date,$time,$capacity);$insert->execute();
    $lock=$conn->prepare('SELECT slot_date,slot_time,capacity,status,reason,reserved_count FROM booking_slot_inventory WHERE slot_date=? AND slot_time=? FOR UPDATE');
    $lock->bind_param('ss',$date,$time);$lock->execute();$row=$lock->get_result()->fetch_assoc();
    if(!$row)throw new RuntimeException('Time slot inventory could not be prepared.');
    return $row;
}

function reserveCapacitySlot(mysqli $conn,string $date,string $time,int $bookingId): void
{
    $time=normalizeSlotTime($time);
    if($date===date('Y-m-d') && strtotime($date.' '.$time)<=time())throw new RuntimeException('That appointment time has already passed.');
    $row=ensureInventoryRowForUpdate($conn,$date,$time);
    if($row['status']==='closed')throw new RuntimeException($row['reason']!==''?'This time is closed: '.$row['reason']:'This time slot is closed.');
    if((int)$row['reserved_count']>=(int)$row['capacity'])throw new RuntimeException('That time is already fully booked.');
    $u=$conn->prepare('UPDATE booking_slot_inventory SET reserved_count=reserved_count+1 WHERE slot_date=? AND slot_time=?');
    $u->bind_param('ss',$date,$time);$u->execute();
    $s=$conn->prepare('INSERT INTO booking_slots(booking_id,slot_date,slot_time) VALUES (?,?,?)');
    $s->bind_param('iss',$bookingId,$date,$time);$s->execute();
}

function releaseBookingCapacity(mysqli $conn,int $bookingId): void
{
    $s=$conn->prepare('SELECT slot_date,slot_time FROM booking_slots WHERE booking_id=? FOR UPDATE');
    $s->bind_param('i',$bookingId);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($rows as $row){
        $u=$conn->prepare('UPDATE booking_slot_inventory SET reserved_count=GREATEST(0,reserved_count-1) WHERE slot_date=? AND slot_time=?');
        $u->bind_param('ss',$row['slot_date'],$row['slot_time']);$u->execute();
    }
    $d=$conn->prepare('DELETE FROM booking_slots WHERE booking_id=?');$d->bind_param('i',$bookingId);$d->execute();
}

function slotIsSelectable(mysqli $conn,string $date,string $time): bool
{
    $needle=substr(normalizeSlotTime($time),0,5);
    foreach(candidateStartTimes($conn,$date) as $slot){if($slot['value']===$needle)return (bool)$slot['available'];}
    return false;
}
