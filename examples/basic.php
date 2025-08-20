<?php
require __DIR__.'/../vendor/autoload.php';

use Nlezoray\Creatio\Creatio;

//First example
$Creatio = new Creatio('dev'); //Creatio oData in production env <=> $Creatio('prod','odata')
//$Creatio('dev','oauth'); //Creatio oAuth in development env
//$account = $Creatio->initAccountById('51d67a11-703f-4f86-9814-e079ee362cab'); //Put Account guid here

//var_dump($account);

/* To get contacts with nb results defined */
$contacts = $Creatio->getContacts(10);
echo "<xmp>".print_r($contacts,1)."</xmp>";