<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:41
 */

namespace Migrate\Command;

use Migrate\Config\ConfigLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate:addenv', description: 'Initialise an environment to work with php db migrate')]
class AddEnvCommand extends AbstractEnvCommand {

    protected function configure(): void
    {
        $this->addArgument(
            'format',
            InputArgument::OPTIONAL,
            'Environment file format: (yml, json or php), default: yml'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $format = $input->getArgument('format');
        $supportedFormats = array_keys(ConfigLocator::$SUPPORTED_PARSERS);

        if (is_null($format)) {
            $format = 'yml';
        }

        if (!in_array($format, $supportedFormats)) {
            throw new \RuntimeException(sprintf('Invalid file format: %s', $format));
        }

        // init directories
        if(! file_exists($this->getMainDir())) {
            mkdir($this->getMainDir());
        }

        if(! file_exists($this->getEnvironmentDir())) {
            mkdir($this->getEnvironmentDir());
        }

        if(! file_exists($this->getMigrationDir())) {
            mkdir($this->getMigrationDir());
        }

        $drivers = pdo_drivers();        

        $questions = new SymfonyStyle($input, $output);

        $envName = $questions->ask("Please enter the name of the new environment", "dev");

        $envConfigFile = $this->getEnvironmentDir() . '/' . $envName . '.' . $format;
        if (file_exists($envConfigFile)) {
            throw new \InvalidArgumentException("environment [$envName] is already defined!");
        }

        $systemvar = $questions->ask("Read database connection variables from System Variables? (yes/no)", 'yes');

        if ($systemvar == 'yes') {
            $dotenvfile = $questions->ask("DotEnv filename (.env)?", '.env');
            $driver = $questions->choice("Please enter your pdo driver: ", $drivers);
        } else {
            $dotenvfile = 'no';
            $driver = $questions->choice("Please choose your pdo driver", $drivers);
        }

        $dbName = $questions->ask("Please enter the database name (or the database file location): ", "~");
        $dbHost = $questions->ask("Please enter the database host (if needed): ", "~");
        $dbPort = $questions->ask("Please enter the database port (if needed): ", "~");
        $dbUserName = $questions->ask("Please enter the database user name (if needed): ", "~");
        $dbUserPassword = $questions->ask("Please enter the database user password (if needed): ", "~");
        $dbCharset = $questions->ask("Please enter the database charset (if needed): ", "~");
        $changelogTable = $questions->ask("Please enter the changelog table (default changelog): ", "changelog");
        $defaultEditor = $questions->ask("Please enter the text editor to use by default (default vim): ", "vim");

        $confTemplate = file_get_contents(__DIR__ . '/../../templates/env.' . $format . '.tpl');
        $confTemplate = str_replace('{DOTENVFILE}', $dotenvfile, $confTemplate);
        $confTemplate = str_replace('{DRIVER}', $driver, $confTemplate);
        $confTemplate = str_replace('{HOST}', $dbHost, $confTemplate);
        $confTemplate = str_replace('{PORT}', $dbPort, $confTemplate);
        $confTemplate = str_replace('{USERNAME}', $dbUserName, $confTemplate);
        $confTemplate = str_replace('{PASSWORD}', $dbUserPassword, $confTemplate);
        $confTemplate = str_replace('{DATABASE}', $dbName, $confTemplate);
        $confTemplate = str_replace('{CHARSET}', $dbCharset, $confTemplate);
        $confTemplate = str_replace('{CHANGELOG}', $changelogTable, $confTemplate);
        $confTemplate = str_replace('{EDITOR}', $defaultEditor, $confTemplate);

        file_put_contents($envConfigFile, $confTemplate);

        return 0;
    }
}
