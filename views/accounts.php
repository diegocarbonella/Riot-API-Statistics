<?php

require 'src/Users.php';
require 'src/Data.php';

$account_id = Users::getLastAccountId();
$matches = Users::getAllMatchesFromUser($account_id);

if (count($matches) > 0) {
    echo Data::setMatchFutureInDB($matches);
}

echo "\n";


?>