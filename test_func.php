<?php
define("TEST_NUMBER", 2);

require_once 'index.php';

try {

    switch(TEST_NUMBER){
        case 1:
            $initialJson = 'initial.json';
            break;
        case 2:
            $initialJson = 'initial2.json';
            break;
        case 3:
            $initialJson = 'initial3.json';
            break;
    }
    $initialJsonPath = 'some/path';
    $jsonStr = file_get_contents( $initialJsonPath . '/' . $initialJson);
    $decodedJson = json_decode($jsonStr, true);
    if(!is_array($decodedJson)) throw new Exception('Can not decode main JSON.');
    $refsPresentInRoot = isset($decodedJson['$ref']) ? true : false;
    $processedJson = resolveRefs($decodedJson, $refsPresentInRoot, $initialJsonPath);
    unset($processedJson['$ref']);
    file_put_contents('clue_result.json', json_encode($processedJson) . "\n", FILE_APPEND);

} catch(Exception $e){
    echo ' >>>>> ' . $e->getMessage() . ' <<<<< ';
}
