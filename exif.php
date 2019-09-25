<?php

$file = $argv[1];

$exif = exif_read_data($file, 'IFD0');
if (!$exif) die("EXIF-data not found!");

//var_dump($exif);
foreach ($exif as $key => $val) {
  if ($key == 'MakerNote') continue;
  echo "\n$key: " . (is_array($val) ? 'Array' : $val);
}

list($date, $time) = explode(" ", $exif['DateTimeOriginal']);
$date = implode('-',explode(':', $date));
$timestamp = strtotime("$date $time");
$zeit = date('j.n.Y h:i', $timestamp);
print "\n----------\nDATETIME: $date $time\nZEIT: $zeit";
