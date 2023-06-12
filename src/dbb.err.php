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
 * This file contains Error Trapping and Exception Handling's 
 * support functions for dbBridge.
 * 
 * Entry points:
 *  error2exception( int $errno, string $errstr, string $errfile, int $errline ) -> Throws an ErrorException
 *  dbBridgeException( string $message, int $code = 0, Exception $previous = null, string $file = 'unknown-source', int $line = 0 ) : dbBridgeException 
 *  log_dbBridge( string $message, int $debugFlags = debugFlags::LOG_AND_SHOW ) : void
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
USE \SoapFault;
USE \DOMException;
USE \JsonException;
USE \SimpleXMLException;
USE \ErrorException;

/**
 * CLASS: dbBridgeException( string $message, int $code = 0, Exception $previous = null, string $file = 'unknown-source', int $line = 0 )
 * 
 * This class implements the exception class for dbBridge.
 * 
 * Arguments:
 *  $message - the exception message
 *  $code - the exception code
 *  $previous - the previous exception
 *  $file - the file where the TRAPPED Error was created in case of an ErrorException
 *  $line - the line where the TRAPPED Error was created in case of an ErrorException
 * 
 * Returns: dbBridgeException object
 */
class dbBridgeException extends Exception 
{
    public function __construct( string $message = '', int $code = 0, Exception $previous = null, string $file = 'unknown-source', int $line = 0 ) 
    {
        // Get the class of prior exception if any
        if( $previous != null )
        {
            $class = get_class( $previous );
            $messagePrevious = $previous->getMessage();
            $message = "Wrapper of Exception: '$class' Message: $messagePrevious";
            $code = $previous->getCode();

            /* 
               $message and $code will be used in the parent::__construct() below
               NOTE: Can't use $this before calling parent::__construct() below!
            */
        }
                    
    parent::__construct( $message, /*$code*/ 0, $previous );

        /*
           Must check again after calling parent::__construct() 
           because we now need to overirde the file and line of $this
        */
        if( $previous != null ) 
        {
            // Just for correctness the exceptionInfo() will be called with the previous exception!
            $this->file = $previous->getFile();
            $this->line = $previous->getLine();
        }
        else if( $file != 'unknown-source' )
        {
            $this->file = $file;
            $this->line = $line;
        }
        else if( $this->getTrace() != null )
        {
            // get the file and line of the caller (the one who called the constructor)
            if( count( $this->getTrace() ) > 1 )
            {
                $caller = $this->getTrace()[1];

                if( $caller['file'] != null )
                {
                    $this->file = $caller['file'];
                    $this->line = $caller['line'];
                }
            }
        }

        /*
           This should be the ONLY place where we call error_log()
           except log_dbBridge()!
        */   
        error_log( exceptionInfo( $previous ?? $this, bStackTrace : true, bExplode: true, bExceptionDetails : true ), 3, 'dbb.error.log' );
        echo exceptionInfo( $this, bStackTrace : true, bExplode: false, bExceptionDetails : false );
        exit( 1 );
    }
}

/**
 * FUNCTION: handleInterrupt() : void
 * 
 * This function is called when the user presses Ctrl-C.
 * 
 * Arguments: none
 * 
 * Returns: void
 */
function handleInterrupt() : void
{
    $error = error_get_last();
    $errorMessage = $error['message'] ?? '';
    $errorFile = $error['file'] ?? '';
    $errorLine = $error['line'] ?? '';

    throw new dbBridgeException( "Interrupted at line $errorLine in $errorFile: $errorMessage" );
}

/**
 * FUNCTION: error2Exception( int $severity, string $message, string $file, int $line ) : void
 * 
 * Error Handler to trap Warnings & Errors, converts them to an ErrorException and throw them.
 * 
 * Arguments:
 *  $severity - the severity of the error
 *  $message - the error message
 *  $file - the file where the error occured
 *  $line - the line where the error occured
 * 
 * @Entry Point - by calling set_error_handler( 'dbBridge\\error2Exception' )
 * 
 * Returns: void
 */
function error2Exception( int $severity, string $message, string $file, int $line ) : void
{    
    throw new dbBridgeException( 'Trapped Error: ' . $message, $severity, null, $file, $line );
}

/**
 * CLASS: debugFlags
 * 
 * This class defines the debug flags used by dbBridge.
 * 
 * Constants:
 *  NONE - no debug information is shown
 *  LOG - debug information is logged to the file dbBridge.log
 *  SHOW - debug information is shown on the screen
 *  LOG_AND_SHOW - debug information is logged to the file dbBridge.log and shown on the screen * 
 */ 
class debugFlags
{
    // Levels of debug information MASKS
    const DEBUG_ALWAYS               = 1; 

    const DEBUG_TRANSFORM_RESERVED    = 4;
    const DEBUG_TRANSFORM_SOURCE      = 8;
    const DEBUG_TRANSFORM_TARGET      = 16;
    const DEBUG_TRANSFORM_TRANSFORMED = 32;
    
    // Mask for all levels of Transform debug information
    //DEBUG_TRANSFORM_RESERVED | DEBUG_TRANSFORM_SOURCE | DEBUG_TRANSFORM_TARGET | DEBUG_TRANSFORM_TRANSFORMED;   
    const DEBUG_TRANSFORM_ALL         = 60;

    const DEBUG_QUERY_CREATE          = 64;    
    const DEBUG_QUERY_SELECT          = 128;
    const DEBUG_QUERY_INSERT          = 256;
    
    // DEBUG_QUERY_CREATE | DEBUG_QUERY_SELECT | DEBUG_QUERY_INSERT;
    const DEBUG_QUERY_ALL             = 448;

    const DEBUG_OVERWRITE             = 512; 
    const DEBUG_BIND                  = 1024;
    const DEBUG_EXECUTE               = 2048;
    const DEBUG_FETCH                 = 4096;
    const DEBUG_FIXME                 = 8192;
    const DEBUG_IMPORT_ROW            = 16384;
    const DEBUG_GC                    = 32768;
 
    // Mask for all levels of debug information
    //DEBUG_TRANSFORM_ALL | DEBUG_QUERY_ALL | DEBUG_OVERWRITE | DEBUG_BIND | DEBUG_EXECUTE | DEBUG_FETCH | DEBUG_FIXME | DEBUG_IMPORT_ROW | DEBUG_GC;
    const DEBUG_ALL                  = 65407; 
        
    // Default to All levels of trace log and debug information
    static $debugLogFlags  = self::DEBUG_ALL;  
    static $debugShowFlags = self::DEBUG_IMPORT_ROW;  

    static function getDebugFlags() : int
    {
        return self::$debugFlags;
    }

    static function isDebugFlagSet( int $debugFlag ) : bool
    {
        return ( self::$debugFlags & $debugFlag ) != 0;
    }

    static function setDebugFlag( int $debugFlag ) : void
    {
        self::$debugFlags |= $debugFlag;
    }

    static function clearDebugFlag( int $debugFlag ) : void
    {
        self::$debugFlags &= ~$debugFlag;
    }

    static function toggleDebugFlag( int $debugFlag ) : void
    {
        self::$debugFlags ^= $debugFlag;
    }

    static function asString( int $debugFlags ) : string
    {
        static $debugFlagsStrings = [
            self::DEBUG_TRANSFORM_RESERVED    => 'DEBUG_TRANSFORM_RESERVED',
            self::DEBUG_TRANSFORM_SOURCE      => 'DEBUG_TRANSFORM_SOURCE',
            self::DEBUG_TRANSFORM_TARGET      => 'DEBUG_TRANSFORM_TARGET',
            self::DEBUG_TRANSFORM_TRANSFORMED => 'DEBUG_TRANSFORM_TRANSFORMED',
            self::DEBUG_TRANSFORM_ALL         => 'DEBUG_TRANSFORM_ALL',
            self::DEBUG_QUERY_CREATE          => 'DEBUG_QUERY_CREATE',
            self::DEBUG_QUERY_SELECT          => 'DEBUG_QUERY_SELECT',
            self::DEBUG_QUERY_INSERT          => 'DEBUG_QUERY_INSERT',
            self::DEBUG_QUERY_ALL             => 'DEBUG_QUERY_ALL',
            self::DEBUG_OVERWRITE             => 'DEBUG_OVERWRITE',
            self::DEBUG_BIND                  => 'DEBUG_BIND',
            self::DEBUG_EXECUTE               => 'DEBUG_EXECUTE',
            self::DEBUG_FETCH                 => 'DEBUG_FETCH',
            self::DEBUG_FIXME                 => 'DEBUG_FIXME',
            self::DEBUG_IMPORT_ROW            => 'DEBUG_IMPORT_RECORD',
            self::DEBUG_ALL                   => 'DEBUG_ALL',
            self::DEBUG_ALWAYS                => 'DEBUG_ALWAYS'
        ];

        return $debugFlagsStrings[$debugFlags] ?? 'UNKNOWN';
    }

    static function setDebugLogFlags( int $debugLogFlags ) : void
    {
        self::$debugLogFlags = self::DEBUG_ALWAYS | $debugLogFlags;
    }

    static function setDebugShowFlags( int $debugShowFlags ) : void
    {
        self::$debugShowFlags = self::DEBUG_ALWAYS | $debugShowFlags;
    }
}

/**
 * FUNCTION: exceptionInfo( Exception $e, bool $bStackTrace = false, bool $bExplode = false, bool $bExceptionDetails = false ) : string
 * 
 * Returns a string with the exception information.
 * 
 * Arguments:
 *  $e - the exception object
 *  $bStackTrace - if true, the stack trace is included.
 *  $bExplode - if true, the Exception object is exploded using print_r() and the result is included.
 *  $bExceptionDetails - if true, the exception details are included.
 * 
 * Returns:
 *  a string with the exception information
 */
function exceptionInfo( Exception $e, bool $bStackTrace = false, bool $bExplode = false, bool $bExceptionDetails = false ) : string
{
    $trace = debug_backtrace();

    if( isset( $trace[ 0 ] ) )
    {
        if( $trace[ 0 ][ 'function' ] == __FUNCTION__ )
        {
            $trace = array_slice( $trace, 1 );
        }

        $traceLine = $trace[ 0 ];
        
        if( isset( $traceLine[ 'class' ] ) )
        { 
            // check if the scope operator is present
            if( isset( $traceLine[ 'type' ] ) )
            {
                $class = $traceLine[ 'class' ] . $traceLine[ 'type' ];
            }
            else
            {
                $class = $traceLine[ 'class' ] . '->';
            } 
        }
        else
        {
            $class = '';
        }
    }
    else
    {
        $traceLine = [];
        $class = '';
    }

    $functionOrMethodName = $traceLine['function'] ?? 'unknown_function_or_method';
    $sourceFileName = $traceLine[ 'file' ] ?? 'unknown_source_file';
    $sourceLineNo = $traceLine[ 'line' ] ?? 0;

    $throwerDescription = "Thrower: " . $e->getFile() . '(' . $e->getLine() . ')';
    $catcherDescription = "Catcher: $sourceFileName($sourceLineNo): $class$functionOrMethodName()";

    if( $bExplode )
    {   $explodePrefix      = $throwerDescription . PHP_EOL;
        $explodePrefix     .= $catcherDescription . PHP_EOL . PHP_EOL;
        $explodePrefix     .= 'Exception: ' . get_class( $e ) . PHP_EOL;

        $explodedException  = print_r( $e, true );
    }
    else
    {
        $explodePrefix = '';
        $explodedException = '';
    }

    if( $bStackTrace)
    {
        $stackTrace  = $throwerDescription . PHP_EOL;
        $stackTrace .= $catcherDescription . PHP_EOL. PHP_EOL;
        $stackTrace .= 'Stack Trace:'      . PHP_EOL;
        $stackTrace .= $e->getTraceAsString();
    }
    else
    {
        $stackTrace = '';
    }

    // Exception details
    $exceptionDetails = "";
    if( $bExceptionDetails ) 
    {
        $exceptionDetails = "Exception: " . get_class( $e );

        if($e instanceof PDOException) 
        {
            $exceptionDetails .= PHP_EOL;
            $exceptionDetails .= "   Error Info: " . implode( ", ", $e->errorInfo );
        }
        elseif($e instanceof ErrorException) 
        {
            $exceptionDetails .= PHP_EOL;
            $exceptionDetails .= "   Severity: " . $e->getSeverity();
        }
        elseif($e instanceof SoapFault) 
        {
            $exceptionDetails .= PHP_EOL;
            $exceptionDetails .= "   Fault Code: " . $e->faultcode . PHP_EOL;
            $exceptionDetails .= "   Fault String: " . $e->faultstring . PHP_EOL;
            $exceptionDetails .= "   Fault Actor: " . $e->faultactor . PHP_EOL;
            $exceptionDetails .= "   Detail: " . $e->detail . PHP_EOL;
        }
    }

    return PHP_EOL . PHP_EOL . 
           $explodePrefix .
           'Error Code: ' . $e->getCode() . PHP_EOL . 
           'Message: ' . $e->getMessage() . PHP_EOL .
           $exceptionDetails              . PHP_EOL .
           $explodedException             . PHP_EOL .
           $stackTrace . PHP_EOL . PHP_EOL;
}

/**
 * FUNCTION: debugShow( string $message, int $debugFlags = debugFlags::DEBUG_ALWAYS ) : void
 * 
 * Shows a message on the screen if the debug level is masked ON.
 * 
 * Arguments:
 *  $message - the message to show
 *  $debugFlags - the debug flags mask level required to log/show the message
 * 
 * Returns: void
 */
function debugShow( string $message, int $debugFlags = debugFlags::DEBUG_ALWAYS ) : void
{
    if( ( $debugFlags & debugFlags::DEBUG_ALWAYS ) || ( debugFlags & debugFlags::$debugShowFlags ) )
    {
        echo $message;
    }
}

/**
 * FUNCTION: log_dbBridge( string $message, int $debugFlags = debugFlags::DEBUG_ALL ) : void
 * 
 * Logs a message to the file dbBridge.log and/or shows it on the screen.
 * 
 * Arguments:
 *  $message - the message to log/show
 *  $debugFlags - the debug flags mask level required to log/show the message
 * 
 * Returns: void
 */
function log_dbBridge( string $message, int $debugFlags = debugFlags::DEBUG_ALWAYS ) : void
{
    // If set
    if( $debugFlags & debugFlags::$debugLogFlags )
    {
        /*
          This should be the only place where we use error_log() in this library
          except for dbBridgeException::__construct()
        */
        error_log( $message, 3, 'dbBridge.log' );        
    }

    //Performance inlining!
    //debugShow( $message, $debugFlags );
    if( ( $debugFlags & debugFlags::DEBUG_ALWAYS ) || ( $debugFlags & debugFlags::$debugShowFlags ) )
    {
        echo $message;
    }    
}
?>
