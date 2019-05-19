<?php

function closeAndExit($code = 0) {
  session_write_close();
  exit($code);
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

function getClientIp() {
  $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
  $i = 0;
  while($i < sizeof($ipKeys) && empty($_SERVER[$ipKeys[$i]]))
    $i++;
  return $i >= sizeof($ipKeys)
  ? ''
  : $_SERVER[$ipKeys[$i]];
}

function getRequestObject() {
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

function getResultOfMirroredToUrlRequest($url, $request, $verifyPeerSSL = 0) {
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
