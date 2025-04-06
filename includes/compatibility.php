<?php
/**
 * Compatibility functions to handle naming differences
 * between different parts of the codebase
 */

// Only define if it doesn't already exist
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        return get_db_connection();
    }
}

// Add other compatibility functions as needed