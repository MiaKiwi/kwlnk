<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class AccountDisabledException extends Exception
{
    /**
     * AccountDisabledException constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Account is disabled.")
    {
        parent::__construct($message);
    }
}