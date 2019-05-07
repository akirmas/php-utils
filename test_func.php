<?php
require_once 'index.php';

try {
    $initialJsonPath = 'some/path';
    /*
    for($i = 1; $i <= 5; $i ++){
        $initialJson = "initial$i.json";
    }
    */
    $initialJson = "initial5.json";
    processSingleTest($initialJson, $initialJsonPath);
} catch(Exception $e){
    echo ' >>>>> ' . $e->getMessage() . ' <<<<< ';
}

function processSingleTest($initialJson, $initialJsonPath)
{
    $decodedJson = json_decode(file_get_contents( $initialJsonPath . '/' . $initialJson), true);
    if(!is_array($decodedJson)) throw new Exception('Can not decode main JSON.');
    $refsPresentInRoot = isset($decodedJson['$ref']) ? true : false;
    $processedJson = resolveRefs($decodedJson, $refsPresentInRoot, $initialJsonPath);
    unset($processedJson['$ref']);
    file_put_contents('clue_result.json', json_encode($processedJson) . "\n", FILE_APPEND);
}
