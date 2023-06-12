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
 * This file imlements the stdTypes class for dbBridge. This class defines 
 * the standard types used by dbBridge as an interim representation in converting
 * from one SQL dialect to another.   
 * 
 * @package dbBridge
 * @version 0.8.0 (working version)
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

/**
 * CLASS: stdTypes()
 * 
 * This class defines the standard types used by dbBridge.
 * 
 */ 
class stdTypes 
{
    /**
     * The complete list of standard types.
     * Here we must have an entry for each known type supported by 
     * any of the database dialects of all dbBridge Drivers.
     */
    const NULL = 'STD_TYPE_NULL';
    const BIT = 'STD_TYPE_BIT';                                 // 1 bit
    const TINYINT = 'STD_TYPE_TINYINT';                         // 8 bit
    const SMALLINT = 'STD_TYPE_SMALLINT';                       // 16 bit
    const INT = 'STD_TYPE_INT';                                 // 32 bit
    const BIGINT = 'STD_TYPE_BIGINT';                           // 64 bit 
    const FLOAT = 'STD_TYPE_FLOAT';                             // single precision
    const DOUBLE = 'STD_TYPE_DOUBLE';                           // double precision
    const DECIMAL = 'STD_TYPE_DECIMAL';                         // variable precision
    const SMALLMONEY = 'STD_TYPE_SMALLMONEY';                        // Financial precision
    const MONEY = 'STD_TYPE_MONEY';                             // Financial precision
    const STRING = 'STD_TYPE_STRING';                           // variable length string
    const CHAR = 'STD_TYPE_CHAR';                               // fixed length string
    const LONG_STRING = 'STD_TYPE_LONG_STRING';
    const UNICODE_STRING = 'STD_TYPE_UNICODE_STRING';
    const LONG_UNICODE_STRING = 'STD_TYPE_LONG_UNICODE_STRING';
    const CHARBINARY = 'STD_TYPE_CHARBINARY';                   // fixed length binary
    const BINARY = 'STD_TYPE_BINARY';                           // variable length binary 
    const BLOB = 'STD_TYPE_BLOB';                               // large object
    const DATE = 'STD_TYPE_DATE';
    const TIME = 'STD_TYPE_TIME';
    const TIME_TZ = 'STD_TYPE_TIME_TZ';
    const DATETIME = 'STD_TYPE_DATETIME';
    const DATETIME_TZ = 'STD_TYPE_DATETIME_TZ';
    const TIMESTAMP = 'STD_TYPE_TIMESTAMP';
    const INTERVAL = 'STD_TYPE_INTERVAL';
    const BOOL = 'STD_TYPE_BOOL';
    const JSON = 'STD_TYPE_JSON';
    const GUID = 'STD_TYPE_GUID';
    const AUTO_INCREMENT_TINYINT = 'AUTO_INCREMENT_TINYINT';
    const AUTO_INCREMENT_SMALLINT = 'AUTO_INCREMENT_SMALLINT';
    const AUTO_INCREMENT_MEDIUMINT = 'AUTO_INCREMENT_MEDIUMINT';
    const AUTO_INCREMENT_INT = 'AUTO_INCREMENT_INT';
    const AUTO_INCREMENT_BIGINT = 'AUTO_INCREMENT_BIGINT';
    const UNKNOWN = 'STD_TYPE_UNKNOWN';
    
    public static function getAll(): array
    {
        return [
            self::NULL,
            self::BIT,
            self::TINYINT,
            self::SMALLINT,
            self::INT,
            self::BIGINT,
            self::FLOAT,
            self::DOUBLE,
            self::DECIMAL,
            self::SMALLMONEY,
            self::MONEY,
            self::STRING,
            self::CHAR,
            self::LONG_STRING,
            self::UNICODE_STRING,
            self::LONG_UNICODE_STRING,
            self::CHARBINARY,
            self::BINARY,
            self::BLOB,
            self::DATE,
            self::TIME,
            self::TIME_TZ,
            self::DATETIME,
            self::DATETIME_TZ,
            self::TIMESTAMP,
            self::INTERVAL,
            self::BOOL,
            self::JSON,
            self::GUID,
            self::AUTO_INCREMENT_TINYINT,
            self::AUTO_INCREMENT_SMALLINT,
            self::AUTO_INCREMENT_MEDIUMINT,
            self::AUTO_INCREMENT_INT,
            self::AUTO_INCREMENT_BIGINT,
            self::UNKNOWN
        ];
    }
}
?>
