<?php
/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION  (single place for the whole project)
|--------------------------------------------------------------------------
| Change hostname / user / password / central database name HERE only.
| Every page connects through db_connect().
|
|   db_connect()              -> central database  (hospital_management)
|   db_connect($hospital_db)  -> one hospital's own database
|   db_connect(null)          -> server connection without selecting a database
|--------------------------------------------------------------------------
*/

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'Hospital_management');
    define('DB_PASS', 'B@ldh@ V@rshil');
    define('DB_NAME', 'hospital_management');
}

if (!function_exists('db_connect')) {
    function db_connect($database = DB_NAME)
    {
        return mysqli_connect(
            DB_HOST,
            DB_USER,
            DB_PASS,
            $database
        );
    }
}
