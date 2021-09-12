<?php

use mstodulski\database\EntityManager;
use mstodulski\database\OrmService;

function getConfig() : array
{
    $config['dsn'] = 'mysql:host=localhost;port=3306;dbname=orm;charset=utf8;';
    $config['user'] = 'root';
    $config['password'] = null;
    $config['entityConfigurationDir'] = 'tests/config/';
    $config['migrationDir'] = 'tests/migrations/';
    $config['fixtureDir'] = 'tests/fixtures/';
    $config['mode'] = 'prod';
    $config['sqlAdapterClass'] = 'mstodulski\database\MySQLAdapter';

    return $config;
}

function deleteDir($dirPath)
{
    if (!is_dir($dirPath)) throw new InvalidArgumentException("$dirPath must be a directory");
    if (!str_ends_with($dirPath, '/')) $dirPath .= '/';

    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

$config = getConfig();
$mysqlAdapter = new $config['sqlAdapterClass']();

if (is_dir(getcwd() . '/cache')) {
    deleteDir(getcwd() . '/cache');
}

if (is_dir(getcwd() . '/' . $config['migrationDir'])) {
    deleteDir(getcwd() . '/' . $config['migrationDir']);
}

if (file_exists('tests/config/entityZero.orm.yml')) {
    unlink('tests/config/entityZero.orm.yml');
}

if (file_exists('tests/fixtures/entityZero.yml')) {
    unlink('tests/fixtures/entityZero.yml');
}

$entityManager = EntityManager::create($mysqlAdapter, $config);

$query = /** @lang */"SELECT concat('DROP TABLE IF EXISTS `', table_name, '`;')
                    FROM information_schema.tables
                    WHERE table_schema = 'orm';";

$queries = $entityManager->getDbConnection()->getTable($query);

$entityManager->turnOffCheckForeignKeys();
foreach ($queries as $query) {
    $query = reset($query);
    $entityManager->getDbConnection()->executeQuery($query);
}
$entityManager->turnOnCheckForeignKeys();

$arguments = [];
$arguments[] = "bin/morm";
$arguments[] = "-dsn";
$arguments[] = $config['dsn'];
$arguments[] = "-u";
$arguments[] = $config['user'];
if ($config['password'] != '') {
    $arguments[] = "-p";
    $arguments[] = $config['password'];
}
$arguments[] = "-cd";
$arguments[] = $config['entityConfigurationDir'];
$arguments[] = "-md";
$arguments[] = $config['migrationDir'];
$arguments[] = "-fd";
$arguments[] = $config['fixtureDir'];
$arguments[] = "-ac";
$arguments[] = $config['sqlAdapterClass'];
$arguments[] = "generate";
$arguments[] = "migration";

OrmService::route($arguments);

$arguments = [];
$arguments[] = "bin/morm";
$arguments[] = "-dsn";
$arguments[] = $config['dsn'];
$arguments[] = "-u";
$arguments[] = $config['user'];
if ($config['password'] != '') {
    $arguments[] = "-p";
    $arguments[] = $config['password'];
}
$arguments[] = "-cd";
$arguments[] = $config['entityConfigurationDir'];
$arguments[] = "-md";
$arguments[] = $config['migrationDir'];
$arguments[] = "-fd";
$arguments[] = $config['fixtureDir'];
$arguments[] = "-ac";
$arguments[] = $config['sqlAdapterClass'];
$arguments[] = "migrate";

OrmService::route($arguments);

$arguments = [];
$arguments[] = "bin/morm";
$arguments[] = "-dsn";
$arguments[] = $config['dsn'];
$arguments[] = "-u";
$arguments[] = $config['user'];
if ($config['password'] != '') {
    $arguments[] = "-p";
    $arguments[] = $config['password'];
}
$arguments[] = "-cd";
$arguments[] = $config['entityConfigurationDir'];
$arguments[] = "-md";
$arguments[] = $config['migrationDir'];
$arguments[] = "-fd";
$arguments[] = $config['fixtureDir'];
$arguments[] = "-ac";
$arguments[] = $config['sqlAdapterClass'];
$arguments[] = "import";
$arguments[] = "fixtures";

OrmService::route($arguments);
