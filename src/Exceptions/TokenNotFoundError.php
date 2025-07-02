<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;



class TokenNotFoundError extends Unauthorized
{
    /**
     * TokenNotFoundError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Token not found.")
    {
        parent::__construct($message);
    }
}