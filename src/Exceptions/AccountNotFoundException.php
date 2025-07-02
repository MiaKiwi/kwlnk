<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class AccountNotFoundException extends Exception
{
    /**
     * AccountNotFoundException constructor.
     * @param string $accountId The ID of the account that was not found.
     */
    public function __construct(string $accountId)
    {
        parent::__construct("Account with ID '$accountId' not found.");
    }
}