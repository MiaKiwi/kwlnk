<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class PasswordIncorrectException extends Exception
{
    /**
     * PasswordIncorrectException constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Password is incorrect.")
    {
        parent::__construct($message);
    }
}