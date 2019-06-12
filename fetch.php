<?php
require_once(__DIR__.'/http.php');
use function \http\curlHeaders;

function fetch($url, $options = []) {
  $body = null;
  $method = !array_key_exists('method', $options)
  ? 'GET'
  : $options['method'];
  $headers = !array_key_exists('headers', $options)
  ? []
  : $options['headers'];

  $bodyless = in_array($method, ['GET', 'HEAD', 'OPTIONS']);

  if (array_key_exists('body', $options))
    $body = $options['body'];
  elseif (array_key_exists('data', $options)) {
    $data = $options['data'];
    switch(
      !empty($headers['Content-Type'])
      ? $headers['Content-Type']
      : ''
    ) {
      case 'application/json':
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);
        break;
      case 'application/x-www-form-urlencoded':
        $body = http_build_query($data);
        break;
      default:
        if ($method === 'GET')
          $body = http_build_query($data);
    }
  }  

  $reqUrl = $url . (
    $bodyless || empty($body)
    ? ''
    : (
      (
        strpos($url, '?') === false
        ? '?'
        : ''
      )
      .$body
    )
  );
  $headers = curlHeaders($headers);
  
  $ch = curl_init($reqUrl);
  curl_setopt_array($ch,
    (
      $method === 'GET'
      ? []
      : [CURLOPT_CUSTOMREQUEST => $method]
    ) + (
      $bodyless
      ? []
      : [CURLOPT_POSTFIELDS => $body]
    ) + [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => 1,
      //TODO: move to http module
      CURLOPT_HTTPHEADER => $headers
    ]
  );
  $respBody = curl_exec($ch);
  $error = null;
  $code = null;
  if ($respBody === false) {
    $error = curl_error($ch);
    $code = curl_errno($ch);
  }
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  $headers = [];
  $headerStrings = explode("\r\n", substr($respBody, 0, $header_size));
  $statusMessage = null;
  for($i = 0; $i < sizeof($headerStrings); $i++) {
    $header = explode(': ', $headerStrings[$i], 2);
    if (!$header[0])
      continue;
    if (sizeof($header) >= 2)
      $headers[$header[0]] = $header[1];
    elseif(preg_match("|^HTTP/([0-9\.]+\s+){2}|", $header[0]))
      $statusMessage = $header[0];
  }
  
  $status = null;
  if (!is_null($statusMessage))
    preg_match('/[0-9]{3}/', $statusMessage, $status);
  $status = @$status[0];

  $respBody = substr($respBody, $header_size);
  $resp = null;
  if (isset($headers['Content-Type']))
    switch($headers['Content-Type']) {
      case 'application/json':
        $resp = json_decode($respBody, true);
        break;
      case 'application/x-www-form-urlencoded':
        parse_str($respBody, $resp);
        break;
    }
  return [
    'status' => $status,
    'statusMessage' => $statusMessage,
    'error_code' => $code,
    'error_message' => $error,
    'headers' => $headers,
    'body' => $respBody,
    'data' => $resp
  ];
}
