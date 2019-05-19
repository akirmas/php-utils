<?php
namespace utils;

require_once(__DIR__.'/assoc.php');
use function \assoc\merge;
require_once(__DIR__.'/fs.php');
use function \fs\pathsResolver;

function tmstmp() {
  return date('Ymd-His_').rand();
}
function tmstmpObject($prefix = '') {
  $dateMap = [
    'year' => 'Y',
    'month' => 'm',
    'day' => 'd',
    'hour' => 'H',
    'minute' => 'i',
    'second' => 's'
  ];
  $tmstmp = time();
  $output = [];
  foreach($dateMap as $key => $format) {
    $output[$prefix.$key] = date($format, $tmstmp);
  }
  return $output;
}

function mergeJsonPaths($baseDir, $path, $filename = '') {
  return mergeJsons(...pathsResolver($baseDir, $path, $filename));
}

function mergeJsons(...$paths) {
  return merge(
    ...array_map(
      function($path) {
        return !file_exists($path)
        ? []
        : json_decode(file_get_contents($path), true);
      },
      $paths
    )
  );
}