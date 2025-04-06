<?php
// Fix Easy Mode Lives Script
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Fix games that have incorrect lives for Easy mode (should be 5, not 4)
$query = "UPDATE games SET lives = 5 WHERE difficulty = 'easy' AND lives = 4";
$result = db_query($query);

echo "Fixed " . $result->rowCount() . " games with incorrect Easy mode lives count.";
?>
