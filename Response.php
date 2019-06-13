<?php
declare(strict_type=1);
class Response {
  public $status;
  public $statusMessage;
  public $error_code;
  public $error_message;
  public $headers;
  public $body;
  public $data;
  
  function __construct(
    int $status, 
    string $statusMessage, 
    int $error_code, 
    string $error_message, 
    array $headers, 
    string $body, 
    array $data
  ) {
    foreach(
      ['status', 'statusMessage', 'error_code', 'error_message', 'headers', 'body', 'data']
      as $property
    )
      $this->{$property} = $$property;
  }

  function __set($name, $value) {
    return;
  }  
}