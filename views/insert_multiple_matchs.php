<?php

require_once __DIR__ . "/../controllers/Internal.php";

Internal::processLocalJsonsAndInsertInDB(250);

print_r("End...\n");

?>