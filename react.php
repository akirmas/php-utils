<?php

namespace react;

// @body : HTML string
function dataPass($data, $assignTo = 'window') {
  $point = '</head>';
  $json = is_string($data) ? $data : json_encode($data);
  return "<script>Object.assign($assignTo, $json)</script>";
}

function inject($body, $script, $point = '</head>') {
  return preg_replace(
    "|{$point}|",
    "{$script}.{$point}",
    $body,
    1
  );
}

function resolveLinks($base, $index = 'index.html') {
  $dom = new \DOMDocument();
  $dom->loadHTMLFile("{$base}{$index}");
  $output = new \DOMDocument();

  foreach(
    $dom->getElementsByTagName('head')->item(0)->getElementsByTagName('link')
    as $link
  )
    $output->appendChild($output->importNode(absUrl($link, $base), true));
  foreach(
    $dom->getElementsByTagName('body')->item(0)->childNodes
    as $node
  )
    $output->appendChild($output->importNode(absUrl($node, $base), true));

  return $output->saveHTML();
}

function absUrl(&$node, $base) {
  $attrs = ['src', 'href'];
  foreach($attrs as $attr)
    if ($node->hasAttribute($attr))
      $node->setAttribute($attr,
        preg_replace(
          '|^\.|',
          $base,
          $node->getAttribute($attr)
        )
    );
  return $node;
}
