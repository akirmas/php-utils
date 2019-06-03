<?php
declare(strict_types=1);
namespace assoc;

function mapKeys(
  array $assoc,
  array $keyMap,
  bool $flip = false,
  bool $keepUnmet = false,
  string $delimiter = ":"
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
        keyExists($flip ? flip($keyMap) : $keyMap, $key)
      ) {
        $met = true;
        foreach(
          (
            $flip
            ? keys(
              array_filter(
                $keyMap,
                function ($el) use ($key) {
                  return $el === $key;
                }
              )
            )
            : [getValue($keyMap, $key)]
          ) as $property
        )
          $result[
            $property
          ] = join2($delimiter, array_slice($row, $lastIndex));
      }
    } while ($lastIndex < count($row));

    if (!$keepUnmet || $met)
      continue;

    $lastIndex = count($row) - 1;
    $result[
      join2($delimiter, array_slice($row, 0, $lastIndex))
    ] = join2($delimiter, array_slice($row, $lastIndex));    
  }

  foreach($result as $key => $value) {
    $countedKey = formatString($key, $result);
    if ($countedKey !== $key) {
      unset($result[$key]);
      $result[$countedKey] = $value;
    }
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
  bool $keepUnmet = false
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
    elseif (keyExists($valuesMap, [$key, "#default"]))
      $result[$key] = $valuesMap[$key]['#default'];
    elseif ($keepUnmet)
      $result[$key] = $value;
  }
  return $result;
}

// Tail precedence
function merge(...$objects) {
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

function assoc2table(array $assoc) {
  $rows = [];
  foreach($assoc as $key => $value) {
    if (!is_array($value))
      array_push($rows, [$key, $value]);
    else
      foreach(assoc2table($value) as $subRow)
        array_push($rows, array_merge([$key], $subRow));
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
      explode($delimiter, "{$key}{$delimiter}{$value}")
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

function formatString($format, $obj) {
  return !is_string($format) || !isESObject($obj)
  ? $format
  : str_replace(
    array_map(
      function($key) {
        return "{{$key}}";
      },
      keys($obj)
    ),
    values($obj),
    $format
  );
}

function fillValues($obj, $voc) {
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


function resolveRefs($json, $refPresent = false, $parentJsonDir = '', $testScriptRelPath = '')
{
    if($refPresent){
        $refs = $json['$ref'];
        if(is_string($refs)) $refs = [$refs];
        unset($json['$ref']);
        foreach($refs as $singleRef){
            $isLocalFileSystemPath = true;
            $oldParentJsonDir = $parentJsonDir;
            if(strpos($singleRef, './') === 0){
                $singleRef = substr($singleRef, 1, strlen($singleRef) - 1);
                $pathToSubJson = $parentJsonDir . $singleRef;
                $parentJsonDir = dirname($pathToSubJson);
            } elseif(strpos($singleRef, '/') === 0) {
                $pathToSubJson = __DIR__ . '/' . $testScriptRelPath . $singleRef;
            } elseif(strpos($singleRef, 'http') === 0) {
                $isLocalFileSystemPath = false;
                $pathToSubJson = $singleRef;
            }
            if($isLocalFileSystemPath && !file_exists($pathToSubJson)){
                $parentJsonDir = $oldParentJsonDir;
                continue;
            }
            $singleRefJson = json_decode(file_get_contents($pathToSubJson), true);
            if(!is_array($singleRefJson)){
                $parentJsonDir = $oldParentJsonDir;
                continue;
            }
            $hasRef = isset($singleRefJson['$ref']) ? true : false;
            $json = merge($json, resolveRefs($singleRefJson, $hasRef, $parentJsonDir, $testScriptRelPath) );
        }
        foreach($json as $key => $value){
            $refFound = (is_array($value) && isset($value['$ref'])) ? true : false;
            $json[$key] = resolveRefs($value, $refFound, $parentJsonDir, $testScriptRelPath);
        }
    } else {
        foreach($json as $key => $value){
            $refFound = (is_array($value) && isset($value['$ref'])) ? true : false;
            $json[$key] = resolveRefs($value, $refFound, $parentJsonDir, $testScriptRelPath);
        }
    }
    return $json;
}
