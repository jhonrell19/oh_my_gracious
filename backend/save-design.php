<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';require_once __DIR__ . '/csrf.php';requireCustomer();verifyCsrf();
$userId=(int)$_SESSION['user_id'];$name=trim((string)($_POST['design_name']??'Saved AR Design'));$color=trim((string)($_POST['nail_color']??'#ff4f9a'));$shape=trim((string)($_POST['nail_shape']??'almond'));$finish=trim((string)($_POST['nail_finish']??'glossy'));$size=(float)($_POST['nail_size']??1);$vertical=(int)($_POST['vertical_offset']??0);$depth=(int)($_POST['depth_value']??0);$preview=(string)($_POST['preview_image']??'');
if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color)||!in_array($shape,['almond','round','square','coffin','stiletto'],true)||!in_array($finish,['natural','glossy','chrome','matte','pearl'],true))jsonResponse('error','Invalid design settings.',[],422);
if(strlen($name)>120 || strlen($preview)>7*1024*1024 || !is_finite($size))jsonResponse('error','Design is too large or invalid.',[],422);
if($preview!==''){
 $bytes=str_starts_with($preview,'data:image/png;base64,')?base64_decode(substr($preview,22),true):false;
 $info=$bytes!==false?@getimagesizefromstring($bytes):false;
 if(!$info || $info[2]!==IMAGETYPE_PNG || $info[0]>4096 || $info[1]>4096)jsonResponse('error','Use a valid PNG preview up to 4096 by 4096 pixels.',[],422);
}
$previewPath=null;if($preview!==''&&str_starts_with($preview,'data:image/png;base64,')){$raw=base64_decode(substr($preview,strpos($preview,',')+1),true);if($raw!==false&&strlen($raw)<=5*1024*1024&&substr($raw,0,8)==="\x89PNG\r\n\x1a\n"){$dir=dirname(__DIR__).'/uploads/designs';if(!is_dir($dir))mkdir($dir,0755,true);$file='design_'.$userId.'_'.bin2hex(random_bytes(8)).'.png';file_put_contents($dir.'/'.$file,$raw,LOCK_EX);$previewPath='uploads/designs/'.$file;}}
$stmt=$conn->prepare('INSERT INTO saved_designs(user_id,design_name,nail_color,nail_shape,nail_finish,nail_size,vertical_offset,depth_value,preview_path) VALUES (?,?,?,?,?,?,?,?,?)');$stmt->bind_param('issssdiis',$userId,$name,$color,$shape,$finish,$size,$vertical,$depth,$previewPath);$stmt->execute();$id=(int)$conn->insert_id;audit($conn,'design_saved','saved_design',$id);jsonResponse('success','Design saved to your account.',['design_id'=>$id]);
