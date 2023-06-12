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
 * This file contains the driver implementation for SQLite dbBridge driver.
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

// Sqlite database driver class.
/**
 * CLASS SqliteDriver( PDO $pdo ) : sqlDriver
 * 
 * Implements the dbBridge driver for SQLite.
 * 
 * @package dbBridge
 * @subpackage sqlite
 * @version 1.0.0
 * @license MIT
 */
class SqliteDriver extends sqlDriver 
{
    protected function fetchWorkarea() : string
    {
        return $this->Workarea = getWorkareaSQLite( $this->pdo );
    }

    protected function fetchTableNames() : array 
    {
        return getTableNamesSqlite( $this->pdo );
    }
    
    protected function fetchTableColumns( string $table_name ) : array
    {
        if( in_array( $table_name, $this->getTableNames() ) ) 
        {
            return getColumnsSqlite( $this->pdo, $table_name );
        }
        else 
        {
            throw new Exception( "Workarea: '{$this->Workarea}' does not have Table: '$table_name'!" );
        }
    }
}

/**
 * FUNCTION getTableNamesSqlite( PDO $pdo ) : array
 * 
 * Returns an array of table names in the current Workarea.
 * 
 * @param PDO $pdo
 * @return array
 */
function getTableNamesSqlite( PDO $pdo ) : array 
{
    try
    {
        $result = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table'" );
        return $result->fetchAll( PDO::FETCH_COLUMN );
    }
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION columnsSqlLiteToStandard( array $columns ) : array
 * 
 * Converts SQLite column definitions to standard column definitions.
 * 
 * @param array $columns
 * @return array
 */
function columnsSqlLiteToStandard( array $columns ) : array 
{
    try
    {
        $standardColumns = [];

        foreach( $columns as $column ) 
        {
            $typeInfo = extractTypeInfo( $column['type' ] );

            $standardColumns[ strtolower($column[ 'name' ]) ] = [
                'data_type' => $typeInfo[ 'type' ],
                'character_maximum_length' => $typeInfo[ 'length' ],
                'numeric_precision' => $typeInfo[ 'precision' ],
                'numeric_scale' => $typeInfo[ 'scale' ],
                'is_nullable' => !$column['notnull'] ? 'YES' : 'NO',
            ];
        }
        return $standardColumns;
    }
    catch( Exception $e) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION getColumnsSqlite( PDO $pdo, string $table_name ) : array
 * 
 * Returns an array of column definitions for the specified table.
 * 
 * @param PDO $pdo
 * @param string $table_name
 * @return array
 */
function getColumnsSqlite( PDO $pdo, string $table_name ) : array 
{
    try 
    {
        $query = $pdo->prepare( "PRAGMA table_info( $table_name )" );
        
        $result = $query->execute();
        if( $result === false ) 
        {
            throw new Exception( "Failed to execute query: " . $query->errorInfo()[2] );
        }
        $sqlLiteColumns = $query->fetchAll( PDO::FETCH_ASSOC );

        if( ! $sqlLiteColumns ) 
        {
            throw new Exception("Failed to fetch columns for table: $table_name" );
        }

        // Convert to our standard format.
        return columnsSqlLiteToStandard( $sqlLiteColumns );
    } 
    catch ( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION getWorkareaSQLite( PDO $pdo ) : string
 * 
 * Returns the path to the main SQLite database file.
 * We use it to determine and set the Workarea.
 * 
 * @param PDO $pdo
 * @return string
 */
function getWorkareaSQLite(PDO $pdo) : string
{
    $query = $pdo->query("PRAGMA database_list;");
    $databases = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($databases as $database) 
    {
        if ($database['name'] === 'main') 
        {
            return $database['file'];
        }
    }

    throw new Exception('Could not find main SQLite database');
}


/**
 * FUNCTION: sqliteTypeTo_stdType( string $sqliteType )
 * 
 * Map each SQLite type to a stdType.
 * 
 * @param string $sqliteType
 * @return string
 */
function sqliteTypeTo_stdType( string $sqliteType ) : string
{
    static $typeMappings = [
        'integer' => stdTypes::INT,
        'real' => stdTypes::MONEY,   // Could be DOUBLE but let's default to higher precision
        'text' => stdTypes::STRING,
        'blob' => stdTypes::BLOB,
        'null' => stdTypes::NULL
    ];

    return $typeMappings[ strtolower( $sqliteType ) ] ?? stdTypes::UNKNOWN;
}

/**
 * FUNCTION: stdTypeTo_sqliteType( string $stdType )
 * 
 * Map each stdType to a SQLite type.
 * 
 * @param string $stdType
 * @return string
 */
function stdTypeTo_sqliteType( string $stdType ) : string
{
    // We must map EVERY stdType, even if it's not a perfect match
    static $typeMappings = [
        stdTypes::NULL => 'null',
        stdTypes::BIT => 'integer',
        stdTypes::TINYINT => 'integer',
        stdTypes::SMALLINT => 'integer',
        stdTypes::INT => 'integer',
        stdTypes::BIGINT => 'integer',
        stdTypes::FLOAT => 'real',
        stdTypes::DOUBLE => 'real',        
        stdTypes::DECIMAL => 'real',
        stdTypes::SMALLMONEY => 'real',
        stdTypes::MONEY => 'real',        
        stdTypes::STRING => 'text',
        stdTypes::CHAR => 'text',
        stdTypes::LONG_STRING => 'text',
        stdTypes::UNICODE_STRING => 'text',
        stdTypes::LONG_UNICODE_STRING => 'text',
        stdTypes::CHARBINARY => 'blob',
        stdTypes::BINARY => 'blob',
        stdTypes::BLOB => 'blob',
        stdTypes::DATE => 'text',
        stdTypes::TIME => 'text',
        stdTypes::TIME_TZ => 'text',
        stdTypes::DATETIME => 'text',
        stdTypes::DATETIME_TZ => 'text',
        stdTypes::TIMESTAMP => 'text',
        stdTypes::INTERVAL => 'text',
        stdTypes::BOOL => 'integer',
        stdTypes::JSON => 'text',
        stdTypes::GUID => 'text',        
        stdTypes::UNKNOWN => 'text'
    ];

    // If we don't have a mapping, log warning and default to text
    if( ! isset( $typeMappings[$stdType] ) )
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' );
    }

    return $typeMappings[$stdType] ?? 'text';
}
?>