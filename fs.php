<?php
function mkdir2(...$folders) {
  $dir = join(DIRECTORY_SEPARATOR, $folders);
  if (!file_exists($dir)) mkdir($dir, 0777, true);
  return realpath($dir);
}

function inFolder($root, $sub) {
  if (!file_exists($root) || !file_exists($sub)) return 0;
  return strpos(realpath($sub), realpath($root)) === 0;
}

function readNestedFile($root, $relativePath) {
  $file = "$root/$relativePath";
  if (!inFolder($root, $file)) {
    throw new Exception('File not exists or permission denied');
  } else
    return file_get_contents($file);
}

function scandir2($root) {
  return array_filter(
    scandir($root),
    function($folder){ return !in_array($folder, ['.', '..']); }
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
