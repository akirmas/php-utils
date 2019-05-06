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


function closeAndExit($code = 0) {
  session_write_close();
  exit($code);
}

function tryGet($source, $key, $defaultValue = null) {
    return gettype($source) === 'array' && array_key_exists($key, $source)
        ? $source[$key]
        : (gettype($source) === 'object' && property_exists($source, $key)
            ? $source->{$key}
            : $defaultValue
        );
}

function getRequestObject()
{
    $inputData = null;
    $inputMethod = null;
    if(array_key_exists('REQUEST_METHOD', $_SERVER)){
        $requestHeaders = getallheaders();
        switch($_SERVER['REQUEST_METHOD']){
            case 'POST':
                $inputMethod = 'POST';
                //Process POSTed forms and files here:
                if (array_key_exists('Content-Type', $requestHeaders)){
                    $contentType = $requestHeaders['Content-Type'];
                    switch($contentType){
                        case 'application/x-www-form-urlencoded':
                            $inputData = $_POST;
                            break;
                        case 'multipart/form-data-encoded':
                            if(empty($_FILES)) return false;
                            //TODO: Uploaded files processing here.
                            break;
                    }
                } else {
                    //We assume that JSON was POSTed and process it here:
                    $inputData = json_decode(file_get_contents('php://input'));
                    if(json_last_error() !== JSON_ERROR_NONE){
                        return false;
                    }
                }
                break;
            case 'GET':
                break;
        }
    } elseif(PHP_SAPI === 'cli') {
        $inputMethod = 'cli';
        $inputData = file_get_contents('php://stdin');
    } else {
        return false;
    }
    return (object)['inputMethod' => $inputMethod, 'inputData' => $inputData];
}

function getResultOfMirroredToUrlRequest($url, $request, $verifyPeerSSL = 0)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    if(is_string($request)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    } elseif(is_array($request) || is_object($request)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeerSSL);
    $response = curl_exec($ch);
    curl_close ($ch);
    return $response;
}
/*
function processSingleRef($singleRef, $key, &$mainJson)
{
    $singleRefDecoded = json_decode(file_get_contents($singleRef), true);
    if(!is_array($singleRefDecoded))
        throw new Exception('Can not decode single ref: ' . $singleRef);
    clueJsons(\assoc\merge($mainJson, $singleRefDecoded));
}

function clueJsons(&$mainJson)
{
    if(!isset($mainJson['#refs'])) return true;
    if(!is_array($mainJson['#refs'])) throw new Exception('#refs is not an array!');
    array_walk($mainJson['#refs'], 'processSingleRef', $mainJson);
}
*/

function clueJsons($json, $refPresent = false)
{
    if($refPresent){
        $refs = $json['#refs'];
        unset($json['#refs']);
        forEach($refs as $singleRef){
            if(!file_exists($singleRef)) throw new Exception('Not existent REF: ' . $singleRef);
            $singleRefJson = json_decode(file_get_contents($singleRef), true);
            if(!is_array($singleRefJson)) throw new Exception('Can not decode JSON from REF: ' . $singleRef);
            if(isset($singleRefJson['#refs'])){
                $json = \assoc\merge($json, clueJsons($singleRefJson, true));
            } else {
                $json = \assoc\merge($json, clueJsons($singleRefJson));
            }
        }
    } else {
        forEach($json as $key => $value){
            if(is_array($value) && isset($value['#refs'])) {
                $json[$key] = clueJsons($value, true);
            }
        }
    }
    return $json;
}

