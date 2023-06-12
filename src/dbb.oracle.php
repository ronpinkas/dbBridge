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
 * www - https://www.ronpinkas.com
 * 
 * MIT License
 * 
 * This file contains the driver implementation for the Oracle dbBridge driver.
 * 
 * @package dbBridge
 * @version 0.8.0 (woring version)
 * @license MIT License <https://opensource.org/licenses/MIT>
 * 
 * @link https://www.ronpinkas.com/dbBridge
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

// Return an array of table names in the current Workarea.
function getTableNamesOracle( PDO $pdo, string $Workarea ) : array
{
    $result = $pdo->query( "SELECT table_name FROM user_tables" );
    
    if( !$result )
    {
        throw new Exception( "[getTableNamesOracle()]Workarea: '{$Workarea}' - SELECT Failed!" );
    }

    return $result->fetchAll( PDO::FETCH_COLUMN );
}

// Return an array of column definitions for the specified table.
function getColumnsOracle(PDO $pdo, string $table_name, string $Workarea) : array
{
    try
    {
        $query = $pdo->prepare( "SELECT column_name as \"column_name\", data_type as \"data_type\", data_length as \"character_maximum_length\", data_precision as \"numeric_precision\", data_scale as \"numeric_scale\", nullable as \"is_nullable\" FROM user_tab_columns WHERE table_name=:table_name" );    
        
        $result = $query->execute( [':table_name' => strtoupper($table_name)] );
        if( $result === false ) 
        {
            throw new Exception( "Failed to execute query: " . $query->errorInfo()[2] );
        }

        $columns = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as &$column) {
            $column['is_nullable'] = $column['is_nullable'] === 'Y' ? true : false;

            // If the column data_type is a TIMESTAMP WITH TIME ZONE, add a to_utc_query field
            if ($column['data_type'] === 'TIMESTAMP WITH TIME ZONE') {
                $column['to_utc_query'] = "SYS_EXTRACT_UTC(\"{$column['column_name']}\") AS \"{$column['column_name']}\"";
            }
        }

        if(is_array($columns) === false || count($columns) == 0)
        {
            throw new Exception("[getColumnsOracle()]Workarea: '{$Workarea}' - No Columns Found!");
        }

        return $columns;
    }
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

// Oracle database driver class.
class OracleDriver extends sqlDriver
{
    protected function fetchWorkarea() : string 
    {
        return $this->Workarea = $this->pdo->query( 'SELECT global_name FROM global_name;' )->fetchColumn();
    }

    protected function fetchTableNames() : array 
    {        
        return getTableNamesOracle( $this->pdo, $this->Workarea );
    }

    protected function fetchTableColumns( string $table_name ) : array
    {
        if( in_array( $table_name, $this->getTableNames() ) ) 
        {
            return getColumnsOracle( $this->pdo, $table_name, $this->Workarea );
        }
        else
        {
            throw new Exception( "[][]workarea: '{$this->Workarea}' does not have Table: '$table_name'!" );
        }
    }    
}

/**
 * FUNCTION: oracleTypeTo_stdType( string $oracleType )
 * 
 * Map each oracleType to a stdType.
 * 
 * @param string $oracleType
 * @return string
 */
function oracleTypeTo_stdType($oracleType): string
{
    static $oracleTypeToStdType = [
        'BFILE' => stdTypes::CHARBINARY,
        'BINARY_DOUBLE' => stdTypes::DOUBLE,
        'BINARY_FLOAT' => stdTypes::FLOAT,
        'BINARY_INTEGER' => stdTypes::INT,
        'BLOB' => stdTypes::CHARBINARY,
        'CHAR' => stdTypes::CHAR,
        'CLOB' => stdTypes::TEXT,
        'DATE' => stdTypes::TIMESTAMP,
        'DEC' => stdTypes::DEC,
        'DECIMAL' => stdTypes::DEC,
        'DOUBLE PRECISION' => stdTypes::DOUBLE,
        'FLOAT' => stdTypes::FLOAT,
        'INT' => stdTypes::INT,
        'INTEGER' => stdTypes::INT,
        'INTERVAL DAY TO SECOND' => stdTypes::INTERVAL,
        'INTERVAL YEAR TO MONTH' => stdTypes::INTERVAL,
        'LONG' => stdTypes::TEXT,
        'LONG RAW' => stdTypes::CHARBINARY,
        'NCHAR' => stdTypes::CHAR,
        'NCLOB' => stdTypes::TEXT,
        'NUMBER' => stdTypes::DEC,
        'NVARCHAR2' => stdTypes::STRING,
        'RAW' => stdTypes::CHARBINARY,
        'REAL' => stdTypes::FLOAT,
        'ROWID' => stdTypes::STRING . '(10)',
        'SMALLINT' => stdTypes::INT,
        'TIMESTAMP' => stdTypes::TIMESTAMP,
        'TIMESTAMP WITH TIME ZONE' => stdTypes::TIMESTAMP,
        'TIMESTAMP WITH LOCAL TIME ZONE' => stdTypes::TIMESTAMP,
        'VARCHAR2' => stdTypes::STRING,
        'UROWID' => stdTypes::STRING . '(4000)'
    ];

    if( !isset( $oracleTypeToStdType[ $oracleType ] ) )
    {
        $oracleTypeToStdType[$oracleType] = stdTypes::UNKNOWN;

        $msg = __METHOD__.'('.__LINE__.')'."FIXME!!! Oracle type '$oracleType' is not yet supported by stdTypes - Mapped it to: '{$oracleTypeToStdType[$oracleType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $oracleTypeToStdType[ $oracleType ];
}

/**
 * FUNCTION: stdTypeTo_oracle( string $type )
 * 
 * Map each stdType to an oracleType.
 * 
 * @param string $type
 * @return string
 */
function stdTypeTo_oracle( string $type ): string
{
    $stdTypeToOracle = [
        stdTypes::NULL => 'NULL',
        stdTypes::BIT => 'RAW(1)',
        stdTypes::TINYINT => 'NUMBER(3)',
        stdTypes::SMALLINT => 'NUMBER(5)',
        stdTypes::INT => 'NUMBER(10)',
        stdTypes::BIGINT => 'NUMBER(19)',
        stdTypes::FLOAT => 'FLOAT(24)',  // corresponds to Oracle's BINARY_FLOAT
        stdTypes::DOUBLE => 'FLOAT(53)',  // corresponds to Oracle's BINARY_DOUBLE
        stdTypes::DECIMAL => 'NUMBER(38, 10)',
        stdTypes::MONEY => 'NUMBER(19, 4)',
        stdTypes::CHAR => 'CHAR(1 CHAR)',
        stdTypes::STRING => 'VARCHAR2(4000 CHAR)',
        stdTypes::LONG_STRING => 'CLOB',
        stdTypes::UNICODE_STRING => 'NVARCHAR2(2000)',
        stdTypes::LONG_UNICODE_STRING => 'NCLOB',
        stdTypes::CHARBINARY => 'RAW(2000)',
        stdTypes::BINARY => 'BLOB',
        stdTypes::DATE => 'DATE',
        stdTypes::TIME => 'TIMESTAMP(0)',  // Oracle does not have a separate TIME type
        stdTypes::TIME_TZ => 'TIMESTAMP(0) WITH TIME ZONE',
        stdTypes::DATETIME => 'TIMESTAMP',
        stdTypes::DATETIME_TZ => 'TIMESTAMP WITH TIME ZONE',
        stdTypes::TIMESTAMP => 'TIMESTAMP',
        stdTypes::INTERVAL => 'INTERVAL DAY TO SECOND',
        stdTypes::BOOL => 'NUMBER(1)',
        stdTypes::JSON => 'BLOB',  // Oracle has JSON capabilities, but there's no specific JSON type
        stdTypes::GUID => 'RAW(16)',
        stdTypes::AUTO_INCREMENT_TINYINT => 'NUMBER(3)',
        stdTypes::AUTO_INCREMENT_SMALLINT => 'NUMBER(5)',
        stdTypes::AUTO_INCREMENT_MEDIUMINT => 'NUMBER(7)',
        stdTypes::AUTO_INCREMENT_INT => 'NUMBER(10)',
        stdTypes::AUTO_INCREMENT_BIGINT => 'NUMBER(19)',
        stdTypes::UNKNOWN => 'BLOB'  // a safe default for unknown data types
    ];
    
    if( !isset( $stdTypeToOracleType[$stdType] ) )
    {
        $stdTypeToOracleType[$stdType] = 'VARCHAR2(4000)'; // default to VARCHAR2(4000) for unknown types

        $msg = "__METHOD__(__LINE__)->FIXME!!! stdType '$stdType' is not yet supported by OracleTypes - Mapped it to: '{$stdTypeToOracleType[$stdType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $stdTypeToOracleType[ $stdType ];
}
?>
