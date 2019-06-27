<?php
declare(strict_types=1);
namespace assoc;

require_once(__DIR__.'/monads.php');

function mapKeys(
  array $assoc,
  array $keyMap,
  bool $flip = false,
  bool $keepUnmet = false,
  string $delimiter = ":",
  string $monadDelimiter = "::"
) :array {
  $result = [];
  if (!isESObject($assoc))
    return $result;
  foreach(assoc2table($assoc) as $row) {
    $lastIndex = 0;
    $met = false;
    do {
      $lastIndex++;
      //NB! Last iteration on {a:{b:c}} is [a,b,c] - feature
      $key = join($delimiter, array_slice($row, 0, $lastIndex));
      if (
        //TODO: in flip case should be 
        keyExists($flip ? flip($keyMap) : $keyMap, $key)
      ) {
        $met = true;
        $props = $flip
        ? keys(
          array_filter(
            $keyMap,
            function ($el) use ($key, $monadDelimiter) {
              return explode($monadDelimiter, $el)[0] === $key;
            }
          )
        )
        : [getValue($keyMap, $key)];
        foreach($props as $property) {
          $monads = explode($monadDelimiter, (string) $property);
          $property = array_shift($monads);
          
          $result[$property] = array_reduce(
            $monads,
            function($value, $monad) {
              //TODO: rethink for flip (issue #2)
              return call_user_func($monad, $value);
            },
            join2($delimiter, array_slice($row, $lastIndex))
          );
        }
          
      }
    } while ($lastIndex < count($row));

    if (!$keepUnmet || $met)
      continue;

    $lastIndex = count($row) - 1;
    $result[
      join2($delimiter, array_slice($row, 0, $lastIndex))
    ] = join2($delimiter, array_slice($row, $lastIndex));    
  }

  return $result;
}
 
function join2($delimiter, $arr) {
  $arr = array_values(array_filter(
    $arr,
    function($value) {
      return !is_null($value) && $value !== '';
    }
  ));
  switch(sizeof($arr)) {
    case 0:
      return null;
    case 1:
      return $arr[0];
    default:
      return join($delimiter, $arr);
  }
}

function mapValues(
  array $assoc,
  array $valuesMap,
  bool $keepUnmet = false,
  string $defaultKey = '$default'
) :array {
  $result = [];
  foreach((array) $assoc as $key0 => $value0) {
    if (isESObject($key0) || isESObject($value0)) {
      $result[$key0] = $value0;
      continue;
    }
    $key = (string) $key0;
    $value = (string) $value0;
    if (
      keyExists($valuesMap, $key)
      && (!isESObject($valuesMap[$key]))
    )
      $result[$key] = $valuesMap[$key];
    elseif (keyExists($valuesMap, [$key, $value]))
      $result[$key] = $valuesMap[$key][$value];
    elseif (keyExists($valuesMap, [$key, $defaultKey]))
      $result[$key] = $valuesMap[$key][$defaultKey];
    elseif ($keepUnmet)
      $result[$key] = $value;
  }
  return $result;
}

/** Tail precedence */
function merge(array ...$objects) :array {
  $base = (array) array_shift($objects);
  foreach($objects as $obj)
    if (isESObject($obj))
      foreach((array) $obj as $key => $value) {
        // Too many assigns
        $base[$key] = (
          !keyExists($base, $key)
          || !isESObject($value)
          || !isESObject($base[$key])
        )
        ? $value
        : merge($base[$key], $value);
      }
  return $base;
}

function tryGet($source, $key, $defaultValue = null) {
  return gettype($source) === 'array' && array_key_exists($key, $source)
  ? $source[$key]
  : (gettype($source) === 'object' && property_exists($source, $key)
      ? $source->{$key}
      : $defaultValue
  );
}

function flip($obj) {
  return array_flip((array) $obj);
}

function isESObject($var) {
  return !is_null($var) && (is_array($var) || is_object($var));
}

function assoc2table(array $assoc, $delimiter = null) {
  $rows = [];
  foreach($assoc as $key => $value) {
    $$key = is_null($delimiter) ? [$key] : explode($delimiter, $key);
    if (!is_array($value))
      array_push($rows, array_merge($$key, [$value]));
    else
      foreach(assoc2table($value) as $subRow)
        array_push($rows, array_merge($$key, $subRow));
  }
  return $rows;
}

function row2assoc(array $row) :array {
  $len = count($row);
  $result = [$row[$len - 2] => $row[$len - 1]];
  foreach(array_slice(array_reverse($row), 2) as $key)
    $result = [$key => $result];
  return $result;
}

function table2assoc(array $table) :array {
  $result = [];
  foreach($table as $row)
    merge($result, row2assoc($row));
  return $result;
}

function getComplexKey($source, $path, $default = null) {
  if (!is_array($path) || sizeof($path) === 0)
    return $source;
  if (is_array($source) && array_key_exists($path[0], $source))
    return getComplexKey($source[array_shift($path)], $path, $default);
  if (is_object($source)&& keyExists($source, $path[0]))
    return getComplexKey($source->{array_shift($path)}, $path, $default);
  return $default;
}

function splitKeysValues($source, $delimiter = ':', $result = []) {
  if (!isESObject($source))
    return $result;
  $keys = keys($source);
  if (sizeof($keys) <= 0)
    return $result;
  $key = $keys[0];
  $value = getValue($source, $key);
  deleteKey($source, $key);
  return splitKeysValues($source, $delimiter, merge(
    $result,
    row2assoc(
      explode($delimiter, join('', [$key, $delimiter, $value]))
    )
  ));
}

function splitKeys($source, $delimiter = ':') {
  $result = [];
  foreach(assoc2table($source) as $row) {
    $newRow = [];
    $value = array_pop($row);
    foreach($row as $chunk)
      array_push($newRow, ...explode($delimiter, $chunk));
    array_push($newRow, $value);
    $result = merge($result, row2assoc($newRow));
  }
  return $result;
}

function keyExists($source, $keys) {
  $keys = is_array($keys) ? $keys : [$keys];
  $key = array_shift($keys);

  return (
    is_array($source) && array_key_exists($key, $source)
    || is_object($source) && property_exists($source, $key)
  ) && (
    sizeof($keys) === 0
    || keyExists($source[$key], $keys)
  ); 
}

// TODO: add options $delimiter = ':', $deep = false, $parse = false
function keys($source) {
  return is_array($source)
  ? array_keys($source)
  : (is_object($source) 
  ? get_object_vars($source)
  : null
  );
}

function values($source, $delimiter = null) {
  return !isESObject($source)
  ? null
  : array_map(
    function ($el) use ($delimiter) {
      return is_null($delimiter)
      ? end($el)
      : end(
        explode(
          $delimiter,
          end($el)
        )
      );
    },
    assoc2table(
      is_object($source)
      ? get_object_vars($source)
      : $source
    )
  );
}

function values2($source) {
  return array_map(
    function($value) {
      return is_array($value) ? '' : $value;
    },
    $source
  );
}

function deleteKey(&$source, $key) {
  if (is_array($source))
    unset($source[$key]);
  elseif (is_object($source))
    unset($source->{$key});
  return $source;
}

function getValue($source, $key, $defaultValue = null) {
  if ($key === '' || is_array($key) && sizeof($key) === 0)
    return $source;
  if (is_array($key) && sizeof($key) >= 1)
    return getValue(
      getValue($source, $key[0], $defaultValue),
      array_slice($key, 1),
      $defaultValue
    );
  return !keyExists($source, $key)
  ? $defaultValue
  : (is_array($source)
  ? $source[$key]
  : (is_object($source)
  ? $source->{$key}
  : $defaultValue
  ));
}
function getValues($source, $keys, $defaultValue = null) {
  return array_map(
    function ($key) use ($source, $defaultValue) {
      return getValue($source, $key, $defaultValue);
    },
    $keys
  );
}

function formatString($format, $obj, $bracketLeft = '\{', $bracketRight = '\}') {
  if (!is_string($format) || !isESObject($obj))
    return $format;
  // or preg_filter - this is option
  $out = preg_replace(
    array_map(
      function($key) use ($bracketLeft, $bracketRight) {
        return join('', ['/', $bracketLeft, $key, $bracketRight, '/']);
      },
      keys($obj)
    ),
    values2($obj),
    $format
  );
  return $out;
}

function extractAssoc($pattern, $value, $lb = '\{', $rb = '\}') {
  $catchPattern = "/$lb([^$rb]*)$rb/";
  preg_match_all($catchPattern, $pattern, $vars);
  $vars = $vars[1];

  $extractPattern = preg_replace($catchPattern, '(.*)', $pattern);
  //TODO: chain extract - explode by $lb
  preg_match("/$extractPattern/", "x:q:y:z", $vals, PREG_UNMATCHED_AS_NULL);
  array_shift($vals);

  $output = [];
  for($i = 0; $i < sizeof($vars); $i++)
    $output[$vars[$i]] = $i < sizeof($vals) ? $vals[$i] : null; 
  return $output;
}

function fillValues($obj, $voc = null) {
  if (is_null($voc))
   $voc = $obj;
  $obj = (array) $obj;
  foreach($obj as $key => $value)
    $obj[$key] = formatString($value, $voc);
  return $obj;
}

function fillKeys($obj, $voc) {
  $result = [];
  foreach($obj as $key => $value)
    $result[formatString($key, $voc)] = $value;
  return $result;
}

function repairIndexes($assoc) {
  if (!is_array($assoc))
    return $assoc;
  $out = [];
  for($i=0; $i < sizeof($assoc); $i++) {
    if (!isset($assoc[$i]))
      return $assoc;
    $out[] = $assoc[$i];
  }
  return $out;
}

function repairIndexesRecursive($assoc) {
  if (!is_array($assoc))
    return $assoc;
  $out = [];
  foreach($assoc as $key => $value)
    $out[$key] = !is_array($value)
    ? $value
    : repairIndexesRecursive($value);
  return repairIndexes($out);
}

function filter(&$assoc, $fn) {
  if (!is_array($assoc))
    return $assoc;
  foreach(array_keys($assoc) as $key)
    if (!$fn($key, $assoc[$key]))
      unset($assoc[$key]);
  foreach(array_keys($assoc) as $key)
    filter($assoc[$key], $fn);
  return $assoc;
}