<?php
/**
 *  dbBrige - An abstraction bridge between multiple SQL dialects using
 *  PDO (native and odbc) drivers with just one user class and 3 lines of code: 
 * 
 *      $dbSource = new dbAbstractor( $pdoMsSql [, 'YourDB-Name' ] );
 *      $dbTarget = new dbAbstractor( $pdoMySql );
 *      $dbTarget->importDb( $dbSource );
 *   
 * Copyright 2023 Ron[ny] Pinkas <ron@ronpinkas.com>
 * www - https://www.ronpinkas.com
 * 
 * MIT License
 *  
 * This file contains driver implementation for Microsoft SQL Server dbBridge driver.
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

// Microsoft SQL Server database driver class.
/**
 * CLASS: MsSqlDriver( PDO $pdo ) : sqlDriver
 * 
 * Implements the dbBridge driver for Microsoft SQL Server.
 * 
 * @package dbBridge
 * @subpackage mssql
 * @version 1.0.0
 * @license MIT
 */
class MsSqlDriver extends sqlDriver
{
    protected function fetchWorkarea() : string
    {
        return $this->Workarea = $this->pdo->query('SELECT DB_NAME()')->fetchColumn();
    }
    
    protected function fetchTableNames() : array
    {
        return getTableNamesMsSql( $this->pdo );
    }

    protected function fetchTableColumns( string $table_name ) : array
    {
        return getColumnsMsSql( $this->pdo, $table_name, $this->Workarea );
    }
}

/**
 * FUNCTION: getTableNamesMsSql( PDO $pdo )
 * 
 * Return an array of table names in the current Workarea.
 * 
 * @param PDO $pdo
 * 
 * @return array
 */
function getTableNamesMsSql( PDO $pdo ) : array
{
    try
    {
        $result = $pdo->query( "SELECT table_name FROM information_schema.tables WHERE table_type = 'base table'" );
        return $result->fetchAll( PDO::FETCH_COLUMN );
    }
    catch( Exception $e )
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: getColumnsMsSql( PDO $pdo, string $table_name, string $Workarea ) : array
 * 
 * Return an array of column definitions for the specified table.
 * 
 * @param PDO $pdo
 * @param string $table_name
 * @param string $Workarea
 * 
 * @return array
 */
function getColumnsMsSql(PDO $pdo, string $table_name, string $Workarea) : array
{
    try
    {
        $query = $pdo->prepare(
            "SELECT COLUMN_NAME as column_name, DATA_TYPE as data_type, CHARACTER_MAXIMUM_LENGTH as character_maximum_length, 
            NUMERIC_PRECISION as numeric_precision, NUMERIC_SCALE as numeric_scale, IS_NULLABLE as is_nullable
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_CATALOG = :Workarea AND TABLE_NAME = :table_name"
        );
        
        $result = $query->execute( [':Workarea' => $Workarea, ':table_name' => $table_name] );
        if($result === false)
        {
            throw new dbBridgeException( "Failed to execute query: " . $query->errorInfo()[2] );
        }

        $columns = $query->fetchAll(PDO::FETCH_ASSOC);
        //var_dump($columns);

        foreach( $columns as $column )
        {
            $column['is_nullable'] = $column['is_nullable'] === 'YES' ? true : false;

            // Adding a new query to convert column data to UTC if it is a datetime type with timezone
            if(stripos($column['data_type'], 'datetimeoffset') !== false) 
            {
                $column['to_utc_query'] = "CONVERT(DATETIME2, SWITCHOFFSET({$column['column_name']}, '+00:00')) as {$column['column_name']}";
            }
            else
            {
                $column['to_utc_query'] = $column['column_name'];
            }
        }

        return $columns;
    }
    catch( Exception $e )
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: mssqlTypeTo_stdType( string $mssqlType ) : string
 * 
 * Map each mssqlType to a stdType.
 * 
 * @param string $mssqlType
 * 
 * @return string
 */
function mssqlTypeTo_stdType( string $mssqlType ): string
{
    static $mssqlTypeToStdType = [
        'bigint' => stdTypes::BIGINT,
        'binary' => stdTypes::BINARY,
        'bit' => stdTypes::BIT,
        'char' => stdTypes::CHAR,
        'date' => stdTypes::DATE,
        'datetime' => stdTypes::DATETIME,
        'datetime2' => stdTypes::DATETIME,
        'datetimeoffset' => stdTypes::DATETIME_TZ,
        'decimal' => stdTypes::DECIMAL,
        'float' => stdTypes::FLOAT,
        'image' => stdTypes::BLOB,
        'int' => stdTypes::INT,
        'money' => stdTypes::MONEY,
        'nchar' => stdTypes::UNICODE_STRING,
        'ntext' => stdTypes::LONG_UNICODE_STRING,
        'numeric' => stdTypes::DECIMAL,
        'nvarchar' => stdTypes::UNICODE_STRING,
        'real' => stdTypes::FLOAT,
        'smalldatetime' => stdTypes::DATETIME,
        'smallint' => stdTypes::SMALLINT,
        'smallmoney' => stdTypes::SMALLMONEY,
        'text' => stdTypes::LONG_STRING,
        'time' => stdTypes::TIME,
        'tinyint' => stdTypes::TINYINT,
        'uniqueidentifier' => stdTypes::GUID,
        'varbinary' => stdTypes::BINARY,
        'varchar' => stdTypes::STRING,
        'xml' => stdTypes::LONG_UNICODE_STRING,
        'sql_variant' => stdTypes::UNKNOWN,
        'timestamp' => stdTypes::TIMESTAMP,
        'geometry' => stdTypes::BINARY,
        'geography' => stdTypes::BINARY,
        'hierarchyid' => stdTypes::UNKNOWN
    ];

    if( !isset( $mssqlTypeToStdType[ $mssqlType ] ) )
    {
        $mssqlTypeToStdType[$mssqlType] = stdTypes::UNKNOWN;

        $msg = __METHOD__.'('.__LINE__.')'."->FIXME!!! mssqlType '$mssqlType' is not yet supported by stdTypes - Mapped it to: '{$mssqlTypeToStdType[$mssqlType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $mssqlTypeToStdType[ $mssqlType ];
}

/**
 * FUNCTION: stdTypeTo_mssqlType( string $stdType ) : string
 * 
 * Map each stdType to a mssqlType.
 * 
 * @param string $stdType
 * 
 * @return string
 */
function stdTypeTo_mssqlType( string $stdType ): string
{
    static $stdTypeToMssqlType = [
        stdTypes::NULL => 'varchar',
        stdTypes::BIT => 'bit',
        stdTypes::TINYINT => 'tinyint',
        stdTypes::SMALLINT => 'smallint',        
        stdTypes::INT => 'int',
        stdTypes::BIGINT => 'bigint',
        stdTypes::FLOAT => 'float',
        stdTypes::DOUBLE => 'float',
        stdTypes::DECIMAL => 'decimal',
        stdTypes::SMALLMONEY => 'smallmoney',
        stdTypes::MONEY => 'money',
        stdTypes::STRING => 'varchar',
        stdTypes::CHAR => 'char',
        stdTypes::LONG_STRING => 'text',
        stdTypes::UNICODE_STRING => 'nvarchar',
        stdTypes::LONG_UNICODE_STRING => 'ntext',
        stdTypes::CHARBINARY => 'binary',
        stdTypes::BINARY => 'binary',
        stdTypes::BLOB => 'image',
        stdTypes::DATE => 'date',
        stdTypes::TIME => 'time',
        stdTypes::TIME_TZ => 'datetimeoffset',
        stdTypes::DATETIME => 'datetime',
        stdTypes::DATETIME_TZ => 'datetimeoffset',
        stdTypes::TIMESTAMP => 'timestamp',
        stdTypes::INTERVAL => 'varchar',
        stdTypes::BOOL => 'bit',
        stdTypes::JSON => 'varchar',
        stdTypes::GUID => 'uniqueidentifier',
        stdTypes::AUTO_INCREMENT_TINYINT => 'tinyint(I)',
        stdTypes::AUTO_INCREMENT_SMALLINT => 'smallint(I)',
        stdTypes::AUTO_INCREMENT_MEDIUMINT => 'int(I)',
        stdTypes::AUTO_INCREMENT_INT => 'int(I)',
        stdTypes::AUTO_INCREMENT_BIGINT => 'bigint(I)',
        stdTypes::UNKNOWN => 'sql_variant'
    ];

    // If the stdType is not listed above then the code is incomplete and MUST be fixed!!!
    if( !isset( $stdTypeToMssqlType[ $stdType ] ) )
    {
        $stdTypeToMssqlType[$stdType] = 'varchar';
        $msg = __METHOD__.'('.__LINE__.')'."FIXME!!! stdType: '$stdType' is not yet supported by __METHOD__ - Mapped it to: '{$stdTypeToMssqlType[$stdType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $stdTypeToMssqlType[ $stdType ] ?? 'varchar';
}

/**
 * FUNCTION: openMsSqlDb( string $dsnName, string $dbName, string $userName, string $password, int $timeout = 600 ) : PDO
 * 
 * Open a connection to a Microsoft SQL Server database.
 * 
 * @param string $dsnName
 * @param string $dbName
 * @param string $userName
 * @param string $password
 * @param int $timeout
 * 
 * @return PDO
 */
function openMsSqlDb( string $dsnName, string $dbName, string $userName, string $password, int $timeout = 600 ) : PDO
{
    $dsn = 'odbc:DSN=' . $dsnName . ';Database=' . $dbName;

    try
    {
        $pdo = openDb( $dsn, $userName, $password );
        return $pdo;
    }
    catch( Exception $e )
    {        
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}
?>
 