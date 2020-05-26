<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../controllers/External.php";

for ($i = 0; $i < 10; $i++) {
    
    External::saveJsonGamesFromAPI();

}

?>