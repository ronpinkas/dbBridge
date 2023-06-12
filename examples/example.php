<?php
/**
 * This is a sample of the dbBridge Migration functionality.
 */

require_once '../src/dbAbstractor.php';

use dbBridge\dbAbstractor;
use dbBridge\debugFlags;
use function dbBridge\{LoadEnvFile, OpenODBC, OpenMySql};

try 
{
    // Load environment variables for the source database
    LoadEnvFile('db_source.env');

    // Connect to the source database
    $pdoMsSql = openODBC($_ENV['DB_DSN'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $dbMsSql = new dbAbstractor($pdoMsSql, $_ENV['DB_NAME']);

    // Load environment variables for the target database
    // Note: This will override the existing values
    LoadEnvFile('db_target.env');

    // Connect to the target database
    $pdoMySql = openMySql($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_PORT'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $dbMySql = new dbAbstractor($pdoMySql, $_ENV['DB_NAME']);

    // Set debug flags (optional) - defaults for both the log file and the screen should be acceptable for most cases.     
    // debugFlags::setDebugLogFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL); // default is debugFlags::DEBUG_ALL
    // debugFlags::setDebugShowFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL); // default is debugFlags::DEBUG_IMPORT_ROW 

    // Perform the migration process
    $dbMySql->importDb($dbMsSql);

} 
catch (\Exception $e) 
{
    // Handle exceptions
    echo "An error occurred: " . $e->getMessage();
}
?>
