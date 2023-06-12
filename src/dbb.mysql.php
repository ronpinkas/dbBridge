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
 * This file contains driver implementation for MySQL dbBridge driver.
 * 
 * @package dbBridge
 * @version 0.8.0 (woring version)
 * @license MIT License <https://opensource.org/licenses/MIT>
 * 
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
 * This file contains the driver implementation for MySQL dbBridge driver.
 * 
 * @package dbBridge
 * @subpackage mysql
 * @version 1.0.0
 * @license MIT
 * 
 * Copyright © 2003 <ron@ronpinkas.com> (Ron[ny] Pinkas)
 * 
 * License: MIT License <https://opensource.org/licenses/MIT>
 * 
 */

/**
 * CLASS: MySqlDriver
 * 
 * Implements the dbBridge driver for MySQL.
 * 
 * @param string $mysqlType
 * @return string
 */ 
class MySqlDriver extends sqlDriver 
{
    /*
       All missing methods default to Parent's implementations.
    */

    protected function fetchTableNames() : array 
    {
        return getTableNamesMySql( $this->pdo );
    }

    protected function fetchTableColumns( string $table_name ) : array
    {
        return getColumnsMySql( $this->pdo, $table_name, $this->Workarea );
    }
}

/**
 * FUNCTION: getTableNamesMySql( PDO $pdo ) : array
 * 
 * Return an array of table names in the current Workarea.
 * 
 * @param PDO $pdo
 * @return array
 */
function getTableNamesMySql( PDO $pdo ) : array 
{
    try 
    {
        $result = $pdo->query( "SHOW TABLES" );
        return $result->fetchAll( PDO::FETCH_COLUMN );
    } 
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: getColumnsMySql( PDO $pdo, string $table_name, string $Workarea ) : array
 * 
 * Return an array of column definitions in the specified table.
 * 
 * @param PDO $pdo
 * @param string $table_name
 * @param string $Workarea
 * @return array
 */
function getColumnsMySql( PDO $pdo, string $table_name, string $Workarea ) : array 
{
    try
    {
        $stmt = $pdo->prepare( "SELECT COLUMN_NAME as column_name, DATA_TYPE as data_type, CHARACTER_MAXIMUM_LENGTH as character_maximum_length, 
        NUMERIC_PRECISION as numeric_precision, NUMERIC_SCALE as numeric_scale, 
        IF(IS_NULLABLE = 'YES', TRUE, FALSE) AS is_nullable 
        FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :Workarea AND TABLE_NAME = :table_name" );

        $return = $stmt->execute( [ 'Workarea' => $Workarea, 'table_name' => $table_name ] );
        if( $return === false )
        {
            throw new dbBridgeException( "Unable to get column definitions for table $table_name." );
        }

        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //echo var_dump( $columns );

        foreach( $columns as &$column ) 
        {
            if( strpos($column[ 'data_type' ], 'timestamp') !== false || $column['data_type'] == 'datetime' ) 
            {
                $column[ 'to_utc_query' ] = "CONVERT_TZ(`{$column['column_name']}`, @@session.time_zone, '+00:00') as `{$column['column_name']}`";
            } else 
            {
                $column['to_utc_query'] = "`{$column['column_name']}`";
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
 * FUNCTION: mysqlTypeTo_stdType( string $mysqlType ): string
 * 
 * Map each mysqlType to a stdType.
 * 
 * @param string $mysqlType
 * @return string
 */
function mysqlTypeTo_stdType( string $mysqlType ): string
{
    static $mysqlTypeToStdType = [
        'tinyint' => stdTypes::TINYINT,
        'smallint' => stdTypes::SMALLINT,
        'mediumint' => stdTypes::INT,
        'int' => stdTypes::INT,
        'bigint' => stdTypes::BIGINT,
        'real' => stdTypes::DOUBLE,
        'float' => stdTypes::FLOAT,
        'double' => stdTypes::DOUBLE,
        'decimal' => stdTypes::DECIMAL,
        'date' => stdTypes::DATE,
        'datetime' => stdTypes::DATETIME,
        'timestamp' => stdTypes::TIMESTAMP,
        'time' => stdTypes::TIME,
        'year' => stdTypes::INT,
        'char' => stdTypes::CHAR,
        'varchar' => stdTypes::STRING,
        'tinytext' => stdTypes::LONG_STRING,
        'text' => stdTypes::LONG_STRING,
        'mediumtext' => stdTypes::LONG_STRING,
        'longtext' => stdTypes::LONG_STRING,
        'binary' => stdTypes::CHARBINARY,
        'varbinary' => stdTypes::BINARY,
        'tinyblob' => stdTypes::BLOB,
        'blob' => stdTypes::BLOB,
        'mediumblob' => stdTypes::BLOB,
        'longblob' => stdTypes::BLOB,
        'enum' => stdTypes::STRING,
        'set' => stdTypes::STRING,
        'bool' => stdTypes::BOOL,
        'boolean' => stdTypes::BOOL,
        'json' => stdTypes::JSON,
        'bit' => stdTypes::BIT,
        'geometry' => stdTypes::BINARY,
        'point' => stdTypes::BINARY,
        'linestring' => stdTypes::BINARY,
        'polygon' => stdTypes::BINARY,
        'multipoint' => stdTypes::BINARY,
        'multilinestring' => stdTypes::BINARY,
        'multipolygon' => stdTypes::BINARY,
        'geometrycollection' => stdTypes::BINARY
    ];

    // If the mysqlType is not supported by the dialect, map it to a dialect type that is the closest match
    if( !isset( $mysqlTypeToStdType[ $mysqlType ] ) )
    {
        $mysqlTypeToStdType[$mysqlType] = stdTypes::UNKNOWN;

        $msg = __METHOD__.'('.__LINE__.')'."FIXME!!! mysqlType '$mysqlType' is not yet supported by stdTypes - Mapped it to: '{$mysqlTypeToStdType[$mysqlType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $mysqlTypeToStdType[ $mysqlType ];
}

/**
 * FUNCTION: stdTypeTo_mysqlType( string $stdType ) : string
 * 
 * Map each stdType to a mysqlType.
 * 
 * @param string $stdType
 * @return string
 */
function stdTypeTo_mysqlType( string $stdType ): string
{
    // We must map EVERY EXISTING stdType, even if it's not a perfect match
    static $stdTypeToMysqlType = [
        stdTypes::NULL => 'varchar',
        stdTypes::BIT => 'bit',
        stdTypes::TINYINT => 'tinyint',
        stdTypes::SMALLINT => 'smallint',        
        stdTypes::INT => 'int',
        stdTypes::BIGINT => 'bigint',
        stdTypes::FLOAT => 'float',
        stdTypes::DOUBLE => 'double',
        stdTypes::DECIMAL => 'decimal',
        stdTypes::SMALLMONEY => 'decimal(10, 4)',
        stdTypes::MONEY => 'decimal(19, 4)',
        stdTypes::STRING => 'varchar',
        stdTypes::CHAR => 'char',
        stdTypes::LONG_STRING => 'text',
        stdTypes::UNICODE_STRING => 'varchar',
        stdTypes::LONG_UNICODE_STRING => 'text',
        stdTypes::CHARBINARY => 'binary',
        stdTypes::BINARY => 'varbinary',
        stdTypes::BLOB => 'blob',
        stdTypes::DATE => 'date',
        stdTypes::TIME => 'time',
        stdTypes::TIME_TZ => 'time',
        stdTypes::DATETIME => 'datetime',
        stdTypes::DATETIME_TZ => 'datetime',
        stdTypes::TIMESTAMP => 'timestamp',
        stdTypes::INTERVAL => 'varchar', // There is no equivalent for interval in MySQL
        stdTypes::BOOL => 'boolean',
        stdTypes::JSON => 'json',
        stdTypes::GUID => 'char(36)',
        stdTypes::AUTO_INCREMENT_TINYINT => 'tinyint auto_increment',
        stdTypes::AUTO_INCREMENT_SMALLINT => 'smallint auto_increment',
        stdTypes::AUTO_INCREMENT_MEDIUMINT => 'mediumint auto_increment',
        stdTypes::AUTO_INCREMENT_INT => 'int auto_increment',
        stdTypes::AUTO_INCREMENT_BIGINT => 'bigint auto_increment',
        stdTypes::UNKNOWN => 'varchar'
    ];

    // If the stdType is not listed above, then the code is incomplete and MUST be fixed!
    if( !isset( $stdTypeToMysqlType[ $stdType ] ) )
    {
        $stdTypeToMysqlType[$stdType] = 'varchar';
        $msg = __METHOD__.'('.__LINE__.')'."FIXME!!! stdType: '$stdType' is not yet supported by __METHOD__ - Mapped it to: '{$stdTypeToMysqlType[$stdType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $stdTypeToMysqlType[$stdType] ?? 'varchar';
}
?>