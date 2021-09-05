<?php

use mstodulski\database\EntityManager;
use mstodulski\database\MySQLAdapter;
use mstodulski\database\OrmService;
use Symfony\Component\Yaml\Yaml;

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

$config = Yaml::parseFile('tests/config.yml');
$mysqlAdapter = new MySQLAdapter();

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
$arguments['1'] = 'tests/config.yml';
$arguments['2'] = 'generate';
$arguments['3'] = 'migration';

OrmService::route($arguments);

$arguments = [];
$arguments['1'] = 'tests/config.yml';
$arguments['2'] = 'migrate';

OrmService::route($arguments);

$arguments = [];
$arguments['1'] = 'tests/config.yml';
$arguments['2'] = 'import';
$arguments['3'] = 'fixtures';

OrmService::route($arguments);