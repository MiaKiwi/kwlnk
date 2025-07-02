<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Forbidden;



class AccountDisabledError extends Forbidden
{
    /**
     * AccountDisabledError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Account is disabled.")
    {
        parent::__construct($message);
    }
}