<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'src/Champions.php';
require 'src/Constants.php';

// $champions = Constants::dale();

// $matchs = Champions::getNumberOfMatchs();

// $data = Champions::getTimesChampionWasPlayed(1);

// echo "Matchs = " . $matchs . "<br>";
// echo "Games  = " . $data["games"]   . "<br>";
// echo "Wins  = " . $data["wins"]   . "<br>";


$pickrates = Champions::getPickRate();


$champions = file_get_contents("info/champion.json");
$champions = json_decode($champions, true);
$champions = $champions["data"];


foreach ($champions as $champion) {
    $picks = $pickrates[$champion["key"]]["games"];
    $wins = $pickrates[$champion["key"]]["wins"];
    echo $champion["id"] . " picks " . $picks . " winrate %" . $wins/$picks . "<br>"; 
}

var_dump($pickrates);






// var_dump(Champions::getPickRate());