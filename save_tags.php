<?php

$opt = getopt("d:j:t:");
// var_dump($opt);
if (empty($opt['d']) || empty($opt['j']) || empty($opt['t']) || isset($opt['h'])) {
  $script = basename(__FILE__);
  print <<<HELP
Call: php $script -d '/path/to/foto-archiv-tagged' -j 'files_tagges.json' -f 'MyTag'
Options:
  -h            this help
  -d path/to    Path with tagged files (no äöüß, no spaces!)
  -j            JSON file to safe into
  -t Tag        tag to search for
HELP;
  exit;
}

$dir = $opt['d'];
if (!file_exists($dir)) {
  die("ERROR: not a directory! $dir");
}
$json = $opt['j'];
if (file_exists($json)) {
  unlink($json);
}
$tag = $opt['t'];

print "\nsearching for tagged files .. ";
$cwd = getcwd();
chdir($dir);
$tagged_files = [];
exec("tag -f '$tag' .", $tagged_files);
chdir($cwd);
print "found ".count($tagged_files);

// remove dir
$dir_wo_slash = (substr($dir,-1,1) != '/' ? $dir.'/' : $dir);
//print "\ndir: $dir_wo_slash";
$tagged_files = array_map(function($n) use ($dir_wo_slash) {
  return str_replace($dir_wo_slash,'',$n);
}, $tagged_files);

$data = [
  'tag' => $tag,
  'dir' => $dir,
  'files' => $tagged_files
];
// save into JSON
print "\nwrite JSON into $json .. ";
file_put_contents($json, json_encode($data));
