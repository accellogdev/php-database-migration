<?php
/**
 * Created by PhpStorm.
 * User: aguidet
 * Date: 01/03/15
 * Time: 01:47
 */

namespace Migrate\Command;


use Migrate\Test\Command\AbstractCommandTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InitCommandTest extends AbstractCommandTester
{
    public function setUp(): void
    {
        $this->cleanEnv();
        $this->createEnv();
    }

    public function tearDown(): void
    {
        $this->cleanEnv();
    }

    public function testExecute()
    {
        $application = new Application();
        $application->addCommand(new InitCommand());

        $command = $application->find('migrate:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing'
        ));

        $expected = "connected\nchangelog table (changelog) successfully created\n";

        $this->assertEquals($expected, $commandTester->getDisplay());

    }
}