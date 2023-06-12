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
 * This file contains the driver implementation for ODBC dbBridge driver.
 * 
 * @package dbBridge
 * @version 0.8.0 (woring version)
 * @license MIT License <https://opensource.org/licenses/MIT>
 * 
 * @link https://github.com/ronpinkas/dbBridge
 */
declare( strict_types = 1 );
namespace dbBridge;

require_once 'dbb.sql.php';

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
 * CLASS: odbcDriver( PDO $pdo ) : sqlDriver
 * 
 * Implements the dbBridge driver for ODBC.
 * 
 * @package dbBridge
 * @subpackage odbc
 * @version 1.0.0
 * @license MIT
 */
class odbcDriver extends sqlDriver 
{
    protected string $sqlServer;
    protected sqlDriver $sqlDriver;

    public function __construct(PDO $pdo, string $Workarea ) 
    {
        try
        {
            parent::__construct( $pdo, $Workarea );

            $this->sqlServer = detectSqlServer( $pdo, $Workarea );

            // Check if a file dbb.{driverName}.php exists. If so, include it.
            $driverFile = __DIR__ . "/dbb.$this->sqlServer.php";
            
            if ( file_exists( $driverFile ) )
            {
                require_once $driverFile;
            }
            else
            {
                // Will be wrapped in a dbBridgeException in catch below.
                throw new Exception( "Driver file $driverFile not found" );
            }
            
            $this->sqlDriver = match ( $this->sqlServer) 
            {
                'mysql' => new MySQLDriver( $pdo, $Workarea ),
                'pgsql' => new PgsqlDriver( $pdo, $Workarea ),
                'sqlite' => new SqliteDriver( $pdo, $Workarea ),
                'mssql' => new MsSqlDriver( $pdo, $Workarea ),
                'oracle' => new OracleDriver( $pdo, $Workarea ),
                default => throw new dbBridgeException( "Unknown SQL Server: '$sqlServer'!" ),
            };
        }
        catch( Exception $e )
        {
            throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
        }
    }

    public function getDialect() : string
    {
        return $this->sqlServer;
    }

    protected function fetchTableNames() : array 
    {
        return $this->sqlDriver->getTableNames();
    }

    protected function fetchTableColumns( string $table_name ) : array
    {
        return $this->sqlDriver->getColumns( $table_name );
    }
}

/**
 * FUNCTION: detectSqlServer( PDO $pdo ) : string
 * 
 * Detect the SQL Server type.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function detectSqlServer( PDO $pdo ) : string
{
    try 
    {
        // Try a SQL statement that will only work on MySQL
        $result = $pdo->query( "SHOW TABLES" );
        
        if( $result !== false ) 
        {
            return 'mysql';
        }
    } 
    catch( Exception $e ) 
    {
        // Do nothing, it's not MySQL
    }

    try 
    {
        // Try a SQL statement that will only work on PostgreSQL
        $result = $pdo->query( "SELECT tablename FROM pg_catalog.pg_tables" );
        
        if( $result !== false ) 
        {
            return 'pgsql';
        }
    } 
    catch( Exception $e ) 
    {
        // Do nothing, it's not PostgreSQL
    }

    try
    {
        // Try a SQL statement that will only work on SQL Server
        $result = $pdo->query( "SELECT table_name FROM information_schema.tables" );

        if( $result !== false )
        {
            return 'mssql';
        }
    } 
    catch( Exception $e ) 
    {
        // Do nothing, it's not SQL Server
    }

    try 
    {
        $result = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table'" );
        
        if( $result !== false ) 
        {
            return 'sqlite';
        }
    } 
    catch( Exception $e ) 
    {
        // Do nothing, it's not SQLite
    }

    try 
    {
        $result = $pdo->query( "SELECT table_name FROM user_tables" );
        
        if( $result !== false ) 
        {
            return 'oracle';
        }
    } 
    catch( Exception $e ) 
    {
        // Do nothing, it's not Oracle
    }

    throw new dbBridgeException( __METHOD__ . ' Failed!' );
}
?>

