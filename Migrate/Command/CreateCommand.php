<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:17
 */

namespace Migrate\Command;

use Cocur\Slugify\Slugify;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateCommand extends AbstractEnvCommand
{

    protected function configure()
    {
        $this
            ->setName('migrate:create')
            ->setDescription('Create a SQL migration')
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'Environment'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkEnv();

        $this->init($input, $output);

        /* @var $questions QuestionHelper */
        $questions = $this->getHelperSet()->get('question');

        $versionQuestion = new Question("Please chose your version <info>(default '')</info>: ", "");
        $version = $questions->ask($input, $output, $versionQuestion);

        $descriptionQuestion = new Question("Please enter a description: ");
        $description = $questions->ask($input, $output, $descriptionQuestion);

        $editorQuestion = new Question("Please chose which editor to use <info>(default " . $this->getDefaultEditor() . ")</info>: ", "vim");
        $questions->ask($input, $output, $editorQuestion);

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
    }

}
