# OU Recipe Tools

Internal scripts to prepare recipe files and metadata for importing items from our File Archive to our Islandora repository. You probably don't care about this if you don't work for OU Libraries.



## write_recipes.php

Creates recipe files and gets marcxml for import. 

Requires:

* [ramsey/uuid](https://github.com/ramsey/uuid)
* [GuzzleHttp](http://guzzle.readthedocs.org/)

Use composer to get them. (`composer install`)

To get recipe files and meatadata for import in to the repository, do

```bash

./write_recipes.php import-list.csv  ./output/

```


## z3950-gateway/

Query endpoint for marcrecords that gatways to z3950 server.


