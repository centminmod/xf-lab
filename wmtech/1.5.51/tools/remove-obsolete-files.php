<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try
{
 if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '--apply'))
 { throw new RuntimeException('Usage: php tools/remove-obsolete-files.php /path/to/forum [--apply]'); }
 $root = realpath($argv[1]);
 if (!$root || !is_file($root . '/library/XenForo/Application.php')) { throw new RuntimeException('Expected an XF1 forum root.'); }
 if (is_file($root . '/src/XF.php')) { throw new RuntimeException('XF2 files detected. Run this XF1 cleanup only before uploading XF2.'); }
 $apply = $argc === 3;
 $results = array();
 $allowed = array('fb_channel.php','library/Zend/Service/WindowsAzure','js/swfupload','js/videojs/video-js.swf');
 foreach ($allowed AS $relative)
 {
  $parent = $root;
  foreach (array_slice(explode('/', $relative), 0, -1) AS $part)
  {
   $parent .= '/' . $part;
   if (is_link($parent)) { throw new RuntimeException('Refusing a linked ancestor: ' . $relative); }
  }
 }
 foreach ($allowed AS $relative)
 {
  $path = $root . '/' . $relative;
  $exists = file_exists($path) || is_link($path);
  $removed = 0;
  if ($apply && $exists)
  {
   if (is_dir($path) && !is_link($path))
   {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items AS $item)
    {
     $ok = $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
     if (!$ok) { throw new RuntimeException('Cannot remove an obsolete item.'); }
     $removed++;
    }
    if (!rmdir($path)) { throw new RuntimeException('Cannot remove obsolete directory.'); }
   }
   else if (!unlink($path)) { throw new RuntimeException('Cannot remove obsolete file.'); }
   $removed++;
  }
  $results[] = array('path'=>$relative, 'present'=>$exists, 'removedItems'=>$removed);
 }
 echo json_encode(array('applied'=>$apply, 'paths'=>$results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
}
catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
