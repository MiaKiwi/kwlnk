<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;



class MissingOrInvalidTokenError extends Unauthorized
{
    /**
     * MissingOrInvalidTokenError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Missing or invalid token.")
    {
        parent::__construct($message);
    }
}