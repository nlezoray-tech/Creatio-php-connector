<?php
require __DIR__.'/../vendor/autoload.php';

use Nlezoray\Creatio\Creatio;

$Creatio = new Creatio();
$account = $Creatio->initAccountById('guid'); //Put Account guid here

var_dump($account);
