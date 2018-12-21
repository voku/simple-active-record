[![Build Status](https://travis-ci.com/voku/simple-active-record.svg?branch=master)](https://travis-ci.com/voku/simple-active-record)
[![Coverage Status](https://coveralls.io/repos/github/voku/simple-active-record/badge.svg?branch=master)](https://coveralls.io/github/voku/simple-active-record?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/db0b681fd4bf434eaceaa5213698ea3e)](https://www.codacy.com/app/voku/simple-active-record)
[![Latest Stable Version](https://poser.pugx.org/voku/simple-active-record/v/stable)](https://packagist.org/packages/voku/simple-active-record) 
[![Total Downloads](https://poser.pugx.org/voku/simple-active-record/downloads)](https://packagist.org/packages/voku/simple-active-record)
[![License](https://poser.pugx.org/voku/simple-active-record/license)](https://packagist.org/packages/voku/simple-active-record)

:ring: Simple Active Record
===================


This is a simple Active Record Pattern compatible with PHP 7+ that provides a simple 
and _secure_ interaction with your database using mysqli_* functions at 
its core. This is perfect for small scale applications such as cron jobs, 
facebook canvas campaigns or micro frameworks or sites. 


## Get "Simple Active Record"

You can download it from here, or require it using [composer](https://packagist.org/packages/voku/simple-mysqli).
```json
  {
      "require": {
        "voku/simple-active-record": "1.*"
      }
  }
```

## Install via "composer require"
```shell
  composer require voku/simple-active-record
```

* [Starting the driver](#starting-the-driver)
* [Multiton && Singleton](#multiton--singleton)
* [Doctrine/DBAL as parent driver](#doctrinedbal-as-parent-driver)
* [Using the "ActiveRecord"-Class (OOP database-access)](#using-the-activerecord-class-oop-database-access)
    * [setDb(DB $db)](#setdbdb-db)
    * [insert() : boolean|int](#insert--booleanint)
    * [fetch(integer $id = null) : boolean|\ActiveRecord](#fetchinteger--id--null--booleanactiverecord)
    * [fetchAll() : $this[]](#fetchall--this)
    * [update() : boolean|int](#update--booleanint)
    * [delete() : boolean](#update--booleanint)
  * [Active Record | SQL part functions](#active-record--sql-part-functions)
    * [select()](#select)
    * [from()](#from)
    * [join()](#join)
    * [where()](#where)
    * [group()](#group)
    * [order()](#order)
    * [limit()](#limit)
  * [Active Record | WHERE conditions](#active-record--where-conditions)
    * [equal()/eq()](#equaleq)
    * [notEqual()/ne()](#notequalne)
    * [greaterThan()/gt()](#greaterthangt)
    * [lessThan()/lt()](#lessthanlt)
    * [greaterThanOrEqual()/ge()/gte()](#greaterthanorequalgegte)
    * [lessThanOrEqual()/le()/lte()](#lessthanorequallelte)
    * [like()](#like)
    * [in()](#in)
    * [notIn()](#notin)
    * [isNull()](#isnull)
    * [isNotNull()/notNull()](#isnotnullnotnull)
  * [Active Record | Demo](#active-record---demo)
* [Logging and Errors](#logging-and-errors)
* [Changelog](#changelog)


## Starting the driver
```php
  use voku\db\DB;

  require_once 'composer/autoload.php';

  $db = DB::getInstance('yourDbHost', 'yourDbUser', 'yourDbPassword', 'yourDbName');
  
  // example
  // $db = DB::getInstance('localhost', 'root', '', 'test');
```

## Multiton && Singleton

You can use ```DB::getInstance()``` without any parameters and you will get your (as "singleton") first initialized connection. Or you can change the parameter and you will create an new "multiton"-instance which works like an singleton, but you need to use the same parameters again, otherwise (without the same parameter) you will get an new instance. 

## Doctrine/DBAL as parent driver
```php
  use voku\db\DB;

  require_once 'composer/autoload.php';
  
  $connectionParams = [
      'dbname'   => 'yourDbName',
      'user'     => 'yourDbUser',
      'password' => 'yourDbPassword',
      'host'     => 'yourDbHost',
      'driver'   => 'mysqli', // 'pdo_mysql' || 'mysqli'
      'charset'  => 'utf8mb4',
  ];
  $config = new \Doctrine\DBAL\Configuration();
  $doctrineConnection = \Doctrine\DBAL\DriverManager::getConnection(
      $connectionParams,
      $config
  );
  $doctrineConnection->connect();

  $db = DB::getInstanceDoctrineHelper($doctrineConnection);
```

## Using the "ActiveRecord"-Class (OOP database-access)

A simple implement of active record pattern via Arrayy.

#### setDb(DB $db) 
set the DB connection.

```php
  $db = DB::getInstance('YOUR_MYSQL_SERVER', 'YOUR_MYSQL_USER', 'YOUR_MYSQL_PW', 'YOUR_DATABASE');
  ActiveRecord::setDb($db);
```

#### insert() : boolean|int
This function can build insert SQL queries and can insert the current record into database.
If insert was successful, it will return the new id, otherwise it will return false or true (if there are no dirty data).

```php
  $user = new User();
  $user->name = 'demo';
  $user->password = password_hash('demo', PASSWORD_BCRYPT, ["cost" => 15]);
  $user_id = $user->insert();
  
  var_dump($user_id); // the new id 
  var_dump($user->id); // also the new id 
  var_dump($user->getPrimaryKey()); // also the new id 
```

#### fetch(integer  $id = null) : boolean|\ActiveRecord
This function can fetch one record and assign in to current object.
If you call this function with the $id parameter, it will fetch records by using the current primary-key-name.

```php
  $user = new User();

  $user->notnull('id')->order('id desc')->fetch();
  
  // OR //
  
  $user->fetch(1);
  
  // OR //
  
  $user->fetchById(1); // thows "FetchingException" if the ID did not exists
  
  // OR //
  
  $user->fetchByIdIfExists(1); // return NULL if the ID did not exists
  
  var_dump($user->id); // (int) 1
  var_dump($user->getPrimaryKey()); // (int) 1
```

#### fetchAll() : $this[]
This function can fetch all records in the database and will return an array of ActiveRecord objects.

```php
  $user = new User();

  $users = $user->fetchAll();
  
  // OR //
  
  $users = $user->fetchByIds([1]);
  
  // OR //
  
  $users = $user->fetchByIdsPrimaryKeyAsArrayIndex([1]);
    
  var_dump($users[0]->id) // (int) 1
  var_dump($users[0]->getPrimaryKey()); // (int) 1
```

#### update() : boolean|int
This function can build update SQL queries and can update the current record in database, just write the dirty data into database.
If update was successful, it will return the affected rows as int, otherwise it will return false or true (if there are no dirty data).

```php
  $user = new User();
  $user->notnull('id')->orderby('id desc')->fetch();
  $user->email = 'test@example.com';
  $user->update();
```

#### delete() : boolean
This function can delete the current record in the database. 

### Active Record | SQL part functions

#### select()
This function can set the select columns.

```php
  $user = new User();
  $user->select('id', 'name')->fetch();
```

#### from()
This function can set the table to fetch record from.

```php
  $user = new User();
  $user->select('id', 'name')->from('user')->fetch();
```

#### join()
This function can set the table to fetch record from.

```php
  $user = new User();
  $user->join('contact', 'contact.user_id = user.id')->fetch();
```

#### where()
This function can set where conditions.

```php
  $user = new User();
  $user->where('id=1 AND name="demo"')->fetch();
```

#### group()
This function can set the "group by" conditions.

```php
  $user = new User();
  $user->select('count(1) as count')->group('name')->fetchAll();
```

#### order()
This function can set the "order by" conditions.

```php
  $user = new User();
  $user->order('name DESC')->fetch();
```

#### limit()
This function can set the "limit" conditions.

```php
  $user = new User();
  $user->order('name DESC')->limit(0, 1)->fetch();
```

### Active Record | WHERE conditions

#### equal()/eq()

```php
  $user = new User();
  $user->eq('id', 1)->fetch();
```

#### notEqual()/ne()

```php
  $user = new User();
  $user->ne('id', 1)->fetch();
```

#### greaterThan()/gt()

```php
  $user = new User();
  $user->gt('id', 1)->fetch();
```

#### lessThan()/lt()

```php
  $user = new User();
  $user->lt('id', 1)->fetch();
```

#### greaterThanOrEqual()/ge()/gte()

```php
  $user = new User();
  $user->ge('id', 1)->fetch();
```

#### lessThanOrEqual()/le()/lte()

```php
  $user = new User();
  $user->le('id', 1)->fetch();
```

#### like()

```php
  $user = new User();
  $user->like('name', 'de')->fetch();
```

#### in()

```php
  $user = new User();
  $user->in('id', [1, 2])->fetch();
```

#### notIn()

```php
  $user = new User();
  $user->notin('id', [1, 3])->fetch();
```

#### isNull()

```php
  $user = new User();
  $user->isnull('id')->fetch();
```

#### isNotNull()/notNull()

```php
  $user = new User();
  $user->isNotNull('id')->fetch();
```


### Active Record |  Demo

#### Include && Init

```php
use voku\db\DB;
use voku\db\ActiveRecord;

require_once 'composer/autoload.php';

$db = DB::getInstance('YOUR_MYSQL_SERVER', 'YOUR_MYSQL_USER', 'YOUR_MYSQL_PW', 'YOUR_DATABASE');
ActiveRecord::setDb($db);
```

#### Define Class
```php
namespace demo;

use voku\db\ActiveRecord;

/**
 * @property int       $id
 * @property string    $name
 * @property string    $password
 * @property Contact[] $contacts
 * @property Contact   $contact
 */
class User extends ActiveRecord {
  public $table = 'user';
  public $primaryKey = 'id';
  
  public $relations = [
    // format is [$relation_type, $child_namespaced_classname, $foreign_key_of_child]
    'contacts' => [
      self::HAS_MANY, 
      'demo\Contact', 
      'user_id'
    ],
    'contacts_with_backref' => [
        self::HAS_MANY,
        'demo\Contact',
        'user_id',
        null,
        'user',
    ],
    // format may be [$relation_type, $child_namespaced_classname, $foreign_key_of_child, $array_of_sql_part_functions]
    'contact' => [
      self::HAS_ONE, 
      'demo\Contact', 
      'user_id', 
      [
        'where' => '1', 
        'order' => 'id desc',
      ],
    ],
  ];
}

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $email
 * @property string $address
 * @property User   $user
 */
class Contact extends ActiveRecord {
  public $table = 'contact';
  public $primaryKey = 'id';
  
  public $relations = [
    // format is [$relation_type, $parent_namespaced_classname, $foreign_key_in_current_table]
    'user' => [
      self::BELONGS_TO, 
      'demo\User', 
      'user_id'
    ],
  ];
}
```

#### Init data (for testing - use migrations for this step, please)
```sql
CREATE TABLE IF NOT EXISTS user (
  id INTEGER PRIMARY KEY, 
  name TEXT, 
  password TEXT 
);

CREATE TABLE IF NOT EXISTS contact (
  id INTEGER PRIMARY KEY, 
  user_id INTEGER, 
  email TEXT,
  address TEXT
);
```

#### Insert one User into database.
```php
use demo\User;

$user = new User();
$user->name = 'demo';
$user->password = password_hash('demo', PASSWORD_BCRYPT, ["cost" => 15]);
$user_id = $user->insert();

var_dump($user_id); // the new id 
var_dump($user->id); // also the new id 
var_dump($user->getPrimaryKey()); // also the new id 
```

#### Insert one Contact belongs the current user.
```php
use demo\Contact;

$contact = new Contact();
$contact->address = 'test';
$contact->email = 'test1234456@domain.com';
$contact->user_id = $user->id;

var_dump($contact->insert()); // the new id 
var_dump($contact->id); // also the new id 
var_dump($contact->getPrimaryKey()); // also the new id 
```

#### Example to using relations 
```php
use demo\User;
use demo\Contact;

$user = new User();

// fetch one user
var_dump($user->notnull('id')->orderby('id desc')->fetch());

echo "\nContact of User # {$user->id}\n";
// get contacts by using relation:
//   'contacts' => [self::HAS_MANY, 'demo\Contact', 'user_id'],
var_dump($user->contacts);

$contact = new Contact();

// fetch one contact
var_dump($contact->fetch());

// get user by using relation:
//    'user' => [self::BELONGS_TO, 'demo\User', 'user_id'],
var_dump($contact->user);
```

## Logging and Errors

You can hook into the "DB"-Class, so you can use your personal "Logger"-Class. But you have to cover the methods:

```php
$this->trace(string $text, string $name) { ... }
$this->debug(string $text, string $name) { ... }
$this->info(string $text, string $name) { ... }
$this->warn(string $text, string $name) { ... } 
$this->error(string $text, string $name) { ... }
$this->fatal(string $text, string $name) { ... }
```

You can also disable the logging of every sql-query, with the "getInstance()"-parameter "logger_level" from "DB"-Class.
If you set "logger_level" to something other than "TRACE" or "DEBUG", the "DB"-Class will log only errors anymore.

```php
DB::getInstance(
    getConfig('db', 'hostname'),        // hostname
    getConfig('db', 'username'),        // username
    getConfig('db', 'password'),        // password
    getConfig('db', 'database'),        // database
    getConfig('db', 'port'),            // port
    getConfig('db', 'charset'),         // charset
    true,                               // exit_on_error
    true,                               // echo_on_error
    'cms\Logger',                       // logger_class_name
    getConfig('logger', 'level'),       // logger_level | 'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'
    getConfig('session', 'db')          // session_to_db
);
```

Showing the query log: The log comes with the SQL executed, the execution time and the result row count.

```php
  print_r($db->log());
```

To debug mysql errors, use `$db->errors()` to fetch all errors (returns false if there are no errors) or `$db->lastError()` for information about the last error. 

```php
  if ($db->errors()) {
    echo $db->lastError();
  }
```

But the easiest way for debugging is to configure "DB"-Class via "DB::getInstance()" to show errors and exit on error (see the example above). Now you can see SQL-errors in your browser if you are working on "localhost" or you can implement your own "checkForDev()" via a simple function, you don't need to extend the "Debug"-Class. If you will receive error-messages via e-mail, you can implement your own "mailToAdmin()"-function instead of extending the "Debug"-Class.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fvoku%2Fsimple-mysqli.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2Fvoku%2Fsimple-mysqli?ref=badge_large)
