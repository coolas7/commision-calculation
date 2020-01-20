<?php

use VytautasUoga\CommissionTask\Service\Math;
use VytautasUoga\CommissionTask\Service\Payments;


require 'vendor/autoload.php';

if (count($argv) == 1) { 

    echo "truksta argumentu \nNorint paleisti aplikaciją rašykite: index.php file.csv \n";

    exit; 
}

$csv_filename = $argv[1];

$doMath = new Math();

$doMath->doMath($csv_filename);

