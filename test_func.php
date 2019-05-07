<?php
require_once 'index.php';

try {
    $initialJsonPath = 'some/path';
    for($i = 1; $i <= 7; $i ++){
        processSingleTest($i, $initialJsonPath);
    }
} catch(Exception $e){
    echo ' >>>>> ' . $e->getMessage() . ' <<<<< ';
}

function processSingleTest($testNumber, $initialJsonPath)
{
    $initialJson = "initial$testNumber.json";
    $decodedJson = json_decode(file_get_contents( $initialJsonPath . '/' . $initialJson), true);
    if(!is_array($decodedJson)) throw new Exception('Can not decode main JSON.');
    $refsPresentInRoot = isset($decodedJson['$ref']) ? true : false;
    $processedJson = resolveRefs($decodedJson, $refsPresentInRoot, $initialJsonPath);
    unset($processedJson['$ref']);
    ob_start();
    echo "TEST NUMBER: $testNumber\n";
    print_r($processedJson);
    $out = ob_get_contents();
    //file_put_contents('clue_result.json', json_encode($processedJson) . "\n", FILE_APPEND);
    file_put_contents('clue_result.json', $out . "\n", FILE_APPEND);
    ob_end_clean();
}
