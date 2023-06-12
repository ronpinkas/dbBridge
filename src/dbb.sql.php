<?php
/**
 * dbBridge is an educational proof-of-concept PHP library that serves as an 
 * abstraction bridge between multiple SQL dialects using PDO (native and ODBC)
 * drivers. It enables importing a database from a source to a target with just
 * one user class and three lines of code: 
 * 
 *      $dbSource = new dbAbstractor( $pdoSourceSql [, 'YourDB-Name' ] );
 *      $dbTarget = new dbAbstractor( $pdoTargetSql );
 *      $dbTarget->importDb( $dbSource );
 *
 * Supported SQL dialects:
 *   - MySQL
 *   - Microsoft SQL Server
 *   - Oracle
 *   - PostgreSQL
 *   - SQLite
 *    
 * Copyright 2023 Ron[ny] Pinkas <ron@ronpinkas.com>
 * www - https://github.com/ronpinkas/dbBridge
 * 
 * MIT License
 * 
 * This file contains the driver implementation for the sql dbBridge parent class
 * 
 * @package dbBridge
 * @version 0.8.0 (woring version)
 * @license MIT License <https://opensource.org/licenses/MIT>
 * 
 * @link https://github.com/ronpinkas/dbBridge
 */
declare( strict_types = 1 );
namespace dbBridge;
 
USE \PDO;
USE \PDOStatement;
USE \PDOException;
USE \Exception;

/*
USE \DateTime;
USE \DateTimeZone;
USE \DateInterval;
USE \DatePeriod;
*/
  
// Error and Exception handling
require_once 'dbb.err.php';

// Utility functions
require_once 'dbb.funcs.php';

/**
 * CLASS sqlDriver( PDP $pdo, string $Workarea )
 * 
 * Implements the parent class for all SQL database drivers.
 * 
 * Arguments:
 *  $pdo - a PDO connection to the target database
 * $Workarea - the name of the Workarea to migrate
 * 
 * @package dbBridge
 * @property PDO $pdo
 * @property string $Workarea
 * @property array $tableNames
 */

abstract class sqlDriver extends dbDriver
{
    /*
       All missing methods default to Parent's implementations.
    */

    protected function fetchWorkarea() : string 
    {
        try 
        {
            $result = $this->pdo->query( "SELECT DATABASE()" );
            return $result->fetchColumn();
        } 
        catch( Exception $e ) 
        {
            throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
        }
    }
}
?>