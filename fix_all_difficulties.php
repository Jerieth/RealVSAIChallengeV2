<?php
// Fix All Difficulties Script
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Fix Easy mode: 5 lives, 20 turns
$query1 = "UPDATE games SET lives = 5, max_turns = 20 WHERE difficulty = 'easy' AND (lives != 5 OR max_turns != 20)";
$result1 = db_query($query1);
echo "Fixed " . $result1->rowCount() . " Easy mode games.<br>";

// Fix Medium mode: 3 lives, 50 turns
$query2 = "UPDATE games SET lives = 3, max_turns = 50 WHERE difficulty = 'medium' AND (lives != 3 OR max_turns != 50)";
$result2 = db_query($query2);
echo "Fixed " . $result2->rowCount() . " Medium mode games.<br>";

// Fix Hard mode: 1 life, 100 turns
$query3 = "UPDATE games SET lives = 1, max_turns = 100 WHERE difficulty = 'hard' AND (lives != 1 OR max_turns != 100)";
$result3 = db_query($query3);
echo "Fixed " . $result3->rowCount() . " Hard mode games.<br>";
?>
