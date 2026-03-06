<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:14
 */

namespace Migrate\Command;


use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate:down', description: 'Rollback all waiting migration down to [to] option if precised')]
class DownCommand extends AbstractEnvCommand {

    protected function configure(): void
    {
        $this
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'Environment'
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration will be downed to this migration id included'
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_REQUIRED,
                'If you need to down this migration id only'
            )
            ->addOption(
                'changelog-only',
                null,
                InputOption::VALUE_NONE,
                'Mark as applied without executing SQL '
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->checkEnv();

        $this->init($input, $output);

        $changeLogOnly = (bool) $input->getOption('changelog-only');
        $questions = new SymfonyStyle($input, $output);

        $areYouSure = $questions->ask("Are you sure? (yes/no)", 'no');

        if ($areYouSure == 'yes') {

            $toExecute = $this->filterMigrationsToExecute($input, $output);

            if (count($toExecute) == 0) {

                $output->writeln("your database is already up to date");

            } else {

                $progress = new ProgressBar($output, count($toExecute));

                $progress->setFormat(self::$progressBarFormat);
                $progress->setMessage('');
                $progress->start();

                /* @var $migration \Migrate\Migration */
                foreach ($toExecute as $migration) {
                    $progress->setMessage($migration->getDescription());
                    $this->executeDownMigration($migration, $changeLogOnly);
                    $progress->advance();
                }

                $progress->finish();
                $output->writeln("");
            }
        } else {
            $output->writeln("<error>Rollback aborted</error>");
        }

        return 0;
    }
}
