<?php

// Get database configuration from serverconfig.php constants
$dbHost = defined('YIIMP_DBHOST') ? YIIMP_DBHOST : (defined('YAAMP_DBHOST') ? YAAMP_DBHOST : 'localhost');
$dbName = defined('YIIMP_DBNAME') ? YIIMP_DBNAME : (defined('YAAMP_DBNAME') ? YAAMP_DBNAME : 'yaamp');
$dbUser = defined('YIIMP_DBUSER') ? YIIMP_DBUSER : (defined('YAAMP_DBUSER') ? YAAMP_DBUSER : 'root');
$dbPassword = defined('YIIMP_DBPASSWORD') ? YIIMP_DBPASSWORD : (defined('YAAMP_DBPASSWORD') ? YAAMP_DBPASSWORD : '');

// Force TCP/IP for remote hosts by checking if host is not localhost
$isRemoteHost = !in_array($dbHost, ['localhost', '127.0.0.1', '::1']);
$dsn = $isRemoteHost 
    ? "mysql:host={$dbHost};port=3306;dbname={$dbName}"
    : "mysql:host={$dbHost};dbname={$dbName}";

return [
    'class' => 'yii\db\Connection',
    'dsn' => $dsn,
    'username' => $dbUser,
    'password' => $dbPassword,
    'charset' => 'utf8',
    
    // Force TCP/IP connection for remote database hosts
    'attributes' => [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],

    // Schema cache options (for production environment)
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 60,
    'schemaCache' => 'cache',
    
    // Enable query logging - always log errors, debug info only in dev mode
    'enableLogging' => true,
    'enableProfiling' => YII_DEBUG,
    
    // Custom event handlers for database errors
    'on afterOpen' => function($event) {
        if (YII_DEBUG) {
            Yii::info('Database connection established', 'yii\db\Connection');
        }
    },
];
