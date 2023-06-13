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
 * Acknowledgments:
 * This code benefited from research time supported by https://www.omie.com.br
 *
 * This file contains driver implementation for PostgreSQL dbBridge driver.
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
 * CLASS: PgsqlDriver
 * 
 * Implements the dbBridge driver for PostgreSQL.
 * 
 * @package dbBridge
 * @subpackage pgsql
 * @version 1.0.0
 * @license MIT
 */
class PgsqlDriver extends sqlDriver 
{
    protected function fetchWorkarea() : string
    {
        return $this->Workarea = $this->pdo->query('SELECT current_schema()')->fetchColumn();
    }
        
    protected function fetchTableNames() : array
    {
        return getTableNamesPgSql( $this->pdo );
    }

    protected function fetchTableColumns( string $table_name ) : array
    {
        return getColumnsPgSql( $this->pdo, $table_name, $this->Workarea );
    }    
}

/**
 * FUNCTION: getTableNamesPgSql( PDO $pdo )
 * 
 * Return an array of table names in the current Workarea.
 * 
 * @param PDO $pdo
 * 
 * @return array
 */
function getTableNamesPgSql( PDO $pdo ) : array 
{
    try
    {
        $result = $pdo->query( "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'" );
        return $result->fetchAll( PDO::FETCH_COLUMN );
    }
    catch (PDOException $e) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: getColumnsPgSql( PDO $pdo, string $table_name, string $Workarea )
 * 
 * Returns an array of column definitions for the specified table.
 * 
 * @param PDO $pdo
 * @param string $table_name
 * @param string $Workarea
 * 
 * @return array
 */
function getColumnsPgSql( PDO $pdo, string $table_name, string $Workarea ) : array
{
    try
    {
        $stmt = $pdo->prepare(
            "SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale,
            CASE WHEN is_nullable = 'YES' THEN TRUE ELSE FALSE END AS is_nullable
            FROM information_schema.columns WHERE table_schema = :Workarea AND table_name = :table_name"
        );

        $result = $stmt->execute( [ 'Workarea' => $Workarea, 'table_name' => $table_name ] );
        if( $result === false ) 
        {
            throw new Exception("Unable to get column definitions for table $table_name.");
        }

        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ( $columns as &$column ) 
        {
            // If column is a datetime type with timezone, add a query to convert data to UTC 
            if ($column['data_type'] === 'timestamp with time zone' || $column['data_type'] === 'time with time zone') 
            {
                $column['to_utc_query'] = $column['column_name'] . " AT TIME ZONE 'UTC' as " . $column['column_name'];
            } else 
            {
                $column['to_utc_query'] = $column['column_name'];
            }
        }
        return $columns;
    } catch (Exception $e) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: pgsqlTypeTo_stdType( string $pgsqlType )
 * 
 * Map each pgsqlType to a stdType.
 * 
 * @param string $pgsqlType
 * 
 * @return string
 */
function pgsqlTypeTo_stdType( string $pgsqlType ): string
{
    // We MUST map EACH pgsqlType to its respective a stdType
    static $pgTypeTostdType = [
        'smallserial' => stdTypes::AUTO_INCREMENT_SMALLINT,
        'serial' => stdTypes::AUTO_INCREMENT_INT,
        'bigserial' => stdTypes::AUTO_INCREMENT_BIGINT,
        'smallint' => stdTypes::INT,
        'integer' => stdTypes::INT,
        'bigint' => stdTypes::INT,
        'decimal' => stdTypes::REAL,
        'numeric' => stdTypes::REAL,
        'real' => stdTypes::REAL,
        'double precision' => stdTypes::REAL,
        'smallserial' => stdTypes::INT,
        'serial' => stdTypes::INT,
        'bigserial' => stdTypes::INT,
        'money' => stdTypes::REAL,
        'character varying' => stdTypes::STRING,
        'varchar' => stdTypes::STRING,
        'character' => stdTypes::CHAR,
        'char' => stdTypes::CHAR,
        'text' => stdTypes::LONG_STRING,
        'bytea' => stdTypes::BLOB,
        'timestamp' => stdTypes::DATETIME,
        'timestamp with time zone' => stdTypes::DATETIME_TZ,
        'date' => stdTypes::DATE,
        'time' => stdTypes::TIME,
        'time with time zone' => stdTypes::TIME_TZ,
        'interval' => stdTypes::INTERVAL,
        'boolean' => stdTypes::BOOL,
        'bit' => stdTypes::BIT,
        'json' => stdTypes::JSON,
        'jsonb' => stdTypes::BINARY,
        'uuid' => stdTypes::GUID
    ];

    // If the pgsqlType is not supported by the dialect, map it to a dialect type that is the closest match
    if( !isset( $pgTypeTostdType[ $pgsqlType ] ) )
    {
        $pgTypeTostdType[$pgsqlType] = stdTypes::UNKNOWN;

        $msg = "__METHOD__ ( __LINE__ )->FIXME!!! pgsqlType '$pgsqlType' is not yet supported by stdTypes - Mapped it to: '{$pgTypeTostdType[$pgsqlType]}'.";

        // Log a warning message
        log_dbBridge( $msg, debugFlags::DEBUG_FIXME );
    }

    return $pgTypeTostdType[ $pgsqlType ];
}

/**
 * FUNCTION: stdTypeTo_pgsqlType( string $stdType )
 * 
 * Map each stdType to a pgsqlType.
 * 
 * @param string $stdType
 * 
 * @return string
 */
function stdTypeToType_pgsqlType( string $stdType ): string
{
    // We must map EVERY EXISTING stdType, even if it's not a perfect match
    static $stdTypeToPgType = [
        stdTypes::NULL => 'text',
        stdTypes::BIT => 'bit',
        stdTypes::TINYINT => 'smallint',
        stdTypes::SMALLINT => 'smallint',        
        stdTypes::INT => 'integer',
        stdTypes::BIGINT => 'bigint',
        stdTypes::FLOAT => 'real',
        stdTypes::DOUBLE => 'double precision',
        stdTypes::DECIMAL => 'decimal',
        stdTypes::SMALLMONEY => 'money',
        stdTypes::MONEY => 'money',
        stdTypes::STRING => 'varchar',
        stdTypes::CHAR => 'char',
        stdTypes::LONG_STRING => 'text',
        stdTypes::UNICODE_STRING => 'character varying',
        stdTypes::LONG_UNICODE_STRING => 'text',
        stdTypes::CHARBINARY => 'bytea',
        stdTypes::BINARY => 'bytea',
        stdTypes::BLOB => 'bytea',
        stdTypes::DATE => 'date',
        stdTypes::TIME => 'time',
        stdTypes::TIME_TZ => 'time with time zone',
        stdTypes::DATETIME => 'timestamp',
        stdTypes::DATETIME_TZ => 'timestamp with time zone',
        stdTypes::TIMESTAMP => 'timestamp',
        stdTypes::INTERVAL => 'interval',
        stdTypes::BOOL => 'boolean',
        stdTypes::JSON => 'json',
        stdTypes::GUID => 'uuid',
        stdTypes::AUTO_INCREMENT_TINYINT => 'smallserial',
        stdTypes::AUTO_INCREMENT_SMALLINT => 'smallserial',
        stdTypes::AUTO_INCREMENT_MEDIUMINT => 'serial',
        stdTypes::AUTO_INCREMENT_INT => 'serial',
        stdTypes::AUTO_INCREMENT_BIGINT => 'bigserial',
        stdTypes::UNKNOWN => 'text'
    ];

    // If the stdType is not listed above than the code is incomplete and MUST be fixed!!!
    if( !isset( $stdTypeToPgType[ $stdType ] ) )
    {
        $stdTypeToPgType[$stdType] = 'text';
        $msg = "__METHOD__ ( __LINE__ )->FIXME!!! stdType: '$stdType' is not yet supported by __METHOD__ - Mapped it to: '{$stdTypeToPgType[$stdType]}'.";

        // Log a warning message
        log_dbBridge( $msg );
    }

    return $stdTypeToPgType[$stdType] ?? 'text';
}
?>
