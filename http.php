<?php
namespace http;

function closeAndExit($code = 0) {
  session_write_close();
  exit($code);
}

function input() {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input))
    $input = [];
  
  if (empty($_SERVER['argv']) || empty($_SERVER['argv']))
    $argv = [];
  else
    $argv = json_decode($_SERVER['argv'][1], true);
  if (is_null($argv))
    $argv = [];
  $input = $_REQUEST + $argv + $input;
  return $input;
}

function output($output) {
  $headers = @$output['headers'];
  if (is_array($headers))
    setHeaders($headers);
  if (!empty($output['status']))
    http_response_code($output['status']);

  if (!empty($output['body']) && is_string($output['body']))
    return $output['body'];

  $contentType = @$headers['Content-Type'];
  $contentType = is_null($contentType) ? 'application/json' : $contentType;
  $data = @!empty($output['data']) ? $output['data'] : $output['body'];
  switch($contentType) {
    case 'text/html': 
      return join('', $data);
    case 'application/json': 
      return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    default:
  }
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

function setHeaders($headers, $replace = true) {
  if (headers_sent())
    return;
  foreach($headers as $key => $value)
      header(
        is_int($key)
        ? $value
        : "{$key}: {$value}",
        $replace
      );
}

function ip2country($ip) {
  $ip2countryPath = __DIR__.'/http/IP2LOCATION-LITE-DB1.CSV';
  if (!file_exists($ip2countryPath))
    return '';
  $ipInt = sprintf("%u", ip2long($ip));  
  $f = fopen($ip2countryPath, 'r');
  $countryISO = null;
  $country = null;
  while(!feof($f)) {
    $row = fgetcsv($f);
    if ($row[0] > $ipInt || $row[1] < $ipInt)
      continue;
    $countryISO = $row[2];
    $country = $row[3];
    break;
  }
  fclose($f);
  return [
    'name' => $country,
    'iso' => $countryISO
  ];
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
