<?php

namespace Miakiwi\Kwlnk\App;

use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Monolog\Logger as Monologger;



class Logger
{
    /**
     * Returns a LoggerInterface instance for logging.
     * @return LoggerInterface
     */
    public static function get(): LoggerInterface
    {
        // Create a logger instance
        $logger = new Monologger(Config::get('app.name', 'KwLnk'));



        // Add a stream handler to log to a file
        $logger->pushHandler(new StreamHandler(
            $_ENV['LOG_FILE'],
            $_ENV['LOG_LEVEL'] ?? Monologger::DEBUG
        ));

        // Add a stream handler to log to the console
        $logger->pushHandler(new StreamHandler('php://stdout', $_ENV['LOG_LEVEL'] ?? Monologger::DEBUG));



        // Add the caller information to the logger context
        $logger->pushProcessor(function ($record) {
            $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];

            $file_name = basename($stack['file'] ?? 'unknown');
            $line_number = $stack['line'] ?? 'unknown';

            $record['extra']['caller'] = [
                'file' => $file_name,
                'line' => $line_number,
            ];

            return $record;
        });



        return $logger;
    }
}