<?php
declare(strict_types=1);
namespace assoc;

function mapKeys(
  array $assoc,
  array $keyMap,
  bool $keepUnmet = false,
  string $delimiter = ":"
) :array {
  $result = [];
  forEach(
    assoc2table(
      json_decode(json_encode($assoc), true)
    ) as $row
  ) {
    $lastIndex = 0;
    $met = false;
    do {
      $lastIndex++;
      //NB! Last iteration on {a:{b:c}} is [a,b,c] - feature
      $key = join($delimiter, array_slice($row, 0, $lastIndex));
      if (keyExists($keyMap, $key)) {
        $met = true;
        $result[
          getValue($keyMap, $key)
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

  forEach($result as $key => $value) {
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
  forEach((array) $assoc as $key0 => $value0) {
    $key = (string) $key0;
    $value = (string) $value0;
    if (
      keyExists($valuesMap, $key)
      && (!isESObject($valuesMap[$key]))
    )
      $result[$key] = $valuesMap[$key];
    elseif (
      keyExists($valuesMap, $key)
      && keyExists($valuesMap[$key], $value)
    )
      $result[$key] = $valuesMap[$key][$value];
    elseif ($keepUnmet)
      $result[$key] = $value;
  }
  return $result;
}

// Tail precedence
function merge(...$objects) {
  $base = (array) array_shift($objects);
  forEach($objects as $obj)
    if (isESObject($obj))
      forEach((array) $obj as $key => $value) {
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

function pathsResolver($baseDir, $path, $filename = '') {
  if (!is_array($path))
    $path = explode('/', (string) $path);
  $output = array_reduce(
    array_merge(
      [''],
      $path
    ),
    function ($acc, $folder) use ($filename) {
      $path = $acc['path']
      .($folder === '' ? '' : '/')
      .$folder;
      return [
        'path' => $path,
        'files' => array_merge(
          $acc['files'],
          array_merge(
            ["{$path}.json", "{$path}/index.json"],
            $filename === '' ? [] : ["{$path}/{$filename}.json"]
          )
        )
      ];
    },
    [
      'path' => is_array($baseDir) ? join('/', $baseDir) : $baseDir,
      'files' => []
    ]
  );
  return $output['files'];
}

function mergeJsonPaths($baseDir, $path, $filename = '') {
  return mergeJsons(...pathsResolver($baseDir, $path, $filename));
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

function row2assoc(array $row) {
  $len = count($row);
  $result = [$row[$len - 2] => $row[$len - 1]];
  foreach(array_slice(array_reverse($row), 2) as $key)
    $result = [$key => $result];
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
