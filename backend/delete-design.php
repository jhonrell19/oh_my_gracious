<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';require_once __DIR__ . '/csrf.php';requireCustomer();verifyCsrf();$id=(int)($_POST['design_id']??0);$uid=(int)$_SESSION['user_id'];$s=$conn->prepare('SELECT preview_path FROM saved_designs WHERE id=? AND user_id=?');$s->bind_param('ii',$id,$uid);$s->execute();$row=$s->get_result()->fetch_assoc();if($row){$d=$conn->prepare('DELETE FROM saved_designs WHERE id=? AND user_id=?');$d->bind_param('ii',$id,$uid);$d->execute();if(!empty($row['preview_path'])){$path=dirname(__DIR__).'/'.$row['preview_path'];if(is_file($path))@unlink($path);}audit($conn,'design_deleted','saved_design',$id);}redirectTo('saved-designs.php');
