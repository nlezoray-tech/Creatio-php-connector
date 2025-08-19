<?php
require __DIR__.'/../vendor/autoload.php';

use Nlezoray\Creatio\Adapter\CreatioOAuthAdapter;

$adapter = new CreatioOAuthAdapter('prod');
// var_dump($adapter->get(...));
echo "OK\n";