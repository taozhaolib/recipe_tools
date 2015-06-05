<?php 
require 'uuid/vendor/autoload.php';


use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

//require_once 'UuidRecipe.php';

$urlBase = "https://bagit.webdev.lib.ou.edu/Ailly_1506/";
$bagCode = "73a65d2e407f49647ab893fa4080175a";

function getJsonArray(){

	global $urlBase, $bagCode;

	try{
		$json = Array();
		$json['recipe'] = Array();
		$json['recipe']['import'] = 'book';
		$json['recipe']['update'] = 'false';
		$json['recipe']['uuid'] = Uuid::uuid5($bagCode, $urlBase)->toString();

		$json['recipe']['metadata'] = Array();
		$json['recipe']['metadata']['marcxml'] = 'book.xml';
		//$json['recipe']['metadata']['pagemeta'] = 'page.xml';

		$json['recipe']['pages'] = Array();
	}
	catch (UnsatisfiedDependencyException $e) {
		echo "something is wrong";
	}

	return $json;

}

function getManifestInfo($json){
	global $urlBase;
	try{
		$handle = @fopen("manifest.txt", "r");
		if($handle){
			$index = 0;
			while (($buffer = fgets($handle, 4096)) !== false) {
				$fileInfo = trim($buffer);
				//echo "file name = $fileInfo\n";
				if($fileInfo != "" && strpos($fileInfo, ".tif") > 0){
					
					$fileInfoArr = explode(" ", $fileInfo);
					$length = count($fileInfoArr);
					$fileName = trim(explode("/", trim($fileInfoArr[$length-1]))[1]);
		
					$json['recipe']['pages'][$index]['file'] = $fileName;
					$json['recipe']['pages'][$index]['sha1'] = trim($fileInfoArr[0]);
					$json['recipe']['pages'][$index]['uuid'] = Uuid::uuid5(trim($fileInfoArr[0]), $urlBase."/".$fileName)->toString();
					$json['recipe']['pages'][$index]['exif'] = $fileName.".exif.txt";

					$index++;
				}
			}
		}

		$json['recipe']['pages'] = array_values($json['recipe']['pages']);

		if (!feof($handle)) {
	        echo "Error: unexpected fgets() fail\n";
	    }
	    fclose($handle);
	}
	catch(Exception $e){
		echo "Something is wrong here";
	}
    //print_r(json_encode($json));
    return json_encode($json);
}

try {
	$json = getJsonArray();
	$json = getManifestInfo($json);
	$file = fopen("recipt.json", "w");
	fwrite($file, $json);

    // $uuidRecipt = new Ramsey\Uuid\UuidRecipe();
    // $uuidRecipt->setSeed("sohdfodf");
    // // Generate a version 5 (name-based and hashed with SHA1) UUID object
    // $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'php.net');
    // echo $uuid5->toString() . "\n"; // c4a760a8-dbcf-5254-a0d9-6a4474bd1b62

} catch (UnsatisfiedDependencyException $e) {

    // Some dependency was not met. Either the method cannot be called on a
    // 32-bit system, or it can, but it relies on Moontoast\Math to be present.
    echo 'Caught exception: ' .  "\n";

}
