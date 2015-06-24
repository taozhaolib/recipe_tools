#!/usr/bin/env php
<?php 
require 'vendor/autoload.php';
require 'credentials.php'; // password file



//   Cant use new namespace yet, but the namespace change will happen
//   soon, so leaving declarations in place 
// use Ramsey\Uuid\Uuid; 
// use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

use Rhumsaa\Uuid\Uuid;
use Rhumsaa\Uuid\Exception\UnsatisfiedDependencyException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;



// these should be constant
// generating them purely for documentation
$repoUuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'repository.ou.edu');
$repoSha =sha1($repoUuid);
assert(strcmp("eb0ecf41-a457-5220-893a-08b7604b7110",$repoUuid)==0);
assert(strcmp("1639fb25f09f85a3b035bd7a0a62b2a9c7e00c18",$repoSha)==0);



    
function makeRecipeJson(  $bagName, $itemLabel)
{

    global $repoUuid;
    global $repoSha;

    
    try{
	$json = Array();
	$json['recipe'] = Array();
	$json['recipe']['import'] = 'book';
	$json['recipe']['update'] = 'false';
	// This is the book uuid and will be used in the book uri
	$json['recipe']['uuid'] = Uuid::uuid5($repoUuid, $bagName)->toString();
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

// We'll sort pages based on filename scanned pages will be named to
//  sort in corect order
function pagecmp($a,$b){
    return strcmp($a["file"], $b["file"]);
}



// given a json item record and a sting representation of a manifest
// iterates through the manifest and adds page records 
function addPagesFromString($json, $manifest, $bagName) {
    global $repoUuid;
    
    $lines = explode(PHP_EOL, $manifest);

    // build json array
    $index=0;
    foreach($lines as $fileInfo) {


	if($fileInfo != "" && strpos($fileInfo, ".tif") > 0){
	    
	    $fileInfoArr = explode(" ", $fileInfo);
	    $length = count($fileInfoArr);
	    $fileName = basename(trim($fileInfoArr[$length-1])) ;

	    $fileHash = trim($fileInfoArr[0]);

	    
	    $json['recipe']['pages'][$index]['label'] = substr($fileName, 0, -4);
	    $json['recipe']['pages'][$index]['file'] = $fileName;
	    // currently lying about what hashes we're using
	    // $json['recipe']['pages'][$index]['md5'] = $fileHash;
	    $json['recipe']['pages'][$index]['sha1'] = $fileHash;
	    $json['recipe']['pages'][$index]['uuid'] = Uuid::uuid5($repoUuid, $bagName."/data/".$fileName)->toString();
	    $json['recipe']['pages'][$index]['exif'] = $fileName.".exif.txt";
	    
	    $index++;
	    
	}
    }

    $temp_json=array_values($json['recipe']['pages']);
    usort($temp_json, "pagecmp");
    $json['recipe']['pages'] = $temp_json; 

    return json_encode($json, JSON_PRETTY_PRINT);
}



if(! $itemfile =@ $argv[1] ) {
    exit("No item csv file specified.\n");
}

if(! $outpath =@ $argv[2] ) {
    exit("No output path specified.\n");
}

if(! $csvfh = @fopen( $itemfile, "r" ) ) {
    exit("Couldn't open file: $php_errormsg\n");
}

if(! is_dir($outpath)) {
    exit("output path isn't a directory\n");
}



// Set up Guzzle client to make requests for marcxml 
$bagClient = new Client(['base_uri' => 'https://bagit.lib.ou.edu/UL-BAGIT/',
		      'auth' => $FA_account]);

$marcClient = new Client(['base_uri' => 'http://52.0.88.11']); 


$count=0;
while($line = fgetcsv($csvfh ) ){
    $count++;
    
    // skip first line, it's a header
    if($count < 2 ) {
	continue;
    }


    $mssid="";
    $label="";
    $bagName="";
    
    $csv_err="";
    if(""!=$line[3]) {
	$label = $line[3];// human readable name
    }  else {
	$csv_err .= ", missing label";
    }

    if(""!=$line[5]) {
	$mssid = $line[5]; // alma manuscript id
    }    
    else {
	$csv_err .= ", missing mssid ";
    }
    if(""!=$line[6]) {
	$bagName = $line[6]; // bagName from digilab
    } 
    else {
	$csv_err .=  ", missing bag name" ;
    }

    if ("" !=$csv_err) {
	print "ERROR line ".$count.$csv_err ."\n";
	continue;
    }



    try {
	$response = $bagClient->get("./$bagName/manifest-md5.txt");

	$manifest = $response->getBody();
	$manifestString = $manifest->getContents();

	$json =makeRecipeJson( $bagName, $label);
	$json = addPagesFromString($json, $manifestString, $bagName);
	$file = fopen( $outpath."/".$bagName . ".json", "w");

	fwrite( $file, $json);
	fclose( $file );

    } catch (ClientException $e) {
	$badcode = $e->getResponse()->getStatusCode();
	$baduri = $e->getRequest()->getUri();

        print "ERROR getting MANIFEST for $bagName. ";
	print "Status: $badcode ";
	print "URI: $baduri \n";
    }

    try {

	$response = $marcClient->get(".", ['query' => ['bib_id' => $mssid]]);

	// save it to a file based on the bag name

	$outfh = fopen( $outpath."/".$bagName.".xml", "w" );
	fwrite( $outfh, $response->getBody());
	fclose( $outfh );

	
    } catch (ClientException $e) {
	$badcode = $e->getResponse()->getStatusCode();
	$baduri = $e->getRequest()->getUri();

        print "ERROR getting MARC for $bagName.";
	print "Status: $badcode ";
	print "URI: $baduri \n";
    }

    
}
