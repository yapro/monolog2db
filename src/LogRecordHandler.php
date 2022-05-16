<?php

declare(strict_types=1);

namespace YaPro\Monolog2db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

use Throwable;
use UnexpectedValueException;

use function array_key_exists;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class LogRecordHandler extends AbstractProcessingHandler
{
    private Connection $connection;
    private string $logFile;
    private string $tableName;

    public function __construct(
        string $dbHost,
        string $dbPort,
        string $dbName,
        string $dbUserName,
        string $dbPassword,
        string $logFileForUnexpectedErrors,
        string $tableName = null
    ) {
        parent::__construct(Logger::NOTICE, true);
        $this->setFormatter(new LogRecordFormatter());
        // отдельный коннект к отдельной бд потому что:
        // - лучше использовать отдельную бд которую не нужно бэкапить
        // - приложение иногда начинает транзакцию, которая может не закончится и затем пишет в лог, а транзакция ведь
        //   может закончится падением и следовательно никаких логов мы не увидим.
        // https://www.doctrine-project.org/projects/doctrine-dbal/en/2.10/reference/configuration.html
        $this->connection = DriverManager::getConnection([
            'host' => '$dbHost',
            'port' => $dbPort,
            'dbname' => $dbName,
            'user' => $dbUserName,
            'password' => $dbPassword,
            'driver' => 'pdo_mysql',
        ]);
        $this->logFile = $logFileForUnexpectedErrors;
        $this->tableName = $tableName ?? 'system_log';
    }

    protected function write(array $record): void
    {
        try {
            if (
                $record['channel'] === 'event' ||
                $record['channel'] === 'doctrine'
            ) {
                return;
            }
            if (!array_key_exists('formatted', $record)) {
                $this->writeError(new UnexpectedValueException('Log record has no formatted state'));
                return;
            }
            $this->connection->insert($this->tableName, $record['formatted']);
        } catch (Throwable $exception) {
            $this->writeError($record['formatted']);
            $this->writeError($this->getExceptionDetails($exception));
        }
    }
    
    private function getExceptionDetails(Throwable $exception): array
    {
        return [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ];
    }
    
    private function writeError($record)
    {
        file_put_contents(
            $this->logFile,
            date('Y-m-d H:i:s') . ': ' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}
