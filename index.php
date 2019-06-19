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
  return $output + ["{$prefix}gmt" => gmdate('D, d M Y H:i:s T', $tmstmp)];
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

//$refs have HEAD priority
function jsonFetch(string $path = '', $assoc = [], $refKey = '$ref', $keepRefKey = false, $refs = []) {
  if (is_array($assoc) && sizeof($assoc) === 0)
    $assoc = json_decode(file_get_contents($path), true);
  if (!is_array($assoc))
    return $assoc;

  $refsKey = "{$refKey}s";
  if (!empty($assoc[$refsKey]))
    $refs = merge($refs, $assoc[$refsKey]);
  if (!empty($assoc[$refKey]))
    $refs[] = $assoc[$refKey];
  
  if (!$keepRefKey) {
    unset($assoc[$refKey]);
    unset($assoc[$refsKey]);
  }

  foreach($refs as $ref) {
    if (empty($ref))
      continue;
    $refPath = $ref[0] === '/'
    ? $ref
    : realpath(
      (
        $path === ''
        ? getcwd()
        : dirname($path)
      )
      ."/$ref"
    );

    $ref = json_decode(file_get_contents($refPath), true);
    if (is_array($ref))
      $assoc = merge(
        jsonFetch($refPath, $ref),
        $assoc
      );
  }

  foreach($assoc as &$value)
    $value = jsonFetch($path, $value);
  return $assoc;
}

/**
 * @param string $csv Path. TODO: assoc
 * */
function csv2assoc($csv, $keepEmpty = false, $fLineHeaders = true) {
  $f = fopen($csv, 'r');
  if ($f === false)
    return null;
  $headers = [];
  $output = [];
  while (($row = fgetcsv($f)) !== false)
    if ($fLineHeaders && sizeof($headers) === 0)
      $headers = $row;
    else {
      $node = [];
      for($i = 0; $i < sizeof($row); $i++)
        if ($row[$i] !== '' || $keepEmpty)
          $node[$headers[$i]] = $row[$i];
      $output[] = $node;
    }
  fclose($f);
  return $output;
}