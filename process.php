<?php
if (!defined('PROCESSING_PIPES'))
  define('PROCESSING_PIPES', [["pipe", "r"],["pipe", "w"],["pipe", "w"]]);

function process($cmd, $options = []) {
  ['method' => $method, 'body' => $body, 'pipes' => $pipes, 'cwd' => $cwd, 'env' => $env] = $options
  + ['method' => PROCESSING_PIPES, 'body' => null, 'pipes' => [], 'cwd' => null, 'env' => null];
  
  [$return, $stdOut, $stdErr] = [-1, null, 'Couldn\'t open process'];

  $process = proc_open($cmd, $method, $pipes, $cwd, $env);
  if (!is_resource($process))
    proc_close($process);
  else {
    if (!is_null($body))
      fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $stdOut = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stdErr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return = proc_close($process);
  }  
  
  return [
    'status' => !$return,
    'error_code' => $return,
    'error_message' => $stdErr,
    'body' => $stdOut
  ];
}