<?php
// Avoid naming conflict with static $_property; and dynamic $property;
class Objective extends ArrayObject {
  static function attach($me, $opts) {
    foreach($opts as $property => $value)
      $me->$property = $value;
    return $me;
  }
  
  static function getter($me, $self, $property) {
    $staticProp = "_$property";
    return !is_null($me)
    && property_exists($me, $property)
    ? $me->$property
    : (
      property_exists($self, $staticProp)
      ? $self::$$staticProp
      : null
    );
  }

  function __construct($opts = []) {
    parent::__construct($opts, ArrayObject::STD_PROP_LIST|ArrayObject::ARRAY_AS_PROPS);
    //self::attach($this, $opts);
  }
  function __get($property) {    
    return self::getter(isset($this) ? $this : null, __CLASS__, $property);
  }
}