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
 * This file contains the main class of the dbBridge package.
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


USE \DateTime;
USE \DateTimeZone;
USE \DateInterval;
USE \DatePeriod;

// Error and Exception handling
require_once 'dbb.err.php';

// Utility functions
require_once 'dbb.funcs.php';

// The odbc dbBridge dbDriver
require_once 'dbb.odbc.php';

/*
  Explictly used in the test function (openMsSqlDb & openMySqlDb) 
  - otherwise not needed.
*/
require_once 'dbb.mssql.php';
require_once 'dbb.mysql.php';

/*
Loaded in R/T by dbAbstractor as needed.

require_once 'dbb.oracle.php';
require_once 'dbb.pgsql.php';
require_once 'dbb.sql.php';
require_once 'dbb.sqlite.php';
*/

require_once 'dbb.stdtypes.php';

/**
 * CLASS: dbAbstractor( PDO $pdo, string $Workarea ) : dbDriver
 * 
 * The dbAbstractor class is the main class of the dbBridge package.
 * It is used to create a bridge between multiple SQL databases using
 * custom dbDriver objects wrappers of respective PDO drivers.
 * 
 * Arguments:
 *  - $pdo: A PDO object.
 *  - $Workarea: The name of the Workarea (database) to use.
 * 
 * @property PDO $pdo
 * @property string $Workarea
 * @property dbDriver $driver
 * @property string $driverName
 */
class dbAbstractor 
{
    protected PDO $pdo;
    protected string $Workarea;
    protected dbDriver $driver;
    protected string $driverName;

    public function __construct( PDO $pdo, string $Workarea )
    {        
        try
        {
            $this->pdo = $pdo;
            $this->Workarea = $Workarea;
                
            $this->driverName = $pdo->getAttribute( PDO::ATTR_DRIVER_NAME );

            // Check if a file dbb.{driverName}.php exists. If so, include it.
            $driverFile = "dbb.$this->driverName.php";
            if( file_exists( $driverFile ) )
            {
                require_once $driverFile;
            }
            else
            {
                // Will be wrapped in a dbBridgeException in catch below.
                throw new Exception( "Driver file $driverFile not found" );
            }

            $this->driver = match ( $this->driverName ) 
            {
                'mysql' => new MySQLDriver( $pdo, $Workarea ),
                'pgsql' => new PgsqlDriver( $pdo, $Workarea ),
                'sqlite' => new SqliteDriver( $pdo, $Workarea ),
                'mssql' => new MsSqlDriver( $pdo, $Workarea ),
                'oracle' => new OracleDriver( $pdo, $Workarea ),
                'odbc' => new odbcDriver( $pdo, $Workarea ),
                default => throw new Exception( "Unsupported driver: $driverName" ),
            };

            // Set the PDO error mode to exception!
            $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }
        catch( Exception $e )
        {
            throw new dbBridgeException( 'dbAbstractor failed to instanciate!', 0, $e );
        }
        finally
        {
            // Close the connection
            $pdo = null;
        }
    }

    /**
     * FUNCTION: getTableNames() : array
     * 
     * Returns an array of table names in the Workarea.
     * 
     * @return array
     */
    public function getTableNames() : array 
    {
        try
        {
            return $this->driver->getTableNames();
        }
        catch( Exception $e )
        {
            throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
        }
    }

    /**
     * FUNCTION: getColumns( string $table_name ) : array
     * 
     * Returns an array of column definitions for the given table.
     * 
     * @param string $table_name
     * @return array
     */
    public function getColumns( string $table_name ) : array
    {
        try
        {
            return $this->driver->getColumns( $table_name );
        }
        catch( Exception $e )
        {
            throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
        }
    }

    /**
     * FUNCTION: createTables( array $tableColumnDefinitions )
     * 
     * Creates tables in the Workarea based on the given tableColumnDefinitions.
     * 
     * @param array $tableColumnDefinitions
     * @return void
     */
    public function createTables( array $tableColumnDefinitions )
    {
        createTables( $this->pdo, $tableColumnDefinitions, $this->getDialect() );
    }

    /**
     * FUNCTION: getDialect() : string
     * 
     * Returns the SQL dialect of the Workarea.
     * 
     * Used by createTables() and importDb() to determine the SQL dialect to use.
     * 
     * @return string
     */
    public function getDialect() : string
    {
        if( $this->driverName === 'odbc' )
        {
            return $this->driver->getDialect();
        }
        else
        {
            return $this->driverName;
        }                
    }

    /**
     * FUNCTION: importDb( dbAbstractor $dbSource )
     * 
     * Imports the database from the given dbSource.
     * 
     * Here's how it works:
     * 
     * 1. getTableColums() retrieves the column definitions for the source table.    
     *   This is done by calling the dialects's native:
     *
     *       fetchTableColumnDefinitions()
     *
     *  The result is an array of column definitions for the source table.
     *  including [ 'column_name' ], [ 'data_type' ], [ 'is_nullable' ] //TODO: , [ 'column_default' ]
     *  it may also include additional dialect specific column definition tags. 
     *  like 'to_utc_query' (implemented in all the included dbDrivers). 
     *       
     * 2. transformTableColumnDefs( array $columnDefs, string $sourceDialect, string $targetDialect ): array
     *   It first utilizes:
     *  
     *       {source-dialect}TypeTo_stdType( string $sourceType )
     *  
     *   to convert the native source dialect specific 'data_type' to its respective stdType.
     *
     *   Then it calls:
     *  
     *       stdTypeTo_{target-dialect}( string $stdType ) 
     *       
     *   to convert the stdType to the target dialect best matching 'data_type'. 
     *   This results in an EXTENDED tableColumnDefinitions including 
     *   additional [ 'original_type' ] as well as a deduced [ 'pdo_type' ]. 
     *   
     *   This funtion also check for '(n)' notation on the stdType 
     *   (for cases like a decision to convert MsSql's 'uniqueidentifier' 
     *   to CHARBINARY(16)) - if found, it extracts such length and store
     *   it (overriding prior length if any) in the ['character_maximum_length']
     *   of the new taget based extended column definition.
     *
     *   This is the core of the type mapping plan.*
     *
     * 3. compileCreateTableQuery()
     *
     *   Uses info from the extended target definitions we gathered in transformTableColumnDefs() to compile 
     *   an appropriate:
     *  
     *       'CRETAE TABLE ...' 
     *
     *   statement for the specific dialect Server. It allows for 
     *   dialect specific customization of the table creation statement.
     *
     *   TODO: It also allows for the support of dialect specific attributes
     *   (e.g. MySql's 'AUTO_INCREMENT' or MsSql's 'IDENTITY(1,1)')
     *
     *   TODO: Move this to the dialect specific dbDriver class.
     *
     * 4. compileSelectQuery() 
     *
     *   Uses all the info gathered as per above to compile the proper:
     *   
     *       'SELECT <Value> AS <NAME>...'
     *   
     *   to generate the NAMED source values in the form we want/need.
     *
     * 5. compileInsertQuery()
     *
     *   Finally here we compile the:
     *
     *       'INSERT INTO <table> ( <columns>) VALUES <placeholders>
     *       
     *   so that values retrieved from the Source-Table by #3 will be saved 
     *   correctly to the Target-Table. This is where we use the extended
     *   column definitions to determine the correct PDO::PARAM_* type
     *   for each column. As as utilize the already dialect specific
     *   'to_utc_query' to convert the value to UTC. 
     * 
     * @param dbAbstractor $dbSource
     * @return void
     */
    public function importDb( dbAbstractor $dbSource )
    {        
        try
        {
            // Create the target database
            createDatabase( $this->pdo, $dbSource->Workarea );
            $this->Workarea = $dbSource->Workarea;

            log_dbBridge( "Importing database: '$dbSource->Workarea'..." . PHP_EOL, debugFlags::DEBUG_ALWAYS );

            // Saving the source transformation "schema" for record keeping, and possible future use.
            log_dbBridge( "Creating dbBridge_schema..." . PHP_EOL, debugFlags::DEBUG_ALWAYS );

            // Drop the schema if exeists
            if( tableExists( $this->pdo, 'dbBridge_schema' ) )
            {
                if( ! tableDrop( $this->pdo, 'dbBridge_schema') )
                {
                    throw new dbBridgeException( 'Failed to drop dbBridge_schema!' );
                }
            }

            if( ! tableCreate_dbBridgeSchema( $this->pdo ) )
            {
                throw new dbBridgeException( 'Failed to create dbBridge_schema!' );
            }

            foreach( $dbSource->getTableNames() as $table_name )
            {
                // Skip sys tables
                if( substr( $table_name, 0, 3 ) == 'sys' ) continue;

                log_dbBridge( "Loading Table definitions: '$table_name'" . PHP_EOL, debugFlags::DEBUG_ALWAYS );
                $sourceTableDefinitions = $dbSource->getColumns( $table_name );
                
                $sourceDialect = $dbSource->getDialect();
                $targetDialect = $this->getDialect();

                log_dbBridge( "Creating migration plan for table: $table_name From: '$sourceDialect' To: '$targetDialect'" . PHP_EOL, debugFlags::DEBUG_ALWAYS ); 
                $sourceImportDefinitions = transformTableColumnDefs( $sourceTableDefinitions, $sourceDialect, $targetDialect );
                
                log_dbBridge( "Creating Table: $table_name" . PHP_EOL, debugFlags::DEBUG_ALWAYS );
                createTable( $this->pdo, $table_name , $sourceImportDefinitions, $this->driverName );

                log_dbBridge( "Importing data for table: $table_name" . PHP_EOL, debugFlags::DEBUG_ALWAYS );
                importTable( $dbSource->pdo, $this->pdo, $table_name, $sourceImportDefinitions, $this->getDialect() );

                log_dbBridge( "Saving dbBridge_schema for table: $table_name" . PHP_EOL, debugFlags::DEBUG_ALWAYS );                
                tableSave_dbBridgeSchema( $this->pdo, $table_name, $sourceImportDefinitions );
            }

            log_dbBridge( "Data imported successfuly." . PHP_EOL, debugFlags::DEBUG_ALWAYS );
        }
        catch( Exception $e )
        {
            throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
        }
    }
}

/**
 * CLASS dbDriver( PDP $pdo, string $Workarea )
 * 
 * Implements the parent class for all database drivers.
 * 
 * Arguments:
 * - $pdo: A PDO object.
 * - $Workarea: The name of the Workarea (database) to use.
 * 
 * @property PDO $pdo
 * @property string $Workarea
 * @property array $tableNames
 * @property array $columnDefinitions
 * 
 */
abstract class dbDriver 
{
    /* 
       Protected methods and properties are not accessible outside of the class chain
    */
    protected PDO $pdo;
    protected string $Workarea;
    protected array $tableNames;
    protected array $columnDefinitions;

    // Abstract methods must be implemented in the child class.

    abstract protected function fetchWorkarea() : string;
    abstract protected function fetchTableNames() : array;
    abstract protected function fetchTableColumns( string $table_name ) : array;

    /*
       Constructor is a special case of a method that is called when the object is created
       in current PHP it must be public and it's name is always __construct.
    */ 
    public function __construct( PDO $pdo, string $Workarea )
    {
        $this->pdo = $pdo;
        $this->Workarea = $Workarea;
        $this->tableNames = [];
        $this->columnDefinitions = [];
    }

    // Internal validation method.
    protected function getColumnsValidateTable( string $table_name ) : void
    {
        // Make sure the tables are loaded - will not reload if already loaded.
        $this->getTableNames();
        
        if( ! in_array( $table_name, $this->tableNames ) )
        {
            throw new dbBridgeException( "Table '$table_name' does not exist in Workarea: '{$this->Workarea}'." );
        }

        $this->columnDefinitions = [];
        $this->columnDefinitions[ $table_name ] = $this->fetchTableColumns( $table_name );
    }
    
    // Public methods are accessible outside of the class

    // Child classes do not need to implement this method.
    public function getColumns( string $table_name ) : array
    {
        if( empty( $this->columnDefinitions[ $table_name ] ) )
        {
            $this->getColumnsValidateTable( $table_name );
        }

        return $this->columnDefinitions[ $table_name ];
    }

    // Child classes do not need to implement this method.
    public function getTableNames() : array
    {
        if( empty( $this->tableNames ) )
        {
            $this->tableNames = $this->fetchTableNames();
        }

        return $this->tableNames;
    }

    // Child classes do not need to implement this method.
    public function getWorkarea() : string
    {
        if ( ! isset( $this->Workarea ) )
        {
            $this->Workarea = $this->fetchWorkarea();
        }

        return $this->Workarea;
    }
}

/**
 * FUNCTION: importTable( PDO $pdoSource, PDO $pdoTarget, string $table, array $tableDefinitions, string $dialect )
 * 
 * Imports a table from source database to target database.
 * 
 * Here's how it works:
 * 
 * 1. getTableColums() retrieves the column definitions for the source table.    
 *   This is done by calling the dialects's native:
 *
 *       fetchTableColumnDefinitions()
 *
 *  The result is an array of column definitions for the source table.
 *  including [ 'column_name' ], [ 'data_type' ], [ 'is_nullable' ] //TODO: , [ 'column_default' ]
 *  it may also include additional dialect specific column definition tags. 
 *  like 'to_utc_query' (implemented in all the included dbDrivers). 
 *       
 * 2. transformTableColumnDefs( array $columnDefs, string $sourceDialect, string $targetDialect ): array
 *   It first utilizes:
 *  
 *       {source-dialect}TypeTo_stdType( string $sourceType )
 *  
 *   to convert the native source dialect specific 'data_type' to its respective stdType.
 *
 *   Then it calls:
 *  
 *       stdTypeTo_{target-dialect}( string $stdType ) 
 *       
 *   to convert the stdType to the target dialect best matching 'data_type'. 
 *   This results in an EXTENDED tableColumnDefinitions including 
 *   additional [ 'original_type' ] as well as a deduced [ 'pdo_type' ]. 
 *   
 *   This funtion also check for '(n)' notation on the stdType 
 *   (for cases like a decision to convert MsSql's 'uniqueidentifier' 
 *   to CHARBINARY(16)) - if found, it extracts such length and store
 *   it (overriding prior length if any) in the ['character_maximum_length']
 *   of the new taget based extended column definition.
 *
 *   This is the core of the type mapping plan.*
 *
 * 3. compileCreateTableQuery()
 *
 *   Uses info from the extended target definitions we gathered in transformTableColumnDefs() to compile 
 *   an appropriate:
 *  
 *       'CRETAE TABLE ...' 
 *
 *   statement for the specific dialect Server. It allows for 
 *   dialect specific customization of the table creation statement.
 *
 *   TODO: It also allows for the support of dialect specific attributes
 *   (e.g. MySql's 'AUTO_INCREMENT' or MsSql's 'IDENTITY(1,1)')
 *
 *   TODO: Move this to the dialect specific dbDriver class.
 *
 * 4. compileSelectQuery() 
 *
 *   Uses all the info gathered as per above to compile the proper:
 *   
 *       'SELECT <Value> AS <NAME>...'
 *   
 *   to generate the NAMED source values in the form we want/need.
 *
 * 5. compileInsertQuery()
 *
 *   Finally here we compile the:
 *
 *       'INSERT INTO <table> ( <columns>) VALUES <placeholders>
 *       
 *   so that values retrieved from the Source-Table by #3 will be saved 
 *   correctly to the Target-Table. This is where we use the extended
 *   column definitions to determine the correct PDO::PARAM_* type
 *   for each column. As as utilize the already dialect specific
 *   'to_utc_query' to convert the value to UTC. 
 *
 * @param PDO $pdoSource
 * @param PDO $pdoTarget
 * @param string $table
 * @param array $tableDefinitions
 * @param string $dialect
 * @return void
 */
function importTable( PDO $pdoSource, PDO $pdoTarget, string $table, array $tableDefinitions, string $dialect )
{
    log_dbBridge( "Importing data for table: '$table'" . PHP_EOL, debugFlags::DEBUG_ALWAYS );

    // Prepare the insert statement
    $insertQuery = compileInsertQuery( $table, $tableDefinitions, $dialect );
    log_dbBridge( "Insert Query: '$insertQuery'" . PHP_EOL, debugFlags::DEBUG_QUERY_INSERT );
    $insertStatement = $pdoTarget->prepare( $insertQuery );

    // Prepare and execute the select query
    $selectQuery = compileSelectQuery( $pdoSource, $table, $tableDefinitions );
    log_dbBridge( "Select Query: " . $selectQuery->queryString . PHP_EOL, debugFlags::DEBUG_QUERY_SELECT );
    $selectQuery->execute();
    
    // Loop through the data and insert it into the target database
    $nRecNo = 1;
    while( $row = $selectQuery->fetch( PDO::FETCH_ASSOC ) ) 
    {
        if ($nRecNo % 1000 == 0) 
        {
            log_dbBridge( "\rRow: $nRecNo", debugFlags::DEBUG_IMPORT_ROW );
            
            log_dbBridge( "Running garbage collector..." . PHP_EOL, debugFlags::DEBUG_GC );
            gc_collect_cycles();

            // Yield to the OS
            usleep( 10000 );
        }

        // importRow() will YIELD and report/log the row number!
        importRow( $insertStatement, $row, $tableDefinitions, $table, $nRecNo );
        $nRecNo++;
    }

    // Cleanup after "\r"
    log_dbBridge( PHP_EOL, debugFlags::DEBUG_IMPORT_ROW );
}

/**
 * FUNCTION importRow( PDOStatement $stmt, array $row, array $tableDefinitions, string $table ) : void
 * 
 * Imports a single table's row into the database.
 * 
 * @param PDOStatement $stmt
 * @param array $row
 * @param array $tableDefinitions
 * @param string $table
 * @return void
 */
function importRow( PDOStatement $stmt, $row, $tableDefinitions, $table ) 
{
    try 
    {
        foreach( $row as $column => $value ) 
        {
            $dataType = $tableDefinitions[ $column ][ 'data_type' ];
            $originalType = $tableDefinitions[ $column ][ 'original_type' ];
            $toUtcQuery = $tableDefinitions[ $column ][ 'to_utc_query' ] ?? null;

            $pdoType = PDO::PARAM_STR;

            if( strpos( $dataType, 'char') !== false || strpos( $dataType, 'text' ) !== false ) 
            {
                if( ! empty($value ) )
                {
                   // Sanitize string value
                   $value = sanitizeString($value);
                }
            } 
            elseif( strpos( $dataType, 'time' ) !== false ) 
            {
                // Set the PDO data type
                $pdoType = PDO::PARAM_STR;

                if( $value === null || $value === 'NULL' || $value === '' ) 
                {
                    $value = 'NULL';
                    $pdoType = PDO::PARAM_NULL;
                } 
                else 
                {
                    // Format datetime value
                    $datetime = new DateTime($value);
                    // Convert the DateTime to UTC if a conversion query is provided
                    if ($toUtcQuery !== null) {
                        $datetime->setTimezone(new DateTimeZone('UTC'));
                    }
                    $value = $datetime->format('Y-m-d H:i:s' );
                }
            }
            // Special case MUST use the original type!
            elseif( strpos( $originalType, 'uniqueidentifier' ) !== false ) 
            {
                if ($value === null) 
                {
                    // Handle NULL value for uniqueidentifier
                    $value = 'NULL';
                    $pdoType = PDO::PARAM_NULL;
                } else 
                {
                    // Replace '-' with '' and then hex to bin in PHP
                    $value = hex2bin(str_replace('-', '', $value));                            
                }
                $pdoType = PDO::PARAM_LOB;
            } 
            elseif( strpos( $dataType, 'bit' ) !== false )
            {
                if( $value === '' || $value === null )
                {
                    $value = 'NULL';
                    $pdoType = PDO::PARAM_NULL;
                } 
                else
                {
                    $value = (int)(bool)$value;
                }
                $pdoType = PDO::PARAM_INT;
            } 
            elseif( strpos( $dataType, 'money' ) !== false )//|| strpos( $dataType, 'smallmoney') !== false ) 
            {
                // Remove currency symbol and stringify
                $value = str_replace( '$', '', $value );
            }                        
            elseif( strpos( $dataType, 'numeric' ) !== false || strpos( $dataType, 'decimal' ) !== false )
            {
                // Remove currency symbol and handle empty string
                if ($value === '' || $value === null ) 
                {
                    $value = 'NULL';
                    $pdoType = PDO::PARAM_NULL;
                }
                else 
                {
                    $value = str_replace( '$', '', $value );
                }
            }
            elseif( strpos( $dataType, 'int') !== false ) 
            {
                // handle empty string
                if ($value === '' || $value === null) 
                {
                    $value = '0';
                }
            } 
            elseif( strpos( $dataType, 'image') !== false || strpos( $dataType, 'binary') !== false ) //|| strpos( $dataType, 'varbinary') !== false ) 
            {
                // Convert image value to binary and stringify
                $value = $pdoTarget->quote($value);
            } 
            else 
            {
                // Handle NULL values for other types
                if( $value === null ) 
                {
                    $value = 'NULL';
                    $pdoType = PDO::PARAM_NULL;
                }
            }
                                                                                    
            // Bind each parameter
            $stmt->bindValue( ":" . $column, $value, $pdoType); 
            log_dbBridge( "Bound Column: $column, Type: $pdoType, Value: $value", debugFlags::DEBUG_BIND );
        }

        //echo "Values: [" . implode(', ', $values) . "]\n";

        $stmt->execute();        
    }
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION compileSelectQuery( PDO $pdoSource, string $table, array $columns ) : PDOStatement
 * 
 * Compiles a SELECT query for the given table and columns.
 * 
 * @param PDO $pdoSource
 * @param string $table
 * @param array $columns
 * @return PDOStatement
 */
function compileSelectQuery( PDO $pdoSource, string $table, array $columns ) : PDOStatement
{
    // Prepare a list of column names or to_utc_query values for the SELECT query
    $selectColumns = [];
    foreach( $columns as $columnName => $columnData ) 
    {
        if( isset( $columnData[ 'to_utc_query' ] ) && $columnData[ 'to_utc_query' ] ) 
        {
            $selectColumns[] = $columnData[ 'to_utc_query' ];
        } 
        else 
        {
            if( isset( $columnData[ 'source_name' ] ) )
            {
                $columnName = $columnData[ 'source_name' ];
            }

            $selectColumns[] = $columnName;
        }
    }

    $selectColumnsStr = implode( ', ', $selectColumns );
    $query = $pdoSource->prepare("SELECT $selectColumnsStr FROM $table");

    return $query;
}

/**
 * FUNCTION compileInsertQuery( string $table, array $columns, string $dialect ) : string
 * 
 * Compiles an INSERT query for the given table and columns.
 * 
 * @param string $table
 * @param array $columns
 * @param string $dialect
 * @return string
 */
function compileInsertQuery( string $table, array $columns, string $dialect ) : string
{
    try
    {
        // The Columns
        $strColumns = implode(', ', array_keys( $columns ) );

        // Prepare the placeholders
        $placeholders = [];
        foreach( $columns as $column => $columnData ) 
        {
            // Store the placeholder
            $placeholder = ":" . $column;

            // Modify placeholder for datetime columns with a timezone-aware datatype and a to_utc_query
            if( isset( $columnData[ 'to_utc_query' ] ) && in_array($columnData[ 'data_type' ], [ 'timestamptz', 'datetimeoffset' ] ) ) 
            {
                // Adjust syntax according to dialect
                switch($dialect) {
                    case 'pgsql':
                        $placeholder = "TIMESTAMP :$column AT TIME ZONE 'UTC'";
                        break;
                    case 'sqlsrv':
                        $placeholder = "CAST(:$column AS DATETIMEOFFSET) AT TIME ZONE 'UTC'";
                        break;
                    case 'mysql':
                        $placeholder = "CONVERT_TZ(:$column, '+00:00', @@global.time_zone)";
                        break;
                    default:
                        throw new Exception("Unsupported dialect: $dialect");
                }
            }

            $placeholders[] = $placeholder;
        }

        $strPlaceholders = implode( ', ', $placeholders );

        return "INSERT INTO $table ($strColumns) VALUES ($strPlaceholders)";
    }
    catch( Exception $e )
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION importDb( PDO $pdoSource, PDO $pdoTarget, array $tableDefinitions, string $dialect )
 * 
 * Imports the database from the source to the target.
 * by looping through the tables and importing each one.
 * Using the importRow() function.
 * 
 * @param PDO $pdoSource
 * @param PDO $pdoTarget
 * @param array $tableDefinitions
 * @param string $dialect
 * @return void
 */
function importDb( PDO $pdoSource, PDO $pdoTarget, $tableDefinitions, string $dialect ) 
{    
    try 
    { 
        // Loop through the tables
        foreach( $tableDefinitions as $table => $columns ) 
        {
            // Skip sys tables
            if( substr( $table, 0, 3 ) == 'sys' ) continue;

            importTable( $pdoSource, $pdoTarget, $table, $columns, $dialect );
            log_dbBridge( "Running garbage collector...", debugFlags::DEBUG_GC );
            gc_collect_cycles();
        }
    } 
    catch( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }
}

/**
 * FUNCTION tableCreate_dbBridgeSchema( PDO $pdo ) : bool
 * 
 * Creates the dbBridge_schema table.
 * 
 * @param PDO $pdo
 * @return bool
 */
function tableCreate_dbBridgeSchema( PDO $pdo) : bool
{
    $query = $pdo->prepare(
        "CREATE TABLE dbBridge_schema (
            table_name VARCHAR(255) NOT NULL,
            column_name VARCHAR(255) NOT NULL,
            data_type VARCHAR(255),
            character_maximum_length INT,
            numeric_precision INT,
            numeric_scale INT,
            is_nullable BOOLEAN,
            original_type VARCHAR(255),
            to_utc_query TEXT,
            PRIMARY KEY (table_name, column_name)
        )"
    );

    $result = $query->execute();
    if( $result === false ) 
    {
        throw new dbBridgeException( "Failed to create schema table: " . $query->errorInfo()[2] );
    }

    return true;
}

/**
 * FUNCTION tableSave_dbBridgeSchema( PDO $pdo string $tableName, array $tableColumns ) : void
 * 
 * Saves the table schema to the dbBridge_schema table.
 * 
 * @param PDO $pdo
 * @param string $tableName
 * @param array $tableColumns
 */
function tableSave_dbBridgeSchema( PDO $pdo, string $tableName, array $tableColumns )
{        
    foreach( $tableColumns as $column )
    {
        $query = $pdo->prepare("
            INSERT INTO dbBridge_schema (table_name, column_name, data_type, character_maximum_length, 
                                         numeric_precision, numeric_scale, is_nullable, original_type, to_utc_query )
            VALUES ( :table_name, :column_name, :data_type, :character_maximum_length, 
                    :numeric_precision, :numeric_scale, :is_nullable, :original_type, :to_utc_query )
        ");

        $result = $query->execute( [
            ':table_name' => $tableName,
            ':column_name' => $column[ 'column_name' ],
            ':data_type' => $column[ 'data_type' ],
            ':character_maximum_length' => $column[ 'character_maximum_length' ],
            ':numeric_precision' => $column[ 'numeric_precision' ],
            ':numeric_scale' => $column[ 'numeric_scale' ],
            ':is_nullable' => $column[ 'is_nullable' ] ? 1 : 0,
            ':original_type' => $column[ 'original_type' ] ?? null,
            ':to_utc_query' => $column[ 'to_utc_query' ] ?? null
        ] );

        if( $result === false )
        {
            throw new dbBridgeException("Failed to save schema data: " . $query->errorInfo()[2]);
        }
    }

    return true;
}

function tableLoad_dbBridgeSchema( PDO $pdo, string $tableName ) : array
{
    $query = $pdo->prepare(
        "SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, 
            is_nullable, to_utc_query
        FROM dbBridge_schema
        WHERE table_name = :table_name"
    );

    $result = $query->execute( [':table_name' => $tableName ] );
    if( $result === false ) 
    {
        throw new dbBridgeException( "Failed to retrieve schema information: " . $query->errorInfo()[2] );
    }

    $columns = $query->fetchAll( PDO::FETCH_ASSOC );
    if( $columns === false ) 
    {
        throw new dbBridgeException( "Failed to fetch schema information: " . $query->errorInfo()[2] );
    }

    return $columns;
}

/**
 * FUNCTION test_dbBridge() : int
 * 
 * Tests the dbBridge Migration functionality.
 * 
 * @return int
 */
function test_dbBridge() : int  
{
    try 
    {        
        // Enable error reporting
        error_reporting( E_ALL );

        // Enable error display
        ini_set( 'display_errors', '1' );

        // Set the custom error handler and interrupt handler
        set_error_handler( 'dbBridge\\error2Exception' );
        pcntl_signal( SIGINT, 'dbBridge\\handleInterrupt' );

        // Clear the log files
        file_put_contents( 'dbBridge.log', '' );
        file_put_contents( 'dbb.error.log', '' );
        
        LoadEnvFile( 'db_source.env' );

        $pdoMsSql = openMsSqlDb( $_ENV['DB_DSN'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'] );
        $dbMsSql = new dbAbstractor( $pdoMsSql, $_ENV['DB_NAME'] );
        
        // Will override existing values - so source and target can use same names!
        LoadEnvFile( 'db_target.env' );

        $pdoMySql = openMySqlDb( $_ENV[ 'DB_HOST' ], $_ENV[ 'DB_NAME' ] , $_ENV[ 'DB_PORT' ], $_ENV[ 'DB_USER' ], $_ENV[ 'DB_PASS' ] );
        $dbMySql = new dbAbstractor( $pdoMySql, $_ENV['DB_NAME'] );

        /*
        // Levels of debug information MASKS
        const DEBUG_TRANSFORM_RESERVED    = 4;
        const DEBUG_TRANSFORM_SOURCE      = 8;
        const DEBUG_TRANSFORM_TARGET      = 16;
        const DEBUG_TRANSFORM_TRANSFORMED = 32;
        // Mask for all levels of Transform debug information
        const DEBUG_TRANSFORM_ALL         = 60;   

        const DEBUG_QUERY_CREATE          = 64;    
        const DEBUG_QUERY_SELECT          = 128;
        const DEBUG_QUERY_INSERT          = 256;
        const DEBUG_QUERY_ALL             = 448;

        const DEBUG_OVERWRITE             = 512; 
        const DEBUG_BIND                  = 1024;
        const DEBUG_EXECUTE               = 2048;
        const DEBUG_FETCH                 = 4096;
        const DEBUG_FIXME                 = 8192;
        const DEBUG_IMPORT_ROW            = 16384;
        const DEBUG_GC                    = 32768;
    
        // Mask for all levels of debug information
        const DEBUG_ALL                  = 65535;
        */

        // Default to required levels of debug trace to dbBridge.log and/or show to terminal.
        debugFlags::setDebugLogFlags( debugFlags::DEBUG_IMPORT_ROW | debugFlags::DEBUG_QUERY_ALL  );
        debugFlags::setDebugShowFlags( debugFlags::DEBUG_IMPORT_ROW );

        // Perform the migration process...
        $dbMySql->importDb( $dbMsSql );
    }
    catch ( Exception $e ) 
    {
        throw new dbBridgeException( __METHOD__ . ' Failed!' , 0, $e );
    }

    return 0;
}

function main() : int
{
    $exitCode = test_dbBridge();

    return $exitCode;
}

return main();
?>