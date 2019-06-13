<?php
function base64Encode($source) {
  return base64_encode($source);
}
function sha256($source) {
  return hash('sha256', $source, true);
}