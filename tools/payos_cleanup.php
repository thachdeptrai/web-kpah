<?php
// CLI only: run every minute from Windows Task Scheduler.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once dirname(__DIR__).'/app/includes/bank_payos.php';
if(!kp_bank_cfg()['ready']){fwrite(STDERR,"payOS is not configured\n");exit(1);}
try{$n=kp_bank_cleanup(100);echo "payOS cleaned ".$n." cancelled unpaid orders\n";}
catch(Throwable $e){kp_log('payOS CLI cleanup failed',$e);fwrite(STDERR,"Cleanup failed\n");exit(1);}
