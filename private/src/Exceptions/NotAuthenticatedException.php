<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class NotAuthenticatedException extends Exception
{
    /**
     * NotAuthenticatedException constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "No account is not authenticated.")
    {
        parent::__construct($message);
    }
}