# dbBridge

![Version](https://img.shields.io/badge/version-0.8.0-brightgreen.svg?style=flat-square)
![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)

dbBridge is an educational proof-of-concept PHP library that serves as an abstraction bridge between multiple SQL dialects using PDO (native and ODBC) drivers. It enables importing a database from a source to a target with just one user class and three lines of code. 

## Prerequisites

- PHP >= 8.0

## Installation

### Using Composer

If you are using Composer, you can add `dbBridge` to your project by running:

    composer require yourusername/dbbridge


### Manual Installation

If you prefer not to use Composer, you can download the library and include it in your project manually.

   ```php
   require_once 'path-to-dbBridge/dbAbstractor.php';
   ```

## Usage
   The dbAbstractor class serves as the core of the dbBridge library, facilitating the transfer of 
   database data between different SQL dialects.

   ```php
   require_once 'vendor/autoload.php';

   use dbBridge\dbAbstractor;

   // Example of setting PDO connection for source database
   $pdoMsSql = new PDO("dblib:host=your_hostname;dbname=your_db;charset=utf8", "your_username", "your_password");

   // Example of setting PDO connection for target database
   $pdoMySql = new PDO("mysql:host=your_hostname;dbname=your_db;charset=utf8", "your_username", "your_password");

   // Create dbBridge wrapper instances for the source and target databases
   $dbSource = new dbAbstractor($pdoMsSql, 'YourDB-Name');
   $dbTarget = new dbAbstractor($pdoMySql);

   // Import the database
   $dbTarget->importDb($dbSource);
   ```

## Known Limitations

This library is an educational proof of concept prototype, and has the following known limitations:

No support for Stored Procedures.
No support for Triggers.
No support for Indexes.
No support for Constraints.
No support for Sequences.
No support for Foreign Keys.
No support for Views.
No support for Functions.
No support for User-defined Types.
No support for User-defined Aggregates.

## Contributing

Contributions are welcome! Please feel free to submit a pull request or create issues for anything you think could be improved.

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Contact

Author: Ron[ny] Pinkas
Email: ron@ronpinkas.com
Website: https://www.ronpinkas.com

## Acknowledgments

- Thanks to OpenAI's GPT model for assistance with PHP documentation and code samples, 
  as well as advice and assistance in generating documentation reading the project for
  distribution with Compser.

A big thank you to the community for your support!

## The log_dbBridge function

The log_dbBridge function in the dbBridge library is responsible for logging and 
displaying progress and log messages during the database import process. It takes two 
arguments, a message string and a context flag which determines the type of message.

Here's an example of how the log_dbBridge function is typically being used:
   
   ```php
   log_dbBridge("This is a transformation message", debugFlags::DEBUG_TRANSFORM_ALL );
   ```

In this example, the message "This is a transformation message" is associated with the 
DEBUG_TRANSFORM_ALL flag. Whether or not this message gets logged or displayed depends
on the bitmask settings in debugFlags::$debugLogFlags and debugFlags::$debugShowFlags.

You can control the bitmask settings like this:

   ```php
   // To enable logging of transformation messages
   debugFlags::$debugLogFlags |= debugFlags::DEBUG_TRANSFORM_ALL;

   // To enable displaying of transformation messages
   debugFlags::$debugShowFlags |= debugFlags::DEBUG_TRANSFORM_ALL;

   // To disable logging of transformation messages
   debugFlags::$debugLogFlags &= ~debugFlags::DEBUG_TRANSFORM_ALL;

   // To disable displaying of transformation messages
   debugFlags::$debugShowFlags &= ~debugFlags::DEBUG_TRANSFORM_ALL;
   ```

This provides you with the flexibility to control which messages are logged or displayed based on their context.

## Debug Flags

The debugFlags class contains constants that can be used to control the behavior of the log_dbBridge() function.
These constants can be used as a bitmask to specify the debug level for logging and displaying messages by means 
of the class' ::$debugLogFlags and ::$debugShowFlags static properties which can be set using the 
::setDebugLogFlags() and ::setDebugShowFlags() static methods. 

Here is a description of each flag:

DEBUG_ALWAYS (1): Indicates that the message should always be logged and displayed, regardless of the mask
level specified by debugFlags::$debugLogFlags and debugFlags::$debugShowFlags.

DEBUG_TRANSFORM_RESERVED (4): Indicates that a warning about the usage of a reserved name as a column name,
which results in a '_' prefix being added should be logged or displayed.

DEBUG_TRANSFORM_SOURCE (8): Indicates that the source data in a transformation should be logged or displayed.

DEBUG_TRANSFORM_TARGET (16): Indicates that the target data in a transformation should be logged or displayed.

DEBUG_TRANSFORM_TRANSFORMED (32): Indicates that the transformed data should be logged or displayed.

DEBUG_TRANSFORM_ALL (60): A combination of all transformation-related flags (DEBUG_TRANSFORM_RESERVED,
DEBUG_TRANSFORM_SOURCE, DEBUG_TRANSFORM_TARGET, DEBUG_TRANSFORM_TRANSFORMED). Use this flag to log or
display any transformation-related messages.

DEBUG_QUERY_CREATE (64): Indicates that messages related to query creation should be logged or displayed.

DEBUG_QUERY_SELECT (128): Indicates that messages related to SELECT queries should be logged or displayed.

DEBUG_QUERY_INSERT (256): Indicates that messages related to INSERT queries should be logged or displayed.

DEBUG_QUERY_ALL (448): A combination of all query-related flags (DEBUG_QUERY_CREATE, DEBUG_QUERY_SELECT, 
DEBUG_QUERY_INSERT). Use this flag to log or display any query-related messages.

DEBUG_OVERWRITE (512): Indicates that messages related to data overwrites should be logged or displayed.

DEBUG_BIND (1024): Indicates that messages related to data binding in queries should be logged or displayed.

DEBUG_EXECUTE (2048): Indicates that messages related to query execution should be logged or displayed.

DEBUG_FETCH (4096): Indicates that messages related to data fetching should be logged or displayed.

DEBUG_FIXME (8192): Indicates that messages related to items marked for fixing or review should be logged
or displayed.

DEBUG_IMPORT_ROW (16384): Indicates that messages related to data row imports should be logged or displayed.

DEBUG_GC (32768): Indicates that messages related to garbage collection should be logged or displayed.

You can combine these flags by using the bitwise OR operator to specify messages at multiple levels.

## The importDb function

The importDb function is responsible for importing a database from a given source.

### Parameters
dbAbstractor $dbSource: The source database abstractor.

### Process
1. Retrieve column definitions: getTableColums() retrieves the column definitions for the source table by
calling the native function fetchTableColumnDefinitions(). The result is an array of column definitions
including column_name, data_type, and is_nullable. Additional dialect-specific column definition tags
may be included.

2. Transform column definitions: transformTableColumnDefs() is used to convert the native source 
dialect-specific data_type to its respective standard type. This is done by calling
{source-dialect}TypeTo_stdType(). It then converts the standard type to the target dialect's data_type
by calling stdTypeTo_{target-dialect}(). This results in an extended table column definitions including
an additional original_type as well as a deduced pdo_type.

3. Compile create table query: compileCreateTableQuery() uses the information gathered from the extended
target definitions in transformTableColumnDefs() to compile an appropriate CREATE TABLE statement for the
specific dialect server. It allows for dialect-specific customization of the table creation statement.

4. Compile select query: compileSelectQuery() compiles a SELECT statement to generate the named source values
in the desired format.

5. Compile insert query: compileInsertQuery() compiles an INSERT INTO statement so that values retrieved
from the source table can be saved correctly to the target table. The extended column definitions are used
to determine the correct PDO parameter type for each column.

## FAQ

### What is the purpose of the dbBridge library?
The dbBridge library is an educational proof-of-concept PHP library that facilitates the transfer of
database data between different SQL dialects using PDO drivers. It is particularly useful for importing
databases from one SQL dialect to another.

### Which SQL dialects are supported by dbBridge?
As of the current version, dbBridge supports SQL dialects that are compatible with PHP's PDO drivers,
including MySQL, MSSQL, Oracle, PostgreSQL, and Sqlite through native and/or ODBC drivers. The library's capabilities might be extended in the future.

### Are there any limitations on the database structures that can be imported using dbBridge?
Yes, the current version of dbBridge has some limitations. It does not support the import of Stored
Procedures, Triggers, Indexes, Constraints, Sequences, Foreign Keys, Views, Functions, User-defined
Types, or User-defined Aggregates. (This is on the TODO list)

### Can I use dbBridge with PHP versions older than 8.0?
dbBridge requires PHP version 8.0 or higher. It is recommended to use the latest stable version of PHP
to ensure compatibility and security.

### Can I use dbBridge without Composer?
Yes, you can manually include the library in your project by downloading it and requiring the
dbAbstractor.php file in your script. However, using Composer is recommended as it simplifies the
installation process.

### How can I contribute to the development of dbBridge?
Contributions to dbBridge are welcome! You can submit a pull request on the repository or create issues
for anything you think could be improved.

### What should I do if I encounter a problem or bug while using dbBridge?
If you encounter a problem or bug, it's recommended to check if the issue is already known. If not, you
can create an issue on the repository describing the problem, the steps to reproduce it, and any error
messages.

### How can I control the logging and display of messages during the database import process?
The library provides the log_dbBridge function along with debug flags to control logging and display of
messages during the import process. You can set the bitmask settings of debugFlags::$debugLogFlags and
debugFlags::$debugShowFlags to control which messages get logged or displayed.

### Is there any support or community around dbBridge?
As an educational proof-of-concept project, dbBridge may not have official support. However, you can
contact the author or participate in discussions and contribute via the repository.

### Can I use dbBridge in a commercial project?
Yes, dbBridge is licensed under the MIT License, which allows for use in both private and commercial
projects as long as the dbBridge's original license is included.