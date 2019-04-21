<?php
function string2word($alphabet, $str) {
  return array_map(
    function($letter) use ($alphabet){
      return strpos($alphabet, $letter);
    },
    array_reverse(str_split($str))
  );
}

function word2string($alphabet, $word) {
  return join('',
    array_reverse(
      array_map(
        function ($letter) use ($alphabet) {
          return $alphabet[$letter];
        },
        $word
      )
    )
  );
}

function wordSum($base, ...$words) {
  $lenS = max(array_map(
    function($word) {
      return sizeof($word);
    },
    $words
  ));
  $addition = 0;
  $result = [];
  for($i = 0; $i < $lenS; $i++) {
    $digit = array_reduce(
      $words,
      function($result, $word) use ($i) {
        return $result + (
          $i >= sizeof($word) ? 0 : $word[$i]
        );
      },
      $addition
    );
    
    array_push($result, $digit % $base);
    $addition = intdiv($digit, $base);
  }
  if ($addition !== 0)
    array_push($result, $addition);
  return $result;
}

function wordCoef($base, $coef, $word) {
  $len = sizeof($word);
  $addition = 0;
  $result = [];
  for($i = 0; $i < $len; $i++) {
    $digit = $addition + $coef * $word[$i];
    array_push($result, $digit % $base);
    $addition = intdiv($digit, $base);
  }
  if ($addition !== 0)
    array_push($result, $addition);
  return $result;
}

function wordMultiply($base, $word1, $word2) {
  $result = [];
  for ($i = 0; $i < sizeof($word2); $i++) {
    $result = wordSum($base,
      $result,
      array_merge(
        array_fill(0, $i, 0),
        wordCoef($base, $word2[$i], $word1)
      )
    );
  }
  return $result;
}

function basing($base, $value) {
  $result = [];
  while ($value > 0) {
    array_push($result, $value % $base);
    $value = intdiv($value, $base);
  }
  return $result;
}

function wordCompare($word1, $word2) {
  $len1 = sizeof($word1);
  $len2 = sizeof($word2);
  if ($len1 === 0 && $len2 === 0)
    return 0;
  $top1 = end($word1);
  $top2 = end($word2);
  return $len1 !== $len2
  ? 1 + ($len2 > $len1)
  : (
    $top1 !== $top2
    ? 1 + ($top2 > $top1)
    : wordCompare(
      array_slice($word1, 0, $len1 - 1),
      array_slice($word2, 0, $len2 - 1)
    )
  );
}

function baseChange($base, $from, $word) {
  $result = [];
  $basing = basing($base, $from); 
  $power = [1];
  $result = [];
  for ($i = 0; $i < sizeof($word); $i++) {
    $result = wordSum($base,
      $result,
      wordCoef($base,
        $word[$i],
        $power
      )
    );
    $power = wordMultiply($base, $power, $basing);
  }
  return $result;
}

function baseChangeString($newAlphabet, $alphabet, $word) {
  return word2string(
    $newAlphabet,
    baseChange(
      sizeof2($newAlphabet),
      sizeof2($alphabet),
      string2word($alphabet, $word)  
    )
  );
}

// Array and string has commom insterface $s[$i] but not common size
function sizeof2($source) {
  return is_array($source)
  ? sizeof($source)
  : (is_string($source)
    ? strlen($source)
    : null
  );
}
