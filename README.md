# Introduction
A simple PHP client for leveraging the [pfsense_fauxapi](https://github.com/ndejong/pfsense_fauxapi).  See 
[pfsense_fauxapi](https://github.com/ndejong/pfsense_fauxapi) for available methods.  Review `Client.php` if further
details are needed.

# Sample
```php
<?php

require_once('vendor/autoload.php');

$options = [
    'uri' => 'http(s)://host[:port]',
    'apiKey' => 'PFFA...',
    'apiSecret' => '<secret>',
];

$client = new PfSenseFauxApi\Client($options);
$response = $client->config_get();
var_dump($response);

$response = $client->config_backup_list();
var_dump($response);

//$response = $client->config_reload();
//var_dump($response);

//$response = $client->gateway_status();
//var_dump($response);

//$response = $client->rule_get();
//var_dump($response);


/*
$data  [
  "system" => [
    "dnsserver" => [
      "8.8.8.8",
      "8.8.4.4"
    ],
    "hostname" => "newhostname"
  ]
];

$response = $client->config_patch($data);
var_dump($response);

$data = [
    'function' => 'openbgpd_install_conf'
];
$response = $client->function_call($data);
var_dump($response);

*/

?>

```