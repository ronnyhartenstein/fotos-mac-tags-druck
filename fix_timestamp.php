<?php


if (empty($argv[1])) { 
  print "Help: ".$argv[0]." [dir]"; 
  exit;
}
$dir = $argv[1];


$files = [];
exec('find '.escapeshellarg($dir).' -type f -iname "*.jpg" -or -iname "*.mp4" -or -iname "*.3gp"', $files);
$failed = [];
$success = [];
error_reporting(E_ALL ^ E_WARNING);

$n = 0;
foreach($files as $file) {
  $n++;
  if ($n % 10 == 0) print ' ';
  if ($n % 100 == 0) print "\n";

  $timestamp = false; $timestamp_source = '';
  $exif = exif_read_data($file, 'IFD0');
  if (!$exif
  || empty($exif['DateTimeOriginal'])
  ) {
    if (preg_match('/(20\d\d)(\d\d)(\d\d)_(\d\d)(\d\d)(\d\d)/', $file, $match)) {
      $timestamp = mktime($match[4], $match[5], $match[6], $match[2], $match[3], $match[1]);
      $timestamp_source = 'filename Ymd_His';
    } else if (preg_match('/(20\d\d)(\d\d)(\d\d)/', $file, $match)) {
      $timestamp = mktime(0, 0, 0, $match[2], $match[3], $match[1]);
      $timestamp_source = 'filename Ymd';
    } else {
      $failed[] = $file.' -> '.($exif ? 'no EXIF' : 'no DateTimeOriginal');
      print 'x'; continue;
    }
  } else {
    $timestamp_source = 'EXIF';
    list($date, $time) = explode(" ", $exif['DateTimeOriginal']);
    $date = implode('-',explode(':', $date));
    $timestamp = strtotime("$date $time");  
  }

  // Datei-Timestamp aktualisieren
  $return = 0;
  $output = [];
  $cmd = 'touch -t '.escapeshellarg(date('YmdHi.s', $timestamp)).' '.escapeshellarg($file);
  //print $cmd."\n";
  exec($cmd, $output, $return);
  if ($return == 0) {
    print '.';
    $success[] = $file.' -> '.date('Y-m-d H:i:s', $timestamp).' ('.$timestamp_source.')';  
  } else {
    print '-';
    $failed[] = $file.' -> FAILED: '.$cmd.' ('.$timestamp_source.')';
  }  
}

file_put_contents('timestamp_failed.log', implode("\n", $failed));
file_put_contents('timestamp_success.log', implode("\n", $success));