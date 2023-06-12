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
    // Note: LoadEnvFile is a function provided by dbBridge to load environment variables from a file.
    // Please create the db_source.env and db_target.env files with your database credentials.
     
    // Load environment variables for the source database
    // db_source.env template:
    // DB_DSN=your_odbc_dsn
    // DB_NAME=your_db_name
    // DB_USER=your_db_user
    // DB_PASS=your_db_pass
     LoadEnvFile( 'db_source.env' );
 
     // Connect to the source database
     // Note: openODBC is a helper function in dbAbstractor.php that helps establish an ODBC connection
     $pdoMsSql = openODBC( $_ENV['DB_DSN'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'] );
     $dbMsSql = new dbAbstractor( $pdoMsSql, $_ENV['DB_NAME'] );
 
    // Load environment variables for the target database
    // Note: This will override the existing values
    // db_target.env template
    // DB_HOST=your_mysql_host
    // DB_NAME=your_db_name
    // DB_PORT=your_mysql_port
    // DB_USER=your_db_user
    // DB_PASS=your_db_pass
     LoadEnvFile( 'db_target.env' );
 
     // Connect to the target database
     // Note: openMySql is a helper function in dbAbstractor.php that helps establish a MySQL connection
     $pdoMySql = openMySql( $_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_PORT'], $_ENV['DB_USER'], $_ENV['DB_PASS'] );
     $dbMySql = new dbAbstractor( $pdoMySql, $_ENV['DB_NAME'] );

    // Set debug flags (optional) - defaults for both the log file and the screen should be acceptable for most cases.     
    // debugFlags::setDebugLogFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL); // default is debugFlags::DEBUG_ALL
    // debugFlags::setDebugShowFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL); // default is debugFlags::DEBUG_IMPORT_ROW 

    // Perform the migration process
    $dbMySql->importDb($dbMsSql);       
 }
 catch( \Exception $e ) 
 {
     // Handle exceptions
     echo "An error occurred: " . $e->getMessage();
 } 
?>
