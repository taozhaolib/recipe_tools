#!/usr/bin/env php
<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;


if(! $itemfile =@ $argv[1] ) {
    exit("no item csv file specified\n");
}
if(! $csvfh = @fopen( "items.csv", "r" ) ) {
    exit("Couldn't open file\n");
}


# set up Guzzle client to make requests for marcxml 
$client = new Client(['base_uri' => 'http://52.0.88.11']); 


$first=TRUE;
while($line = fgetcsv($csvfh ) ){

    # skip first line, it's a header
    if($first== TRUE) {
	$first=FALSE;
	continue;
    }

    # get marcxml for a book
    $mssid= $line[1];
    $bagname = $line[2];
    fwrite(STDOUT, "processing mssid ".$mssid. " for bag ".$bagname."\n" );
    $response =$client->get('.', ['query' => ['bib_id' => $mssid]]);

    # save it to a file based on the bag name
    if( $outfh = @fopen( $bagname.".xml", "w" ) ) {
	fwrite( $outfh, $response->getBody());
	fclose( $outfh );
    }
}
