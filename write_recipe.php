#!/usr/bin/env php
<?php 
require 'vendor/autoload.php';


/* use Ramsey\Uuid\Uuid; */
/* use Ramsey\Uuid\Exception\UnsatisfiedDependencyException; */


use Rhumsaa\Uuid\Uuid;
use Rhumsaa\Uuid\Exception\UnsatisfiedDependencyException;

$fileArchive = "https://bagit.webdev.lib.ou.edu/";
$bagName = "Ailly_1506";
$bagCode = "73a65d2e407f49647ab893fa4080175a";
$itemLabel = "Tractatus de anima";


$bagManifest ="manifest-md5.txt";
$urlBase = $fileArchive ."/" .$bagName;


    
function makeRecipeJson( $urlBase, $bagCode, $bagName, $itemLabel)
{
    try{
	$json = Array();
	$json['recipe'] = Array();
	$json['recipe']['import'] = 'book';
	$json['recipe']['update'] = 'false';
	$json['recipe']['uuid'] = Uuid::uuid5($bagCode, $urlBase)->toString();
	$json['recipe']['label'] = $itemLabel;
	  
	$json['recipe']['metadata'] = Array();
	$json['recipe']['metadata']['marcxml'] = $bagName.'.xml';
	$json['recipe']['pages'] = Array();
    }
    catch (UnsatisfiedDependencyException $e) {
	echo "something is wrong";
    }

    return $json;

}


function pagecmp($a,$b){
    return strcmp($a["file"], $b["file"]);
}


function addContents($json, $manifest){
    global $urlBase;
    try{
	$handle = @fopen($manifest, "r");
	if($handle){
	    $index = 0;
	    while (($buffer = fgets($handle, 4096)) !== false) {
		$fileInfo = trim($buffer);
		//echo "file name = $fileInfo\n";
		if($fileInfo != "" && strpos($fileInfo, ".tif") > 0){
					
		    $fileInfoArr = explode(" ", $fileInfo);
		    $length = count($fileInfoArr);
		    $fileName = trim(explode("/", trim($fileInfoArr[$length-1]))[1]);

		    $json['recipe']['pages'][$index]['label'] = substr($fileName, 0, -4);
		    $json['recipe']['pages'][$index]['file'] = $fileName;
		    $json['recipe']['pages'][$index]['sha1'] = trim($fileInfoArr[0]);
		    $json['recipe']['pages'][$index]['uuid'] = Uuid::uuid5(trim($fileInfoArr[0]), $urlBase."/".$fileName)->toString();
		    $json['recipe']['pages'][$index]['exif'] = $fileName.".exif.txt";

		    $index++;
		}
	    }
	}

	$foo=array_values($json['recipe']['pages']);
	usort($foo, "pagecmp");
	$json['recipe']['pages'] = $foo; 

	if (!feof($handle)) {
	    echo "Error: unexpected fgets() fail\n";
	}
	fclose($handle);
    }
    catch(Exception $e){
	echo "Something is wrong here";
    }
    //print_r(json_encode($json));
    return json_encode($json, JSON_PRETTY_PRINT);
}

try {
    
    $json =makeRecipeJson( $urlBase, $bagCode, $bagName, $itemLabel);
    $json = addContents($json, $bagManifest);
    $file = fopen( $bagName . ".json", "w");
    fwrite($file, $json);


} catch (UnsatisfiedDependencyException $e) {

    // Some dependency was not met. Either the method cannot be called on a
    // 32-bit system, or it can, but it relies on Moontoast\Math to be present.
    echo 'Caught exception: ' .  "\n";

  }


