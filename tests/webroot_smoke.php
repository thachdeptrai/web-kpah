<?php
// Verifies URL front controllers have matching handlers outside Apache webroot.
$root=dirname(__DIR__);
$public=$root.'/public';
$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public,FilesystemIterator::SKIP_DOTS)) as $file) {
 if (!$file->isFile()) continue;
 $path=$file->getPathname();
 $relative=str_replace('\\','/',substr($path,strlen($public)+1));
 if (preg_match('/\\.(sql|sqlite|db|bak|log|zip|json|env)$/i',$relative)) {
   fwrite(STDERR,"Unexpected public file: $relative\n");exit(1);
 }
 if (substr($relative,-4)!=='.php') continue;
 $count++;
 $src=file_get_contents($path);
 if (strlen($src)>400 || strpos($src,"'/app/pages/$relative'")===false || !is_file($root.'/app/pages/'.$relative)) {
   fwrite(STDERR,"Broken entry point: $relative\n");exit(1);
 }
}
if ($count<35) {fwrite(STDERR,"Missing PHP entrypoints\n");exit(1);}
foreach(['app/includes/bootstrap.php','app/includes/config_loader.php','app/config/config.register.php','public/.htaccess','tools/check.php','public/assets/css/topup.css'] as $file) {
 if (!is_file($root.'/'.$file)) {fwrite(STDERR,"Missing: $file\n");exit(1);}
}
if (is_file($root.'/index.php')||is_file($public.'/check.php')||is_file($public.'/topup/_layout.php')) {
 fwrite(STDERR,"PHP source exposed under public webroot\n");exit(1);
}
echo "PASS: $count public endpoints map to private PHP handlers\n";
