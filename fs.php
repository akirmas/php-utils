<?php
namespace fs;

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

//TODO: change name
function pathsResolver($baseDir, $path, $filename = '', $extension = 'json', $index = 'index') {
  if (!is_array($path))
    $path = explode('/', (string) $path);
  $output = array_reduce(
    array_merge(
      [''],
      $path
    ),
    function ($acc, $folder) use ($filename, $extension, $index) {
      $path = $acc['path']
      .($folder === '' ? '' : '/')
      .$folder;
      return [
        'path' => $path,
        'files' => array_merge(
          $acc['files'],
          array_merge(
            ["{$path}.{$extension}", "{$path}/{$index}.{$extension}"],
            $filename === '' ? [] : ["{$path}/{$filename}.{$extension}"]
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

function contentOneOf(...$files) {
  foreach($files as $file)
    if (file_exists($file))
      return file_get_contents($file);
}
