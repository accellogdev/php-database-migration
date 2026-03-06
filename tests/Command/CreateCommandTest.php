<?php
/**
 * Created by PhpStorm.
 * User: aguidet
 * Date: 01/03/15
 * Time: 02:15
 */

namespace Migrate\Command;


use Migrate\Test\Command\AbstractCommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

define('PHPUNIT', true);

class CreateCommandTest extends AbstractCommandTester
{

    protected function setUp(): void
    {
        $this->cleanEnv();
        $this->createEnv();
        $this->initEnv();
    }

    protected function tearDown(): void
    {
        $this->cleanEnv();
    }

    public function testExecute()
    {
        $application = new Application();
        $application->addCommands([new CreateCommand()]);

        $command = $application->find('migrate:create');
        $commandTester = new CommandTester($command);
        
        $commandTester->setInputs([
            'je suis une super migration &&&ééé',
            '',   // default migration type
            ':x'  // exit editor
        ]);

        $commandTester->execute(['command' => $command->getName()]);

        $matches = [];
        preg_match('/.*: (.*) created/', $commandTester->getDisplay(), $matches);

        $fileName = $matches[1];

        $this->assertFileExists($fileName);
        $content = file_get_contents($fileName);
        $expected =<<<EXPECTED
-- // je suis une super migration &&&ééé
-- Migration SQL that makes the change goes here.

-- @UNDO
-- SQL to undo the change goes here.

EXPECTED;

        $this->assertEquals($expected, $content);
    }
}
