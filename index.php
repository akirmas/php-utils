<?php
require_once(__DIR__.'/assoc.php');

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

function fileDelivery($root, $relativePath, $contentType) {
  try {
    $content = readNestedFile($root, $relativePath);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    header("Content-Type: $contentType");
    echo $content;  
  } catch (Exception $err) {
    http_response_code(404);
  }
}

function scandir2($root) {
  return array_filter(
    scandir($root),
    function($folder){ return !in_array($folder, ['.', '..']); }
  );
}

function getClientIp() {
  $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
  $i = 0;
  while($i < sizeof($ipKeys) && empty($_SERVER[$ipKeys[$i]]))
    $i++;
  return $i >= sizeof($ipKeys)
  ? ''
  : $_SERVER[$ipKeys[$i]];
}

function tmstmp() {
  return date('Ymd-His_').rand();
}

function formatString($format, $obj) {
  return !is_string($format) || !\assoc\isESObject($obj)
  ? $format
  : str_replace(
    array_map(
      function($key) {
        return "{{$key}}";
      },
      \assoc\keys($obj)
    ),
    \assoc\values($obj),
    $format
  );
}

function fillValues($obj, $voc) {
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


function closeAndExit($code = 0) {
  session_write_close();
  exit($code);
}
