<?php
require_once __DIR__.'/backend/authorization.php';requireCustomer();
$id=(int)($_GET['id']??0);$uid=(int)$_SESSION['user_id'];
$s=$conn->prepare('SELECT preview_path FROM saved_designs WHERE id=? AND user_id=?');$s->bind_param('ii',$id,$uid);$s->execute();$r=$s->get_result()->fetch_assoc();
$base=realpath(__DIR__.'/uploads/designs');$file=$r?realpath(__DIR__.'/'.($r['preview_path']??'')):false;
if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='png'){http_response_code(404);exit;}
header('Content-Type: image/png');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);
