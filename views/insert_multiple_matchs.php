<?php

require_once __DIR__ . "/../controllers/Internal.php";

$time_start = microtime(true);

// Internal::processLocalJsonsAndInsertInDB();
Internal::dale();

$time_final_end = microtime(true);
$total_execution_time = intval($time_final_end - $time_start);
echo "Total execution time = $total_execution_time\n";

?>