<?php
/**
 * This is a sample of the dbBridge Migration functionality.
 */

//show all errors and warnings
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../src/dbAbstractor.php';

use dbBridge\dbAbstractor;
use dbBridge\debugFlags;
use function dbBridge\LoadEnvFile;
use function dbBridge\OpenODBC;
use function dbBridge\OpenMySql;

LoadEnvFile( 'db_source.env' );

$pdoMsSql = openODBC( $_ENV['DB_DSN'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'] );
$dbMsSql = new dbAbstractor( $pdoMsSql, $_ENV['DB_NAME'] );

// Will override existing values - so source and target can use same names!
LoadEnvFile( 'db_target.env' );

$pdoMySql = openMySql( $_ENV[ 'DB_HOST' ], $_ENV[ 'DB_NAME' ] , $_ENV[ 'DB_PORT' ], $_ENV[ 'DB_USER' ], $_ENV[ 'DB_PASS' ] );
$dbMySql = new dbAbstractor( $pdoMySql, $_ENV['DB_NAME'] );

/*
   Default to required levels of debug trace to dbBridge.log and/or show to terminal.

   // DEBUG_ALL is the default for logging.
   //debugFlags::setDebugLogFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL  );

   //DEBUG_IMPORT_ROW is the default for showing to terminal.
   //debugFlags::setDebugShowFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL  );
*/

// Perform the migration process...
$dbMySql->importDb( $dbMsSql );
?>