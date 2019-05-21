<?php
namespace asserts;
require_once(__DIR__.'/assoc.php');

// condition(A, B) === A condition B
function it($a, $condition, $b) {
  return call_user_func(__NAMESPACE__."\\{$condition}", $a, $b);
}

// General
function equal($a, $b) {
  return $a == $b;
}
function strictEqual($a, $b) {
  return $a === $b;
}
function less($a, $b) {
  return $a < $b;
}
function more($a, $b) {
  return less($b, $a);
}

// Strings
function startsWith($a, $b) {
  return substr($a, 0, strlen($b)) === $b; 
}
function endsWith($a, $b) {
  return substr($a, -strlen($b)) === $b; 
}
function match($a, $pattern) {
  return preg_match($pattern, $a);
}

// Arrays
function includedIn($value, $array) {
  return is_array($value)
  ? sizeof(array_diff($value, $array)) === 0
  : in_array($value, $array);
}

// Objects
function subset($sub, $set) {
  return sizeof(array_diff_assoc($sub, $set)) === 0;
}
function superset($super, $set) {
  return subset($set, $super);
}