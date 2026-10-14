<?php
// Test-only substitute for SMTP delivery. Never mapped by the HTTP router.
if (PHP_SAPI!=='cli' || getenv('KPAH_DB_NAME')!=='kpah_route_test') { http_response_code(403); exit(1); }
$id=$argv[1]??'';
if (!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$id)) exit(1);
session_name('KPAHREG'); session_id($id); session_start();
$_SESSION['kp_form_ts']=time()-10;
$_SESSION['kp_email_code_hash']=hash('sha256','123456');
$_SESSION['kp_email_code_expiry']=time()+300;
$_SESSION['kp_email_code_email']='newroute@example.test';
session_write_close();
