<?php
require_once __DIR__.'/backend/helpers.php';require_once __DIR__.'/backend/csrf.php';
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();$_SESSION=[];
 if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
 session_destroy();redirectTo('login.html');
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign out</title><link rel="stylesheet" href="css/style.css"></head><body><main class="page-wrap narrow"><section class="panel"><h1>Sign out?</h1><form method="post"><?=csrfInput()?><button class="btn primary">Sign out</button></form></section></main></body></html>
