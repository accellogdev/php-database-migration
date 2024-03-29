<?php
/**
 * Created by PhpStorm.
 * User: aguidet
 * Date: 28/02/15
 * Time: 17:32
 */

namespace Migrate\Command;


use Migrate\Config\ConfigLocator;
use Migrate\Migration;
use Migrate\Utils\ArrayUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dotenv\Dotenv;

class AbstractEnvCommand extends AbstractCommand
{

    protected static $progressBarFormat = '%current%/%max% [%bar%] %percent% % [%message%]';

    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var array
     */
    private $config;

    /**
     * @return \PDO
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getChangelogTable()
    {
        return ArrayUtil::get($this->getConfig(), 'changelog');
    }

    public function getDefaultEditor()
    {
        return ArrayUtil::get($this->getConfig(), 'default_editor');
    }
    
    protected function checkEnv()
    {
        if (!file_exists(getcwd() . '/database/environments')) {
            throw new \RuntimeException("you are not in an initialized php-database-migration directory");
        }
    }

    protected function init(InputInterface $input, OutputInterface $output, $env = null)
    {
        $configDirectory = getcwd() . '/database/environments';
        $configLocator = new ConfigLocator($configDirectory);

        if ($env === null) {
            $env = $input->getArgument('env');
        }

        $parser = $configLocator->locate($env);

        $conf = $parser->parse();

        $this->config = $conf;

        $dotenvfile = ArrayUtil::get($conf['connection'], 'dotenvfile');
        $driver = ArrayUtil::get($conf['connection'], 'driver');
        $port = ArrayUtil::get($conf['connection'], 'port');
        $host = ArrayUtil::get($conf['connection'], 'host');
        $dbname = ArrayUtil::get($conf['connection'], 'database');
        $username = ArrayUtil::get($conf['connection'], 'username');
        $password = ArrayUtil::get($conf['connection'], 'password');
        $charset = ArrayUtil::get($conf['connection'], 'charset');

        $output->writeln("------");
        $output->writeln("Migrate:Init");
        $output->writeln("------");
        $outParams = 'dotenvfile='.$dotenvfile.';driver='.$driver.';port='.$port.';host='.$host.';dbname='.$dbname.';username='.$username.';password='.$password.';charset='.$charset;
        $output->writeln("parameter names:\n" . $outParams . ";");

        if ( ($dotenvfile == 'system') || ( ($dotenvfile != '') && ($dotenvfile != 'no') ) ) {
            if ($dotenvfile != 'system') {
                $output->writeln("");
                $output->writeln(".env configuration - DIR-File:\n" . getcwd() . " - " . $dotenvfile);
                // $dotenv = new Dotenv(getcwd(), $dotenvfile);
                // $dotenv->overload(); //override system variables
                // $dotenv = Dotenv\Dotenv::createImmutable(getcwd(), $dotenvfile); // retorna apenas através de $_ENV['variavel']
                $dotenv = Dotenv::createUnsafeImmutable(getcwd(), $dotenvfile); // retorna em getenv('variavel') e $_ENV['variavel']
                $dotenv->load();
            }

            $dotenvfile = getenv($dotenvfile);
            $driver = getenv($driver);
            $port = getenv($port);
            $host = getenv($host);
            $dbname = getenv($dbname);
            $username = getenv($username);
            $password = getenv($password);
            // $charset = getenv($charset);
            $charset = ArrayUtil::get(getenv($charset), 'charset');

            $outParams = 'dotenvfile='.$dotenvfile.';driver='.$driver.';port='.$port.';host='.$host.';dbname='.$dbname.';username='.$username.';password='.$password.';charset='.$charset;
            $output->writeln("");
            $output->writeln("parameter values read from .env:\n" . $outParams);
        }

        $output->writeln("------");

        $uri = $driver;

        if ($driver == 'sqlite') {
            $uri .= ":$dbname";
        } else if ($driver == 'pgsql') {
            $uri .= ( ($dbname === null) || ($dbname == '') ) ? '' : ":dbname=$dbname";
            $uri .= ( ($host === null) || ($host == '') ) ? '' : ";host=$host";
            $uri .= ( ($port === null) || ($port == '') ) ? '' : ";port=$port";
            $uri .= ( ($charset === null) || ($charset == '') ) ? '' : ";options='--client_encoding=$charset'";
        }  else {
            $uri .= ( ($dbname === null) || ($dbname == '') ) ? '' : ":dbname=$dbname";
            $uri .= ( ($host === null) || ($host == '') ) ? '' : ";host=$host";
            $uri .= ( ($port === null) || ($port == '') ) ? '' : ";port=$port";
            $uri .= ( ($charset === null) || ($charset == '') ) ? '' : ";charset=$charset";
        }
        $this->db = new \PDO(
            $uri,
            $username,
            $password,
            array()
        );

        $output->writeln('<info>connected</info>');
    }

    /**
     * @return array(Migration)
     */
    public function getLocalMigrations()
    {
        $fileList = scandir($this->getMigrationDir());
        $fileList = ArrayUtil::filter($fileList);

        $migrations = array();
        foreach ($fileList as $file) {
            $migration = Migration::createFromFile($file, $this->getMigrationDir());
            $migrations[$migration->getId()] = $migration;
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * @return array(Migration)
     */
    public function getRemoteMigrations()
    {
        $migrations = array();
        $result = $this->getDb()->query("SELECT * FROM {$this->getChangelogTable()} ORDER BY id");
        if ($result) {
            foreach ($result as $row) {
                $migration = Migration::createFromRow($row, $this->getMigrationDir());
                $migrations[$migration->getId()] = $migration;
            }

            ksort($migrations);
        }
        return $migrations;
    }

    /**
     * @return array(Migration)
     */
    public function getRemoteAndLocalMigrations()
    {
        $local = $this->getLocalMigrations();
        $remote = $this->getRemoteMigrations();

        foreach ($remote as $aRemote) {
            $local[$aRemote->getId()] = $aRemote;
        }

        ksort($local);

        return $local;
    }

    public function getToUpMigrations()
    {
        $locales = $this->getLocalMigrations();
        $remotes = $this->getRemoteMigrations();

        foreach ($remotes as $remote) {
            unset($locales[$remote->getId()]);
        }

        ksort($locales);

        return $locales;
    }

    public function getToDownMigrations()
    {
        $remotes = $this->getRemoteMigrations();

        ksort($remotes);

        $remotes = array_reverse($remotes, true);

        return $remotes;
    }


    public function saveToChangelog(Migration $migration)
    {
        $appliedAt = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->getChangelogTable()}
          (id, version, applied_at, description)
          VALUES
          ({$migration->getId()},'{$migration->getVersion()}','{$appliedAt}','{$migration->getDescription()}');
        ";
        $result = $this->getDb()->exec($sql);

        if (! $result) {
            throw new \RuntimeException("changelog table has not been initialized");
        }
    }

    public function removeFromChangelog(Migration $migration)
    {
        $sql = "DELETE FROM {$this->getChangelogTable()} WHERE id = {$migration->getId()}";
        $result = $this->getDb()->exec($sql);
        if (! $result) {
            throw new \RuntimeException("Impossible to delete migration from changelog table");
        }
    }

    /**
     * @param Migration $migration
     * @param bool $changeLogOnly
     */
    public function executeUpMigration(Migration $migration, $changeLogOnly = false)
    {
        if ($migration->getUseTransaction()) {
            $this->getDb()->beginTransaction();
        }

        if ($changeLogOnly === false) {
            $result = $this->getDb()->exec($migration->getSqlUp());

            if ($result === false) {
                // error while executing the migration
                $errorInfo = "";
                $errorInfos = $this->getDb()->errorInfo();
                foreach ($errorInfos as $line) {
                    $errorInfo .= "\n$line";
                }
                if ($migration->getUseTransaction()) {
                    $this->getDb()->rollBack();
                }
                throw new \RuntimeException("migration error, some SQL may be wrong\n\nid: {$migration->getId()}\nfile: {$migration->getFile()}\n" . $errorInfo);
            }
        }

        $this->saveToChangelog($migration);
        if ($migration->getUseTransaction()) {
            $this->getDb()->commit();
        }
    }

    /**
     * @param Migration $migration
     * @param bool $changeLogOnly
     */
    public function executeDownMigration(Migration $migration, $changeLogOnly = false)
    {
        if ($migration->getUseTransaction()) {
            $this->getDb()->beginTransaction();
        }

        if ($changeLogOnly === false) {
            $result = $this->getDb()->exec($migration->getSqlDown());

            if ($result === false) {
                // error while executing the migration
                $errorInfo = "";
                $errorInfos = $this->getDb()->errorInfo();
                foreach ($errorInfos as $line) {
                    $errorInfo .= "\n$line";
                }
                if ($migration->getUseTransaction()) {
                    $this->getDb()->rollBack();
                }
                throw new \RuntimeException("migration error, some SQL may be wrong\n\nid: {$migration->getId()}\nfile: {$migration->getFile()}\n" . $errorInfo);
            }
        }
        $this->removeFromChangelog($migration);
        if ($migration->getUseTransaction()) {
            $this->getDb()->commit();
        }
    }

    protected function filterMigrationsToExecute(InputInterface $input, OutputInterface $output)
    {

        $down = false;

        $toExecute = array();
        if (strpos($this->getName(), 'up') > 0) {
            $toExecute = $this->getToUpMigrations();
        } else {
            $down = true;
            $toExecute = $this->getToDownMigrations();
        }

        $only = $input->getOption('only');
        if ($only !== null) {
            if (! array_key_exists($only, $toExecute)) {
                throw new \RuntimeException("Impossible to execute migration $only!");
            }
            $theMigration = $toExecute[$only];
            $toExecute = array($theMigration->getId() => $theMigration);
        }

        $to = $input->getOption('to');
        if ($to !== null) {
            if (! array_key_exists($to, $toExecute)) {
                throw new \RuntimeException("Target migration $to does not exist or has already been executed/downed!");
            }

            $temp = $toExecute;
            $toExecute = array();
            foreach ($temp as $migration) {
                $toExecute[$migration->getId()] = $migration;
                if ($migration->getId() == $to) {
                    break;
                }
            }

        } else if ($down && count($toExecute) > 1) {
            // WARNING DOWN SPECIAL TREATMENT
            // we dont want all the database to be downed because
            // of a bad command!
            $theMigration = array_shift($toExecute);
            $toExecute = array($theMigration->getId() => $theMigration);
        }

        return $toExecute;
    }
}
