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

// Adds basic top level info to a josn representation of a book 
//
function makeRecipeJson($bagName, $itemLabel, $outpath)
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
	$json['recipe']['metadata']['marcxml'] = $outpath.$bagName.'.xml';
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
function addPagesFromString($json, $manifest, $bagName, $bagSrc = "") {
    global $repoUuid;
    
    if(!empty($bagSrc)){
	$pathPrefix = $bagSrc."/".$bagName."/data/";
    }
    else{
	$pathPrefix = "";
    }

    $lines = explode(PHP_EOL, $manifest);

    // build json array
    $index=0;
    foreach($lines as $fileInfo) {

	if($fileInfo != "" && (strpos($fileInfo, ".tif") > 0 || strpos($fileInfo, ".tiff") > 0 || strpos($fileInfo, ".TIF") > 0) ){ 
	    $fileInfoArr = explode(" ", $fileInfo);
	    $length = count($fileInfoArr);

	    // don't add filenames that aren't at the top level
	    $fileEntry = trim($fileInfoArr[$length-1]);
	    $fileName = basename($fileEntry);
	    if (0 != strcmp( $fileEntry, "data/".$fileName)) {
		continue;
	    }
	    $fileHash = trim($fileInfoArr[0]);

	    $json['recipe']['pages'][$index]['label'] = substr($fileName, 0, -4);
	    $json['recipe']['pages'][$index]['file'] = $pathPrefix.$fileName;
	    // currently lying about what hashes we're using
	    $json['recipe']['pages'][$index]['md5'] = $fileHash;
	    $json['recipe']['pages'][$index]['uuid'] = Uuid::uuid5($repoUuid, $bagName."/data/".$fileName)->toString();
	    // Did not change the path for this file as it is not used so far
	    $json['recipe']['pages'][$index]['exif'] = $fileName.".exif.txt";
	    
	    $index++;
	    
	}
    }


    // files in manifest aren't sorted, so we need to sort them.
    $temp_json=array_values($json['recipe']['pages']);
    usort($temp_json, "pagecmp");
    
    // update labels to reflect image sequence order. 
    for($pcnt=0;$pcnt < count($temp_json); $pcnt++ ) {
	$temp_json[$pcnt]['label']="Image " .($pcnt+1) ;
    }

    $json['recipe']['pages'] = $temp_json;
    return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}



if(! $itemfile =@ $argv[1] ) {
    exit("No item csv file specified.\n");
}

if(! $outpath =@ $argv[2] ) {
    exit("No output path specified.\n");
}


if(! $bagSrc =@ $argv[3] ) {
    exit("No source folder for bags specified.\n");
}

if(! $csvfh = @fopen( $itemfile, "r" ) ) {
    exit("Couldn't open file: $php_errormsg\n");
}

if(! is_dir($outpath)) {
    exit("output path isn't a directory\n");
}

// If we're using remote bags, set up Guzzle client to make requests for bag manifest
$bagClient= NULL;
if (substr( $bagSrc, 0, 7 ) === "http://" || (substr( $bagSrc, 0, 8 ) === "https://")) {
    $bagClient = new Client(['base_uri' => $bagSrc,
			     'auth' => $FA_account]);
}
elseif(! is_dir($bagSrc)) {
    exit("bag src folder isn't a directory\n");
}

// Likewise for marcxml
$marcClient = new Client(['base_uri' => 'http://52.0.88.11']); 

$count=0;
while($line = fgetcsv($csvfh) ){
    $count++;
    
    // skip first line, it's a header
    if($count < 2 ) {
	continue;
    }


    $mssid="";
    $label="";
    $bagName="";
    
    $csv_err="";
    if(""!=$line[9]) {
	$label = $line[9];// human readable name
    }  else {
	$csv_err .= ", missing label";
    }

    if(""!=$line[1]) {
	$mssid = $line[1]; // alma manuscript id
    }    
    else {
	$csv_err .= ", missing mssid ";
    }
    if(""!=$line[3]) {
	$bagName = $line[3]; // bagName from digilab
    } 
    else {
	$csv_err .=  ", missing bag name" ;
    }

    if ("" !=$csv_err) {
	print "ERROR line ".$count.$csv_err ."\n";
	continue;
    }



    try {

    
        print "Processing $bagName\n";

	// Get the list of files to include from the bag manifest.  If
	// we're set up to work with remote bags, do that, otherwise
	// open local files.

	$manifestString="";
	if (! NULL === $bagClient) {
	    $response = $bagClient->get("./$bagName/manifest-md5.txt");
	    $manifest = $response->getBody();
	    $manifestString = $manifest->getContents();
	} else {
	    $manifestString = file_get_contents( "$bagSrc/$bagName/manifest-md5.txt");
	}
	

	$json = makeRecipeJson($bagName, $label, $outpath);
	$json = addPagesFromString($json, $manifestString, $bagName, $bagSrc);
	$file = fopen( $outpath."/".$bagName . ".json", "w");

	fwrite( $file, $json);
	fclose( $file );

	$response = $marcClient->get(".", ['query' => ['bib_id' => $mssid]]);

	// save it to a file based on the bag name

	$outfh = fopen( $outpath."/".$bagName.".xml", "w" );
	fwrite( $outfh, $response->getBody());
	fclose( $outfh );

	
    } catch (ClientException $e) {
	$badcode = $e->getResponse()->getStatusCode();
	$baduri = $e->getRequest()->getUri();

        print "ERROR getting data for $bagName \n";
	print "Status: $badcode ";
	print "URI: $baduri \n";


    } catch (RequestException $e) {

        print "ERROR with network connection for $bagName \n";
	print $e->getRequest()->getUri();
	print "\n";
  
    }

    
}
