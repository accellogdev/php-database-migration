<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:17
 */

namespace Migrate\Command;

use Cocur\Slugify\Slugify;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate:create', description: 'Create a SQL migration')]
class CreateCommand extends AbstractEnvCommand
{

    protected function configure(): void
    {
        $this->addArgument(
            'env',
            InputArgument::REQUIRED,
            'Environment'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->checkEnv();

        $this->init($input, $output);

        $questions = new SymfonyStyle($input, $output);

        $version = $questions->ask("Please choose your version", "");

        $description = $questions->ask("Please enter a description: ");

        $questions->ask("Please choose which editor to use", $this->getDefaultEditor());

        $slugger = new Slugify();
        $filename = $slugger->slugify($description);
        $timestamp = str_pad(str_replace(".", "", microtime(true)), 14, "0");
        if ($version != '') {
            $filename = $timestamp . '_' . $version . '_' . $filename . '.sql';
        } else {
            $filename = $timestamp . '_' . $filename . '.sql';
        }

        $templateFile = file_get_contents(__DIR__ . '/../../templates/migration.tpl');
        $templateFile = str_replace('{DESCRIPTION}', $description, $templateFile);

        $migrationFullPath = $this->getMigrationDir() . '/' . $filename;
        file_put_contents($migrationFullPath, $templateFile);
        $output->writeln("<info>$migrationFullPath created</info>");

        if (!defined('PHPUNIT')) {
            system($this->getDefaultEditor() . " $migrationFullPath  > `tty`");
        }

        return 0;
    }

}
