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
 * This file contains general purpose support functions for dbBridge. 
 * 
 * @package dbBridge* 
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

/**
 * FUNCTION: LoadEnvFile( string $sFile, bool $bOverride = true ) : bool
 * 
 * Load an environment file into the $_SERVER and $_ENV superglobals.
 * 
 * @param string $sFile
 * @param bool   $bOverride
 * 
 * @return bool
 * 
 * @throws Exception
 */
function LoadEnvFile( string $sFile, bool $bOverride = true ) : bool
{
    $bSet = false;

    if( ! file_exists( $sFile ) )
    {
        throw new Exception('Env file not found! [' . $sFile . ']' );
    }

    $aLines = file($sFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach( $aLines as $sLine )
    {
        if( strpos(trim($sLine), '#' ) === 0 )
        {
            continue;
        }

        if (strpos($sLine, '=') === false) 
        {
            continue;
        }

        list( $sKey, $sValue ) = explode( '=', $sLine, 2 );
        $sKey   = trim( $sKey );
        $sValue = trim( $sValue );

        // Remove matching quotes from the beginning and end of a string
        if( ( substr($sValue, 0, 1 ) == '"' && substr( $sValue, -1 ) == '"' ) || 
            ( substr($sValue, 0, 1 ) == "'" && substr( $sValue, -1 ) == "'" ) )
        {
            $sValue = substr( $sValue, 1, -1 );
        }

        if( $bOverride  || ! isset($_SERVER[ $sKey ] ) )
        {
            $_SERVER[$sKey] = $sValue;
            $bSet = true;
        }

        if( $bOverride  || ! isset( $_ENV[ $sKey ] ) )
        {
            $_ENV[$sKey] = $sValue;
            $bSet = true;
        }    
    }

    return $bSet;
}

/**
 * FUNCTION: sanitizeString( string $value ) : string
 * 
 * Remove non-printable characters from a string.
 * 
 * @param string $value
 * 
 * @return string
 */
function sanitizeString( string $value ) : string
{
    // Remove non-printable characters
    $value = preg_replace('/[^[:print:]]/', '', $value);
    return $value;
}

/**
 * FUNCTION: getReservedWords() : array
 * 
 * Return an array of reserved words for all supported databases.
 * 
 * TODO: This function should be moved to the database specific class.
 *   
 * @return array
 */
function getReservedWords() 
{
    // Array of reserved words
    static $reservedColumnNames = [
        'ACCESSIBLE', 'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'ASENSITIVE', 'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 
        'BOTH', 'BY', 'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK', 'COLLATE', 'COLUMN', 'CONDITION', 'CONSTRAINT', '
        CONTINUE', 'CONVERT', 'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR', 'DATABASE', 
        'DATABASES', 'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DEFAULT', 'DELAYED', 'DELETE', 
        'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DROP', 'DUAL', 'EACH', 'ELSE', 'ELSEIF', 'ENCLOSED', 
        'ESCAPED', 'EXISTS', 'EXIT', 'EXPLAIN', 'FALSE', 'FETCH', 'FLOAT', 'FLOAT4', 'FLOAT8', 'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT', 
        'GENERATED', 'GET', 'GRANT', 'GROUP', 'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'IF', 'IGNORE', 
        'IN', 'INDEX', 'INFILE', 'INNER', 'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8', 'INTEGER', 'INTERVAL', 
        'INTO', 'IO_AFTER_GTIDS', 'IO_BEFORE_GTIDS', 'IS', 'ITERATE', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 
        'LINEAR', 'LINES', 'LOAD', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'LOW_PRIORITY', 'MASTER_BIND', 
        'MASTER_SSL_VERIFY_SERVER_CERT', 'MATCH', 'MAXVALUE', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MINUTE_MICROSECOND', 
        'MINUTE_SECOND', 'MOD', 'MODIFIES', 'NATURAL', 'NOT', 'NO_WRITE_TO_BINLOG', 'NULL', 'NUMERIC', 'ON', 'OPTIMIZE', 'OPTION', 'OPTIONALLY', 
        'OR', 'ORDER', 'OUT', 'OUTER', 'OUTFILE', 'PARTITION', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'PURGE', 'RANGE', 'READ', 'READS', 'READ_WRITE', 
        'REAL', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE', 'REQUIRE', 'RESIGNAL', 'RESTRICT', 'RETURN', 'REVOKE', 'RIGHT', 
        'RLIKE', 'SCHEMA', 'SCHEMAS', 'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR', 'SET', 'SHOW', 'SIGNAL', 'SMALLINT', 'SPATIAL', 
        'SPECIFIC', 'SQL', 'SQLEXCEPTION', 'SQLSTATE', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT', 'SSL', 'STARTING', 
        'STRAIGHT_JOIN', 'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE', 'UNDO', 'UNION', 
        'UNIQUE', 'UNLOCK', 'UNSIGNED', 'UPDATE', 'USAGE', 'USE', 'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VALUES', 'VARBINARY', 'VARCHAR', 
        'VARCHARACTER', 'VARYING', 'WHEN', 'WHERE', 'WHILE', 'WINDOW', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL'
    ];
    
    return $reservedColumnNames;
}

// createDatabase( $pdo, $dbName );
/**
 * FUNCTION: createDatabase( PDO $pdo, string $dbName )
 * 
 * Create a database if it does not exist.
 * 
 * @param PDO $pdo
 * @param string $dbName
 * 
 * @return void
 */
function createDatabase( PDO $pdo, string $dbName )
{
    try 
    {
        $query = "CREATE DATABASE IF NOT EXISTS $dbName";
        $pdo->exec($query);
        $pdo->exec("USE $dbName");
    } 
    catch ( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION: dbDriverName( PDO $pdo ) : string
 * 
 * Returns the name of the database driver.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbDriverName( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
}

/**
 * FUNCTION: dbDriverVersion( PDO $pdo ) : string
 * 
 * Returns the version of the database driver.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbDriverVersion( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_DRIVER_VERSION );
}

/**
 * FUNCTION: dbServerVersion( PDO $pdo ) : string
 * 
 * Returns the version of the database server.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbServerVersion( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_SERVER_VERSION );
}

/**
 * FUNCTION: dbServerInfo( PDO $pdo ) : string
 * 
 * Returns the database server information.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbServerInfo( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_SERVER_INFO );
}

/**
 * FUNCTION: dbServerName( PDO $pdo ) : string
 * 
 * Returns the name of the database server.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbServerName( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_SERVER_NAME );
}

/**
 * FUNCTION: dbHostInfo( PDO $pdo ) : string
 * 
 * Returns the host information for the database server.
 * 
 * @param PDO $pdo
 * 
 * @return string
 */
function dbHostInfo( PDO $pdo ) : string
{
    return $pdo->getAttribute( PDO::ATTR_CONNECTION_STATUS );
}

/**
 * FUNCTION: getDbDataTypes( string $dialect ) : array
 * 
 * Returns an array of data types for the specified database dialect
 * with mapping to the most apprpriate PDO::PARAM_<TYPE>.
 * 
 * TODO: Should be a Method of the respective dbDriver class
 * 
 * @param string $dialect
 * 
 * @return array [ 'data_type' => PDO::PARAM_<TYPE> ]
 */
function getDbDataTypes( string $dialect ) : array 
{
    // Common SQL data types and their respective PDO constants
    $commonTypes = [
        'CHAR' => PDO::PARAM_STR,
        'VARCHAR' => PDO::PARAM_STR,
        'TEXT' => PDO::PARAM_STR,
        'BLOB' => PDO::PARAM_LOB,
        'INTEGER' => PDO::PARAM_INT,
        'BIGINT' => PDO::PARAM_INT,
        'SMALLINT' => PDO::PARAM_INT,
        'BOOLEAN' => PDO::PARAM_BOOL,
    ];

    // Additional or different types for specific dialects
    $mysqlTypes = [
        'TINYINT' => PDO::PARAM_INT,
        'DECIMAL' => PDO::PARAM_STR,
        'FLOAT' => PDO::PARAM_STR,
        'DOUBLE' => PDO::PARAM_STR,
        'DATE' => PDO::PARAM_STR,
        'DATETIME' => PDO::PARAM_STR,
    ];

    $pgsqlTypes = [
        'SERIAL' => PDO::PARAM_INT,
        'BIGSERIAL' => PDO::PARAM_INT,
        'REAL' => PDO::PARAM_STR,
        'DOUBLE PRECISION' => PDO::PARAM_STR,
        'TIMESTAMP' => PDO::PARAM_STR,
    ];

    $oracleTypes = [
        'NUMBER' => PDO::PARAM_STR,
        'FLOAT' => PDO::PARAM_STR,
        'DATE' => PDO::PARAM_STR,
        'TIMESTAMP' => PDO::PARAM_STR,
    ];

    $mssqlTypes = [
        'TINYINT' => PDO::PARAM_INT,
        'DECIMAL' => PDO::PARAM_STR,
        'FLOAT' => PDO::PARAM_STR,
        'DATETIME' => PDO::PARAM_STR,
        'SMALLDATETIME' => PDO::PARAM_STR,
        'MONEY' => PDO::PARAM_STR,
        'SMALLMONEY' => PDO::PARAM_STR,
        'UNIQUEIDENTIFIER' => PDO::PARAM_STR,        
    ];

    $sqliteTypes = [
        'NULL' => PDO::PARAM_NULL,
        'INTEGER' => PDO::PARAM_INT,
        'REAL' => PDO::PARAM_STR,
        'TEXT' => PDO::PARAM_STR,
        'BLOB' => PDO::PARAM_LOB,
    ];
    
    switch( strtolower( $dialect ) ) 
    {
        case 'mysql':
            return array_merge( $commonTypes, $mysqlTypes );
        case 'pgsql':
            return array_merge( $commonTypes, $pgsqlTypes );
        case 'oracle':
            return array_merge( $commonTypes, $oracleTypes );
        case 'mssql':
            return array_merge( $commonTypes, $mssqlTypes );
        case 'sqlite':
            return array_merge( $commonTypes, $sqliteTypes ); 
        default:
            throw new dbBridgeException( "Unknown database dialect: $dialect" );
    }
}

/**
 * FUNCTION transformColumnDefs( array $columnDefs, string $sourceDialect, string $targetDialect ): array
 * 
 * Returns an array of column definitions transformed from source dialect to the conventions of the
 * target dialect.
 * 
 * The source dialect is the dialect of the database from which the column definitions were obtained.
 * The target dialect is the dialect of the database to which the column definitions are to be applied.
 * 
 * The column definitions are transformed using an interim standard type stdType::CONSTANT, which is 
 * universal global list which provide a 1 to 1 maping form all known sourc type. The stdType may
 * include a fixed length notation '(n)', which when present will override the 'maximum_character_length'
 * of the source type definition.* 
 * 
 * @param array $columnDefs
 * @param string $sourceDialect
 * @param string $targetDialect
 * @return array
 * @throws Exception
 */
function transformTableColumnDefs( array $columnDefs, string $sourceDialect, string $targetDialect ): array
{
    $result = [];

    // Get the functions to convert types from source to standard and from standard to target
    $sourceToStdFunc = "\dbBridge\\{$sourceDialect}TypeTo_stdType";
    $stdToTargetFunc = "\dbBridge\\stdTypeTo_{$targetDialect}Type";

    foreach( $columnDefs as $columnDef )
    {
        $columnName = $columnDef[ 'column_name' ];
        $sourceType = $columnDef[ 'data_type' ];

        $bToFixedLength = false;
        $stdType = $sourceToStdFunc( $sourceType );

        // Check for reserved words and suffix the column name if necessary
        if( in_array( strtoupper( $columnName ), getReservedWords() ) )
        {
            log_dbBridge( "Source: '$columnName' is a reserved word  - appended: '_'" . PHP_EOL, debugFlags::DEBUG_TRANSFORM_RESERVED );
            $columnDef[ 'source_name' ] = $columnName;
            $columnName .= '_';
            $columnDef[ 'column_name' ] = $columnName;
        }
        
        log_dbBridge( "Source: '$columnName' Type: '$sourceType' => stdType: '$stdType'" . PHP_EOL, debugFlags::DEBUG_TRANSFORM_SOURCE );

        /*
           Check for '(n)' notation in the resulting stdType - indicating that fixed
           length is required for the target type. If found, remove the notation from
           the stdType and set the specified fixed length in the columnDef.
        */
        if( preg_match( '/\((\d+)\)/', $stdType, $matches ) )
        {
            // Set the flag to indicate that we have forced a new fixed length
            $bToFixedLength = true;

            // Get the fixedLength from the notation stdType definition            
            $fixedLength = $matches[ 1 ];

            // Remove the fixedLength from the stdType
            $stdType = preg_replace( '/\(\d+\)/', '', $stdType );            

            // Set the fixedLength in the columnDef
            $columnDef[ 'maximum_character_length' ] = $fixedLength;
        }

        // Get the target type for the interim stdType
        $targetType = $stdToTargetFunc( $stdType );
        log_dbBridge( "Target: '$columnName' Type: '$sourceType' <= stdType: '$stdType'" . PHP_EOL, debugFlags::DEBUG_TRANSFORM_TARGET );

        // Get the PDO type for the target type
        $pdoType = $targetDbDataTypes[ $targetType ] ?? PDO::PARAM_STR; // default to PARAM_STR if unknown type

        // Use the original definition as the base our new definition
        $newColumnDef = $columnDef;

        // Add the original type to the new definition
        $newColumnDef[ 'original_type' ] = $sourceType ;

        // Set the new type in the new definition
        $newColumnDef[ 'data_type' ] = $targetType;

        // Set the respective PDO type of the target new type for binding it into parameters
        $newColumnDef[ 'pdo_type' ] = $pdoType;

        // Log the transformation
        log_dbBridge( "Transformed column: '$columnName' from: '$sourceType' to: '$targetType' (PDO type: '$pdoType')" .
                      ($bToFixedLength ? " (fixed length: $fixedLength)" : ''), debugFlags::DEBUG_TRANSFORM_TRANSFORMED );

        // Overwrite the original column definition with the new one
        $result[ $columnName ] = $newColumnDef;
    }

    // Return the transformed column definitions
    return $result;
}

/**
 * FUNCTION compileCreateTableQuery( string $table, array $columns, string $dialectName ): string
 * 
 * Returns a 'CREATE TABLE ...' query string from a table name and an array of column definitions.
 * 
 * 
 * @param string $table
 * @param array $columns
 * @param string $dialectName
 * @return string
 */
function compileCreateTableQuery( string $table, array $columns, string $dialectName ): string
{
    $createTableQuery = "CREATE TABLE $table (";

    foreach( $columns as $columnData ) 
    {
        $columnName = $columnData[ 'column_name' ];

        $createTableQuery .= $columnName . " " . $columnData[ 'data_type' ];

        if( isset( $columnData[ 'character_maximum_length' ] ) && $columnData[ 'character_maximum_length' ]  > 0 ) 
        {
            $createTableQuery .= "(" . $columnData['character_maximum_length'];
            if( isset( $columnData['scale'] ) ) 
            {
                $createTableQuery .= "," . $columnData[ 'scale' ];
            }
            $createTableQuery .= ")";
        }

        // Handle nullable
        if( $dialectName !== 'sqlite' )
        {
            // Use IS NULLABLE information
            $createTableQuery .= $columnData['is_nullable'] ? " NULL" : " NOT NULL";
        }

        $createTableQuery .= ', ';
    }

    $createTableQuery = rtrim( $createTableQuery, ', ' ) . ')';
    log_dbBridge( "Query: '$createTableQuery'", debugFlags::DEBUG_QUERY_CREATE );

    return $createTableQuery;
}

/**
 * Class overwriteFlags
 * 
 * Constants for the overwrite flag
 * 
 * NEVER:          Never overwrite
 * ASK:            Ask before overwriting
 * SKIP:           Skip overwriting the table
 * OVERWRITE_EMPTY Overwrite if the target table is empty
 * OVERWRITE:      Overwrite if the target table is not empty
 * OVERWRITE_ALL:  Overwrite all tables without asking
 * 
 */
class overwriteFlags
{
    const NEVER  = -1;
    const ASK    = 0;
    const SKIP   = 1;
    const OVERWRITE_EMPTY = 2;
    const OVERWRITE       = 3;
    const OVERWRITE_ALL   = 4;

    const asString = [
        self::NEVER => 'NEVER',
        self::ASK => 'ASK',
        self::SKIP => 'SKIP',
        self::OVERWRITE_EMPTY => 'OVERWRITE_EMPTY',
        self::OVERWRITE => 'OVERWRITE',
        self::OVERWRITE_ALL => 'OVERWRITE_ALL'
    ];

    static private $setFlag = self::ASK;

    /**
     * FUNCTION getFlag(): int
     * 
     * Returns the current overwrite flag
     * 
     * @return int
     */
    static function getFlag(): int
    {
        return self::$setFlag;
    }

    /**
     * FUNCTION setFlag( int $setNewFlag ): int
     * 
     * Sets the overwrite flag to a new value
     * 
     * @param int $setNewFlag
     * @return int
     * @throws Exception
     */
    static function setFlag( int $setNewFlag ): int
    {
        if( $setNewFlag < self::NEVER || $setNewFlag > self::OVERWRITE_ALL )
        {
            throw new Exception( "Invalid overwrite flag: $setNewFlag" );
        }

        self::$setFlag = $setNewFlag;

        return self::$setFlag;
    }
}

/**
 * FUNCTION tableExists( PDO $pdo, string $table ): bool
 * 
 * Checks if a table exists in a database from a PDO connection and a table name.
 * 
 * Returns true if the table exists, false otherwise.
 * 
 * @param PDO $pdo
 * @param string $table
 * @return bool
 */
function tableExists( PDO $pdo, string $table ): bool
{
    $query = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE :table";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['table' => $table]);
    
    if( $stmt->rowCount() > 0 ) 
    {
        while( $row = $stmt->fetch(PDO::FETCH_ASSOC ) ) 
        {
            if( $row[ 'table_name' ] === $table ) 
            {
                return true;
            }
        }
    }

    return false;    
}

/**
 * FUNCTION tableDrop( PDO $pdo, string $tableName ): bool
 * 
 * Drops a table from a database from a PDO connection and a table name.
 * 
 * Returns true if the table was dropped or if the table did not exist, false otherwise.
 * 
 * @param PDO $pdo
 * @param string $tableName
 * @return bool
 */
function tableDrop( PDO $pdo, string $tableName ) : bool
{
    // Check if table exists
    if( tableExists( $pdo, $tableName ) ) 
    {
        log_dbBridge( "Dropping table '$tableName'..." . PHP_EOL, debugFlags::DEBUG_OVERWRITE );
        $query = $pdo->prepare( "DROP TABLE " . $tableName );
        return $query->execute();
    } 
    else 
    {
        // Table does not exist
        return true;
    }
}

/**
 * FUNCTION createTable( PDO $pdoTarget, string $table, array $columns, string $dialectName ): bool
 * 
 * Creates a table in the target database from a PDO connection,
 * a table name and an array of column definitions.
 * 
 * @param PDO $pdoTarget
 * @param string $table
 * @param array $columns
 * @param string $dialectName
 * @return bool
 */
function createTable( PDO $pdoTarget, string $table, array $columns, string $dialectName ) : bool
{
    /*
      We use the class static function setFlag() to set the GLOBAL overwrite flag
      We use the class static function getFlag() to store our GLOBAL current overwrite flag
    */

    log_dbBridge( "Creating Table: '$table'" . PHP_EOL, debugFlags::DEBUG_ALWAYS );

    $overwriteFlag = overwriteFlags::getFlag();

    if( $overwriteFlag === overwriteFlags::OVERWRITE || $overwriteFlag === overwriteFlags::OVERWRITE_ALL )
    {
        log_dbBridge( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                      "and Table '$table' is not empty - Migration will OVERWRITE!", debugFlags::DEBUG_OVERWRITE );

        try
        {
            if( ! tableDrop( $pdoTarget, $table ) )
            {
                throw new dbBridgeException( "Error dropping table '$table'." );
            }
        }
        catch( Exception $e )
        {
            // if dbBridgeException no need to wrap it 
            if( $e instanceof dbBridgeException )
            {
                throw $e;
            }
            else
            {
                throw dbBridgeException( "Error dropping table '$table': " . $e->getMessage(), $e->getCode(), $e );
            }
        }
             
    }
    elseif( tableExists( $pdoTarget, $table ) )
    {        
        if( $overwriteFlag === overwriteFlags::NEVER )
        {
            throw new dbBridgeException( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                                         "', and Table '$table' already exists. Migration aborted." );
        }
        else if( $overwriteFlag === overwriteFlags::SKIP )
        {
            return false;
        }
        else if( $overwriteFlag === overwriteFlags::OVERWRITE_EMPTY )
        {
            // Check if the table is empty
            $rowCount = $pdoTarget->query("SELECT COUNT(*) FROM $table")->fetchColumn();            
            if( $rowCount > 0 )
            {
                dbBridge_log( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                              "', and Table '$table' is not empty - Migration skipped.", debugFlags::DEBUG_OVERWRITE );
                return false;
            }
        }
        else if( $overwriteFlag === overwriteFlags::OVERWRITE )
        {
            dbBridge_log( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                          "', and Table '$table' is not empty - Migration will OVERWRITE!", debugFlags::DEBUG_OVERWRITE );
        }
        else if( $overwriteFlag === overwriteFlags::OVERWRITE_ALL )
        {
            dbbBridge_log( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                           "', and Table '$table' is not empty - Migration will OVERWRITE ALL!!!", debugFlags::DEBUG_OVERWRITE );
        }
        else if( $overwriteFlag === overwriteFlags::ASK )
        {
            // Ask the user what to do
            echo "Table '$table' already exists. What do you want to do?" . PHP_EOL;
            echo "  [S]kip"           . PHP_EOL;
            echo "  [O]verwrite"      . PHP_EOL;
            echo "  [!]Overwrite All" . PHP_EOL;
            echo "  [Q]uit"           . PHP_EOL;

            $choice = strtolower( readline( "Your choice: " ) );
            switch ($choice) 
            {
                case '!':
                    log_dbBridge( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] . 
                                  "', and Table '$table' is not empty - User chose to OVERWRITE ALL!!!", debugFlags::DEBUG_OVERWRITE );
                    overwriteFlags::setFlag( overwriteFlags::OVERWRITE_ALL );
                    break;
                case 'q':
                    throw new dbBridgeException( "Migration aborted by user." );
                    break;
                case 's':
                    return false;
                case 'o':
                    log_dbBridge( "overwriteFlag: '" . overwriteFlags::asString[ $overwriteFlag ] .
                                  "}', and Table '$table' is not empty - User chose to OVERWRITE!", debugFlags::DEBUG_OVERWRITE );
                    break;
                default:
                    throw new dbBridgeException( "Invalid choice. Migration aborted." );
            }
        }
        
        try
        {
            if( ! tableDrop( $pdoTarget, $table ) )
            {
                throw new dbBridgeException( "Error dropping table '$table'." );
            }
        }
        catch( Exception $e )
        {
            // if dbBridgeException no need to wrap it 
            if( $e instanceof dbBridgeException )
            {
                throw $e;
            }
            else
            {
                throw new dbBridgeException( "Error dropping table '$table' [" . $e->getMessage() . " / " . $e->getCode(), 0, $e );
            }
        }
    }

    $createTableQuery = compileCreateTableQuery( $table, $columns, $dialectName );

    $pdoTarget->exec( $createTableQuery );

    return true;
}

/**
 * FUNCTION createTables( PDO $pdoTarget, array $tableDefinitions, string $dialectName, int  ): void
 * 
 * Creates tables in the target database from a PDO connection,
 * an array of table definitions and a dialect name.
 * 
 * @param PDO $pdoTarget
 * @param array $tableDefinitions
 * @param string $dialectName
 * @return void
 * @throws Exception
 */
function createTables( PDO $pdoTarget, array $tableDefinitions, string $dialectName ) : void
{
    try
    {
        foreach( $tableDefinitions as $table => $columns )
        {
            createTable( $pdoTarget, $table, $columns, $dialectName );
        }
    } 
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION openDb( string $dsn, string $userName, string $password, int $timeout = 600 ): PDO
 * 
 * Opens a database connection and returns a PDO object.
 * 
 * @param string $dsn
 * @param string $userName
 * @param string $password
 * @param int $timeout
 * @return PDO
 * @throws Exception
 */
function openDb( string $dsn, string $userName, string $password, int $timeout = 600 ) : PDO
{
    try
    {
        //echo "Opening database: $dsn, $userName $password\n";

        $pdo = new PDO( $dsn, $userName, $password );

        if( ! $pdo )
        {
            throw new dbBridgeException( 'Failed to open database:' . $dsn . PHP_EOL );
        }

        $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $pdo->setAttribute( PDO::ATTR_TIMEOUT, $timeout ); 

        return $pdo;
    }
    catch( Exception $e )
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}
?>