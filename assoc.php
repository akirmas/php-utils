<?php
declare(strict_types=1);
namespace assoc;

function mapKeys(
  object $assoc,
  object $keyMap,
  bool $keepUnmet = false,
  string $delimiter = ":"
) :object {
  $result = new \stdClass; // class{} or \stdClass 
  forEach(assoc2table(json_decode(json_encode($assoc), true)) as $row) {
    $lastIndex = 0;
    do {
      $lastIndex++;
      //NB! Last iteration on {a:{b:c}} is [a,b,c] - feature
      $key = join($delimiter, array_slice($row, 0, $lastIndex));
      
    } while (
      !property_exists($keyMap, $key)
      && $lastIndex < count($row)
    );
    if (property_exists($keyMap, $key))
      $key = $keyMap->{$key};
    else {
      if (!$keepUnmet)
        continue;
      $lastIndex = count($row) - 1;
      $key = join2($delimiter, array_slice($row, 0, $lastIndex));
    }
    $value = join2($delimiter, array_slice($row, $lastIndex));

    $matches = [];
    //Idea like \assoc.php:formatString but very different implementation
    if (preg_match('|^{(.*)}$|', $key, $matches))
      if (property_exists($assoc, $matches[1]))
        $key = $assoc->{$matches[1]};

    $result->{$key} = $value;
  }
  return $result;
}
 
function join2($delimiter, $arr) {
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
  object $assoc,
  object $valuesMap,
  bool $keepUnmet = false
) :object {
  $result = new \stdClass;
  forEach((array) $assoc as $key0 => $value0) {
    $key = (string) $key0;
    $value = (string) $value0;
    if (
      property_exists($valuesMap, $key)
      && (!in_array(gettype($valuesMap->{$key}), ['array', 'object']))
    )
      $result->{$key} = $valuesMap->{$key};
    elseif (
      property_exists($valuesMap, $key)
      && property_exists($valuesMap->{$key}, $value)
    )
      $result->{$key} = $valuesMap->{$key}->{$value};
    elseif ($keepUnmet)
      $result->{$key} = $value;
  }
  return $result;
}

function merge(...$objects) {
  $base = (array) array_shift($objects);
  forEach($objects as $obj)
    forEach((array) $obj as $key => $value) {
      $base[$key] = (
        !array_key_exists($key, $base)
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
        : json_decode(file_get_contents($path));
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

function flip($obj) :object {
  return (object) array_flip((array) $obj);
}

function isESObject($var) {
  return in_array(gettype($var), ['array', 'object']);
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