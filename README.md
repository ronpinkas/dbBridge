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

require_once 'path-to-dbBridge/dbAbstractor.php';

## Usage
require_once 'vendor/autoload.php';

use dbBridge\dbAbstractor;

//Set your source and target PDO connections

// Create dbBridge wrapper instances for the source and target databases
$dbSource = new dbAbstractor($pdoMsSql, 'YourDB-Name');
$dbTarget = new dbAbstractor($pdoMySql);

// Import the database
$dbTarget->importDb($dbSource);

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
