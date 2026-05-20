<?php
/**
 * User: aguidet
 * Date: 02/03/15
 * Time: 15:19
 */

namespace Migrate\Test\Command;

use Migrate\Command\AddEnvCommand;
use Migrate\Command\InitCommand;
use Migrate\Enum\Directory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

class AbstractCommandTester extends TestCase
{
    public static $env = 'testing';
    public static $driver = 'sqlite';
    public static $bddName = 'migrate_test';
    public static $username = 'aguidet';
    public static $password = 'aguidet';
    public static $host = 'localhost';
    public static $port = '5432';

    public function cleanEnv(): void
    {
        exec("rm -rf database");

        if (file_exists('test.sqlite')) {
            exec("rm test.sqlite");
        }
    }

    public function createEnv($format = 'yml'): void
    {
        $application = new Application();
        $application->addCommands([new AddEnvCommand()]);

        $command = $application->find('migrate:addenv');
        $commandTester = new CommandTester($command);

        $pdoDrivers = pdo_drivers();
        $driverKey = array_search('sqlite', $pdoDrivers);

        $commandTester->setInputs([
            'testing',           // env name
            (string)$driverKey,  // driver
            'no',                // Use system variables
            'sqlite',            // driver choice
            'test.sqlite',       // database
            '',                  // host
            '',                  // port
            '',                  // username
            '',                  // password
            '',                  // charset
            'changelog',         // changelog table
            'vim'                // editor
        ]);

        $commandTester->execute(['format' => $format]);
    }

    public function initEnv(): void
    {
        $application = new Application();
        $application->addCommands([new InitCommand()]);

        $command = $application->find('migrate:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing'
        ));
    }

    public function createMigration($timestamp, $sqlUp, $sqlDown): void
    {
        $filename = Directory::getMigrationsPath() . '/' . $timestamp . '_migration.sql';

        $content =<<<SQL
--// unit testing migration
-- Migration SQL that makes the change goes here.
$sqlUp

-- @UNDO
-- SQL to undo the change goes here.
$sqlDown

SQL;

        file_put_contents($filename, $content);
    }
}