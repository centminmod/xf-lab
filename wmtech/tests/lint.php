<?php
$root=$argv[1];$count=0;$errors=array();
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($files AS $file) {
 if(!$file->isFile() || strtolower($file->getExtension())!=='php') {continue;}
 $output=array();$status=0;exec(escapeshellarg(PHP_BINARY).' -d error_reporting=32767 -l '.escapeshellarg($file->getPathname()).' 2>&1',$output,$status);$count++;
 $text=implode("\n",$output);
 if($status || strpos($text,'Deprecated:')!==false) {$errors[]=array('file'=>$file->getPathname(),'output'=>$text);}
}
echo json_encode(array('php'=>PHP_VERSION,'files'=>$count,'errors'=>$errors),JSON_PRETTY_PRINT),"\n";
exit($errors?1:0);
