# About

Simple lib for [Review 3](https://reviewthree.com/ru-ru) service.

# Install
Add these lines to the file `composer.json`

```
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/demyashev/php.review3-lib"
        }
    ],
    "require": {
        "demyashev/php.review3-lib": "@dev"
    }
}
```

# How to
```
<?php

include __DIR__ . '/../src/vendor/autoload.php';

use ReviewThree\Service;

# optional, not required
# $cache - any caching object with set and get methods
$cache = new \Redis();
$cache->connect('127.0.0.1');

$service =
    (new Service())
        # required
        ->setWebstore('<webstore>')                     # your personal id in system
        ->setUseragent('<webstore> CURL Client')        # who are you?

        # optional (disabled by default)
        ->setCache($cache)                              # cached all successful results (also with id = 0)
        ->setLogPath('/path/to/log');                   # if you want log, directory must be created before logging

        # optional (default value)
        ->setResponseType(Service::RESPONSE_TYPE_JSON)  # only for requests, Service::RESPONSE_TYPE_JSON, Service::RESPONSE_TYPE_XML
        ->setCachePrefix('review3.')                    # key = review3.<search>
        ->setCacheLifetime(60 * 60 * 24)                # 1 day
        
try {
    # by webstore id (main method)
    $byId = $service->search(2933858);
}
catch (Exception $e) {
    die($e->getMessage());
}

# another methods for search
# by MPN
$byMPN = $service->search('GPC97204', Service::METHOD_MPN);

# by barcode
$byBarcode = $service->search(5901234123457, Service::METHOD_BARCODE);

# by Yandex.Market id
$byYMId = $service->search(100697781887, Service::METHOD_YMID);

# by product name
$byName = $service->search('Sony Cyber-shot DSC-RX10 IV', Service::METHOD_NAME);
```

# License
[Apache 2.0](https://www.apache.org/licenses/LICENSE-2.0)