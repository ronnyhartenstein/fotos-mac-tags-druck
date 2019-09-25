<?php

$opt = getopt("d:s:t:rhp:x:");
// var_dump($opt);
if (empty($opt['d']) || empty($opt['s']) || isset($opt['h'])) {
  $script = basename(__FILE__);
  print <<<HELP
Call: php $script -s '/path/to/foto-archiv-tagged' -d '/path/to/foto-print'
Options:
  -h            this help
  -s path/from  Source dir take photos from (no äöüß, no spaces!)
  -d path/to    Target dir to generate into (no äöüß, no spaces!)
  -r            Remove files in target dir if exists
  -t tag        Tag name to search for (default: Fotosammlung)
  -p "Holiday"  Text prefix written before each texts
  -x 2048       Shrink pictures to size 2048x2048 (optional)
HELP;
  exit;
}

$src = preg_replace('#/$#','',$opt['s']);
$trg = preg_replace('#/$#','',$opt['d']);

if (preg_match('/\s/', $src) || preg_match('/\s/', $trg)) {
  die("ERROR: spaces hurts the copy() routine (actually). Please rename path in -s and -d.");
}

$tag = !empty($opt['t']) ? $opt['t'] : 'Fotosammlung';

$text_pre = !empty($opt['p']) ? $opt['p'] : '';

print <<<OPTIONS
Options:
  Source: $src
  Target: $trg
  Tag:    $tag
OPTIONS;

// glob on $src
$src_files = get_src_files($src, $tag);

// keine gefunden?
if (empty($src_files)) {
  die("ERROR: no pictures found!");
}


// Filter for *.jpg
// print "\nfilter for jpg .. ";
// $src_files = array_filter($src_files, function($n) {
//   return preg_match('/\.jpe?g$/', $n);
// });
// print count($src_files).' left';

// test only one
//$src_files = array_slice($src_files, 0, 1);

// create target dir if not exists
if (!file_exists($trg)) {
  print "\ncreate target dir ..";
  mkdir($trg, 0755, true);
}

// temp. working dir
if (!file_exists('./tmp')) mkdir('./tmp');

// shrink pics?
$shrink = false;
if (!empty($opt['x'])) {
  if (!is_numeric($opt['x'])) {
    die("ERROR: shrink must be numeric!");
  }
  $shrink = $opt['x'];
}
print "\n  shrink: $shrink px";

// Number of pictures to process?
$num_sum = count($src_files);
$num_curr = 0;
print "\nstart processing ..\n";
foreach ($src_files as $src_file) {
  $num_curr++;

  $src_file = str_replace(['./',$src.'/'],['',''],$src_file);
  $trg_file = str_replace(['/','./',' '],['_','','_'],dirname($src_file))
            . '_' . basename($src_file);

  print "\n\n[$num_curr/$num_sum] $src_file -> $trg_file .. ";

  $src_file_real = $src.'/'.$src_file;
  $trg_file_real = $trg.'/'.$trg_file;

  // if exists remove or skip
  if (file_exists($trg_file_real)) {
    if (isset($opt['r'])) {
      print " remove it before .. ";
      unlink($trg_file_real);
    } else {
      print " exists .. skip! ";
      continue;
    }
  }

  // copy file
  // print "\nSRC: $src_file_real";
  // print "\nTRG: $trg_file_real";
  $ok = copy($src_file_real, $trg_file_real);
  if ($ok) { print " COPIED "; }
  else { print " FAILED "; continue; }

  if ($shrink) {
    shrink_pic($trg_file_real, $shrink);
  }

  // determine text from filename and EXIF
  $text = get_text_from_picture($text_pre, $src_file, $src_file_real);

  // write Text into picture
  write_filename_into_picture($text, $trg_file_real);
}

function get_src_files($src, $tag) {
  print "searching for tagged files .. ";
  $cwd = getcwd();
  chdir($src);
  $src_files = [];
  exec("tag -f '$tag' .", $src_files);
  chdir($cwd);
  //var_dump($src_files);
  print "found ".count($src_files);
  return $src_files;
}

function shrink_pic($file, $size) {
  exec_cmd("convert -resize {$size}x{$size} \"$file\" \"$file\"");
}

function get_text_from_picture($text_pre, $src_file, $src_file_real) {
  $text_file = str_replace(['./','lightroom','/# '],['','','/'],dirname($src_file)) . '/' . strtolower(basename($src_file));

  print " EXIF ";
  error_reporting(E_ALL ^ E_WARNING);
  $exif = exif_read_data($src_file_real, 'IFD0');
  error_reporting(E_ALL);

  if (!$exif || empty($exif['DateTimeOriginal'])) {
    print "WARN: DateTimeOriginal not found!";
    $text_zeit = get_text_time_from_filename($src_file, $text_pre);
  } else {
    $text_zeit = get_text_time_from_exifdatetime($exif, $text_pre);
    if (!$text_zeit) $text_zeit = get_text_time_from_filename($src_file, $text_pre);
  }
  if (!empty($text_zeit)) {
    // replace each timestamps in
    $regex_date = '[12]\d{3}\-[01]\d\-[0-3]\d';
    $text_file = preg_replace('/\s*'.$regex_date.'\s*/','', $text_file);
    $text = "$text_pre: $text_file ($text_zeit)";
  } else {
    $text = "$text_pre: $text_file";
  }
  $text = preg_replace('/\/{2,}/','/',$text);
// print "\n\tTEXT: $text\n";
  return $text;
}

function get_text_time_from_exifdatetime($exif, $text_pre) {
  list($date, $time) = explode(" ", $exif['DateTimeOriginal']);
  $date = implode('-',explode(':', $date));
  $timestamp = strtotime("$date $time");
  // no year in date when it is in $text_pre!
  if (strpos($text_pre, date('Y',$timestamp)) !== false) {
    $text_zeit = date('j.n. G:i', $timestamp);
  } else {
    // maybe year in EXIF is wrong in compare to text_pre
    if (preg_match('/\b([12]\d{3})\b/', $text_pre, $match)
    && $match[1] != date('Y', $timestamp)) {
      print " WARN: DateTimeOriginal seems wrong! ";
      $text_zeit = false;
    } else {
      $text_zeit = date('j.n.Y G:i', $timestamp);
    }
  }
  return $text_zeit;
}

function get_text_time_from_filename($src_file, $text_pre) {
  $regex_date = '[12]\d{3}\-[01]\d\-[0-3]\d';
  if (preg_match_all('/'.$regex_date.'/', $src_file, $matches)) {
    $date_relevant = array_pop($matches[0]);
    $date_parts = explode('-',$date_relevant);
    // no year in date when it is in $text_pre!
    if (strpos($text_pre, $date_parts[0]) !== false) {
      $text_zeit = ltrim($date_parts[2],'0').'.'.ltrim($date_parts[1],'0').'.';
    } else {
      $text_zeit = implode('.', array_reverse($date_parts));
    }
    return $text_zeit;
  } else {
    return '';
  }
}

function write_filename_into_picture($text, $img_file) {

  $font_ttf = './fonts/YanoneKaffeesatz-Regular.ttf';
  $font_size = 50;

  list($width, $height) = getimagesize($img_file);
  print " {$width}x{$height} ";

  print " write '$text' into pic ..";

  $md5 = substr(md5(uniqid()),0,4);
  $tmp_trans = "tmp/trans_stamp.$md5.png";
  $tmp_mask = "tmp/mask_mask.$md5.jpg";
  $tmp_text = "tmp/text_mask.$md5.png";
  $tmp_plain = "tmp/text_plain.$md5.utf8";

  write_text_into_tmp($text, $tmp_plain);

  list($font_size, $height_text, $an1,$an2,$an3) = get_annotate_sizes($width, $height);
  //print " font:{$font_size}px $an1,$an2,$an3 ";

  // text-mask: crete transparent picture
  $annotate = "-font $font_ttf -pointsize $font_size \
     -fill black   -annotate $an1 @$tmp_plain \
     -fill white   -annotate $an2 @$tmp_plain \
     -fill transparent   -annotate $an3 @$tmp_plain ";
  $width_text = $height > $width ? $height : $width;
  exec_cmd("convert -size {$width_text}x{$height_text} xc:transparent $annotate $tmp_trans");
  if (!file_exists($tmp_trans)) { die("ERROR: create $tmp_trans failed!"); }

  // text-Maske: same again with black background
  $annotate = "-font $font_ttf -pointsize $font_size \
     -fill white   -annotate $an1 @$tmp_plain \
     -fill white   -annotate $an2 @$tmp_plain \
     -fill black   -annotate $an3 @$tmp_plain";
  exec_cmd("convert -size {$width_text}x100 xc:black $annotate $tmp_mask");
  if (!file_exists($tmp_mask)) { die("ERROR: create $tmp_mask failed!"); }

  // get opacity and save it as PNG - the final maske
  exec_cmd("composite -compose CopyOpacity $tmp_mask $tmp_trans $tmp_text");
  if (!file_exists($tmp_text)) { die("ERROR: create $tmp_text failed!"); }

  // insert final text into our picture
  if ($height > $width) {
    exec_cmd("convert \"$img_file\" \
        -rotate -90 \
        -gravity SouthWest -draw \"image Over 0,-10 0,0 '$tmp_text'\"\
        -rotate 90 \
         \"$img_file\"");
  } else {
    exec_cmd("convert \"$img_file\" \
        -gravity SouthWest -draw \"image Over -10,-10 0,0 '$tmp_text'\"\
         \"$img_file\"");
  }
  print " DONE! ";

  unlink($tmp_trans);
  unlink($tmp_mask);
  unlink($tmp_text);
  unlink($tmp_plain);
}

function get_annotate_sizes($width, $height) {
  $s = function($x,$y) {
    return ['+'.($x-1).'+'.($y-1), '+'.($x+1).'+'.($y+1), '+'.$x.'+'.$y];
  };
  $ano_size = [
    800 => array_merge([25, 50], $s(20, 25)),
    //1024 => array_merge([35, 70], $s(15, 45)), // zu gross
    1024 => array_merge([25, 50], $s(25, 25)),
    2048 => array_merge([50, 100], $s(45, 65))
  ];
  $longest = $height > $width ? $height : $width;
  $sizes = [];
  foreach ($ano_size as $k => $v) {
    if (empty($sizes) || $k <= $longest) $sizes = $v;
  }
  return $sizes;
}

function write_text_into_tmp($text, $tmp_plain) {
  // put text in separate file to fix UTF8 issues - but there are stimm äöü problems
  //print "\nTEXT ORIG: $text";
  //$text = utf8_decode($text);
  //$text = iconv("UTF-8", "ISO-8859-15", $text);
  /*$text = str_replace(
    array_map(function($n){
      return utf8_encode($n);
    }, ['ä','ö','ü','ß'])
    ,['ae','oe','ue','ss']
    , $text);
  print "\nTEXT decode: $text";
  */
  //if (strlen($text) > 50) { $text = substr($text,0,50); }
  file_put_contents($tmp_plain, $text);
}

function exec_cmd($cmd) {
  $stdout = [];
  //print "\n".str_repeat('-',50)."\n$cmd\n";
  exec($cmd.' 2>&1', $stdout);
  //print "\n".join("\n",$stdout);
}
