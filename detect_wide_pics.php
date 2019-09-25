<?php

$opt = getopt("d:f:t:j:");
// var_dump($opt);
if (empty($opt['d']) || empty($opt['t']) || empty($opt['f'])) {
  $script = basename(__FILE__);
  print <<<HELP
Call: php $script -d '/path/to/foto-print' -f 3:2
Options:
  -h            this help
  -d path/to    Target dir to generate into (no äöüß, no spaces!)
  -t tag        Tag name to search for (default: Fotosammlung)
  -f [3:2|1.6]  Max factor sizing
  -j jsonfile   Write found files to this JSON (optional)
HELP;
  exit;
}

$dir = preg_replace('#/$#','',$opt['d']);

$tag = !empty($opt['t']) ? $opt['t'] : 'Fotosammlung';

if (preg_match('/^\d+:\d+$/', $opt['f'])) {
  list($w, $h) = explode(':', $opt['f']);
  $factor = $w / $h;
} else if (preg_match('/^\d+.\d+$/', $opt['f'])) {
  $factor = $opt['f'];
} else {
  die("\nError: factor must be 'x:y'");
}

// glob on $src
$dir_files = get_files($dir, $tag);
if (empty($dir_files)) {
  die("\nError: no files found");
}

$json_file = false;
if (!empty($opt['j'])) {
  $json_file = $opt['j'];
}

print "\nstart check ..\n";
$num_sum = count($dir_files);
$num_curr = 0;
$hits = [];
foreach ($dir_files as $dir_file) {
  $num_curr++;
  list($width, $height) = getimagesize($dir_file);
  print "\r[$num_curr/$num_sum]     ";
  if ($width > $height) {
    $fact_real = $width / $height;
    if ($fact_real > $factor) {
      if ($json_file) {
        $hits[] = ['file' => $dir_file, 'size' => "{$width}x{$height}", 'factor' => $fact_real];
      } else {
        echo "\nWARN: $dir_file .. {$width}x{$height}  -> factor {$fact_real}";
      }
    }
  } else {
    $fact_real = $height / $width;
    if ($fact_real > $factor) {
      if ($json_file) {
        $hits[] = ['file' => $dir_file, 'size' => "{$width}x{$height}", 'factor' => $fact_real];
      } else {
        echo "\nWARN: $dir_file .. {$width}x{$height} (hochkant) -> factor {$fact_real}";
      }
    }
  }
}

if ($json_file) {
  write_hits_list($json_file, $hits);
}

function get_files($src, $tag) {
  print "\nsearching for tagged files .. ";
  $cwd = getcwd();
  chdir($src);
  $src_files = [];
  exec("tag -f '$tag' .", $src_files);
  chdir($cwd);
  //var_dump($src_files);
  print "found ".count($src_files);
  return $src_files;
}

function write_hits_list($json_file, $hits) {
  print "\nwrite JSON file ..";
  file_put_contents($json_file, json_encode($hits));
  print " done";
}
