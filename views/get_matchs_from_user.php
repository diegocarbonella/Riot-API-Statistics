
<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../controllers/External.php";
require_once __DIR__ . "/../models/Matchs.php";

for ($i = 0; $i < 100 ; $i++) {

    External::insertMatchsFromUser();

}

?> 