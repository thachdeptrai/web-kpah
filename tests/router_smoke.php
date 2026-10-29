<?php
$root=dirname(__DIR__);
// UTF-8 BOM before <?php emits bytes and breaks Set-Cookie/session headers.
$firstBytes=(string)file_get_contents($root.'/config/register.php',false,null,0,3);
if($firstBytes===chr(239).chr(187).chr(191)){
    fwrite(STDERR,"FAIL: PHP config emits UTF-8 BOM before session headers".PHP_EOL);
    exit(1);
}
$config=require $root.'/config/routes.php';
function route_assert($ok,$message) {
    if (!$ok) { fwrite(STDERR,'FAIL: '.$message.PHP_EOL); exit(1); }
    echo 'PASS: '.$message.PHP_EOL;
}
foreach ($config['routes'] as $url=>$route) {
    route_assert(is_file($root.'/'.$route['file']), 'handler exists '.$url);
    route_assert($url[0]==='/' && strpos($url,'.php')===false, 'clean route '.$url);
    $admin=strpos($route['file'],'app/admin/')===0 || strpos($route['file'],'app/topup/admin/')===0;
    route_assert(($route['scope']==='admin')===$admin, 'correct session scope '.$url);
}
foreach ($config['aliases'] as $old=>$new) route_assert(isset($config['routes'][$new]),'legacy target '.$old);
// Run the actual dispatcher in isolated processes, not a copy of its logic.
foreach (['admin'=>'KPAHADMIN','player'=>'KPAHREG'] as $scope=>$cookie) {
    $code='define("KP_ROOT",'.var_export($root,true).'); define("KP_ROUTE_SCOPE",'.var_export($scope,true).'); require KP_ROOT."/app/includes/config_loader.php"; require KP_ROOT."/app/includes/security.php"; $_SERVER["SCRIPT_NAME"]="/index.php"; echo kp_session_cookie_name();';
    $cmd=escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code);
    route_assert(trim((string)shell_exec($cmd))===$cookie,'front controller isolates '.$scope.' session');
}
echo "PASS: route catalog and session isolation\n";
