<?php

function import($path0, $dir = null) {
  $path = $path0;
  $pwd = getcwd();
  if (is_null($dir))
    $dir = $pwd;

  $path = preg_replace('/\.php$/', '', $path);
  switch(substr($path, 0, 1)) {
    case '.': // Relative to importer
      $path = $dir.substr($path, 1);
      break;
    case '/': // Absolute
      $module = $path;
      break;
    default: // Relative to project
      $path = $pwd."/{$path}";
  }
  if (file_exists("{$path}/index.php"))
    $path = "{$path}/index";
  $path = "{$path}.php";
  
  return require_once(
    !file_exists($path)
    ? $path0
    : $path
  );
}
