<?php
if(PHP_SAPI!=='cli' || !is_file('/claims/.synthetic-claims-lab')){exit(1);}
$label=$argv[1];if(!in_array($label,array('base','wmtech'),true)){exit(1);}
$root='/claims/source/'.$label.'/upload';$files=0;$errors=array();$deprecated=array();
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) AS $file){
	if(!$file->isFile() || substr($file->getFilename(),-4)!=='.php'){continue;}$files++;
	$command=escapeshellarg(PHP_BINARY).' -d display_errors=1 -d error_reporting=-1 -l '.escapeshellarg($file->getPathname()).' 2>&1';$output=array();exec($command,$output,$status);$text=implode("\n",$output);$relative=substr($file->getPathname(),strlen($root)+1);
	if($status){$errors[$relative]=preg_replace('/ in .* on line /',' on line ',substr($text,0,400));}
	if(strpos($text,'Deprecated:')!==false){$deprecated[]=$relative;}
}
sort($deprecated);ksort($errors);$result=array('php'=>PHP_VERSION,'archive'=>$label,'phpFiles'=>$files,'syntaxErrors'=>$errors,'deprecatedFiles'=>$deprecated,'scope'=>'All shipped upload-tree PHP files, including dead code; no application error-reporting mask.');
file_put_contents('/claims/evidence/lint-'.$label.'-'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.json',json_encode($result,JSON_PRETTY_PRINT)."\n");echo json_encode($result,JSON_PRETTY_PRINT),"\n";
