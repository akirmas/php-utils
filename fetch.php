<?php
function fetch($url, $options = []) {
  $body = null;
  $method = !array_key_exists('method', $options)
  ? 'GET'
  : $options['method'];
  $headers = !array_key_exists('headers', $options)
  ? []
  : $options['headers'];

  if (array_key_exists('body', $options))
    $body = $options['body'];
  elseif (array_key_exists('data', $options)) {
    $data = $options['data'];
    switch(@$headers['Content-Type']) {
      case 'application/json':
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);
        break;
      case 'application/x-www-form-urlencoded':
        $body = http_build_query($data);
        break;
      default:
    }
  }  

  $ch = curl_init($url . (
    $method !== 'GET'
    ? ''
    : "?{$body}"
  ));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HEADER => 1,
    CURLOPT_HTTPHEADER => array_map(
      function($key) use ($headers) {
        $value = $headers[$key];
        return "{$key}: $value";
      },
      array_keys($headers)
    )
  ]);
  $body = curl_exec($ch);
  $error = null;
  $code = null;
  if ($body === false) {
    $error = curl_error($ch);
    $code = curl_errno($ch);
  }
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  $headers = [];
  $headerStrings = explode("\r\n", substr($body, 0, $header_size));
  $statusMessage = null;
  for($i = 0; $i < sizeof($headerStrings); $i++) {
    $header = explode(': ', $headerStrings[$i], 2);
    if (!$header[0])
      continue;
    if ($i === 0) 
      $statusMessage = $header[0];
    else
      $headers[$header[0]] = $header[1];
  }
  
  $status = null;
  if (!is_null($statusMessage))
    preg_match('/[0-9]{3}/', $statusMessage, $status);
  $status = @$status[0];
  //$contentType = @explode(";", $headers['Content-Type'], 2)[0];
  $body = substr($body, $header_size);
  $data = json_decode($body, true);
  return [
    'status' => $status,
    'statusMessage' => $statusMessage,
    'error_code' => $code,
    'error_message' => $error,
    'headers' => $headers,
    'body' => $body,
    'data' => $data
  ];
}