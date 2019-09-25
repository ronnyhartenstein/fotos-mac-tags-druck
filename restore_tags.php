<?php

$opt = getopt("d:j:t:");
// var_dump($opt);
if (empty($opt['j']) || isset($opt['h'])) {
  $script = basename(__FILE__);
  print <<<HELP
Call: php $script -j 'files_tagged.json' -d '/path/to/foto-archiv'
Options:
  -h            this help
  -j            JSON file to read from
  -t Tag        the tag
  -d path/to    Path with files to tag (no äöüß, no spaces!)
HELP;
  exit;
}

// JSON file is mandatory!
$json = $opt['j'];
if (!file_exists($json)) {
  die("ERROR: $json is not a file!");
}
print "\nreadings JSON .. ";
$content = file_get_contents($json);
if (empty($content)) {
  die("ERROR: $json is empty!");
}
$data = json_decode($content, true);
//var_dump($data);
if (empty($data)) {
  die("ERROR: no JSON content!");
}
if (!isset($data['files'])) {
  die("ERROR: no key 'files' found!");
}
if (empty($data['files'])) {
  die("ERROR: no items for key 'files'!");
}
$files = $data['files'];

// source directory: use param -d or from JSON data
if (!empty($opt['d'])) {
  $dir = $opt['d'];
} else if (!empty($data['dir'])){
  $dir = $data['dir'];
} else {
  die("ERROR: no directory found in JSON or param -d");
}
if (!file_exists($dir)) {
  die("ERROR: not a directory! $dir");
}

// tag name: use param -t or from JSON data
if (!empty($opt['t'])) {
  $tag = $opt['t'];
} else if (!empty($data['tag'])){
  $tag = $data['tag'];
} else {
  die("ERROR: no tag found in JSON or param -t");
}
if (empty($tag)) {
  die("ERROR: Please give a tag!");
}

// Let's GO!
$sum = count($files);
$n = 0;
$warns = 0;
print "\nprocess $sum ..";
foreach ($files as $file) {
  $n++;
  print "\r$n/$sum    ";
  $done = false;
  if (file_exists("$dir/$file")) {
    exec("tag -a '$tag' \"$dir/$file\"");
    $done = true;
  } else if (preg_match('/\.jpe?g$/i',$file)) {
    // search for NEF instead of JPG
    $file_nef = preg_replace('/\.jpe?g$/i','.NEF', $file);
    if (file_exists("$dir/$file_nef")) {
      exec("tag -a '$tag' \"$dir/$file_nef\"");
      $done = true;
    }
  }
  if (!$done) {
    print "\nWARN: $dir/$file (.nef) not found!";
    $warns++;
  }
}

print "\nDONE!" . (!empty($warns) ? " ($warns not found)" : "");
