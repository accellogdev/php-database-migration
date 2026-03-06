<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:21
 */

namespace Migrate\Command;

use Migrate\Enum\Directory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Migrate\Test\Command\AbstractCommandTester;

class AddenvCommandTest extends AbstractCommandTester
{
    protected function setUp(): void
    {
        $this->cleanEnv();
    }

    protected function tearDown(): void
    {
        $this->cleanEnv();
    }

    public function testExecute()
    {
        $application = new Application();
        $application->addCommands([new AddEnvCommand()]);

        $command = $application->find('migrate:addenv');
        $commandTester = new CommandTester($command);

        $pdoDrivers = pdo_drivers();
        $driverKey = array_search('sqlite', $pdoDrivers);

        $commandTester->setInputs([
            'testing',          // env name
            (string)$driverKey, // driver
            'no',               // Use system variables
            'sqlite',           // driver choice
            'migrate_test',     // database
            'localhost',        // host
            '5432',             // port
            'aguidet',          // username
            'aguidet',          // password
            'utf8',             // charset
            'changelog',        // changelog table
            'vim'               // editor
        ]);

        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesRegularExpression('/Please enter the name of the new environment/', $commandTester->getDisplay());

        $envDir = Directory::getEnvPath();

        $expected = <<<EXPECTED
connection:
    host:     localhost
    driver:   sqlite
    port:     5432
    username: aguidet
    password: aguidet
    database: migrate_test
    charset:  utf8

changelog: changelog
default_editor: vim

EXPECTED;

        $fileContent = file_get_contents($envDir . '/testing.yml');

        $this->assertEquals($expected, $fileContent);
    }

}
