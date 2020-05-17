
<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../controllers/External.php";
require_once __DIR__ . "/../models/Matchs.php";



$dale = External::insertMatchsFromUser();

print_r($dale);





?>