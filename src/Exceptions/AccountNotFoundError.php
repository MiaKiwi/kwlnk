<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\NotFound;



class AccountNotFoundError extends NotFound
{
    /**
     * AccountNotFound constructor.
     * @param string $id The ID of the account that was not found.
     */
    public function __construct(string $id)
    {
        parent::__construct("Account with ID '$id' not found.");
    }
}