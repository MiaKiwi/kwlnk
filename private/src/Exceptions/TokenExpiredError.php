<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;



class TokenExpiredError extends Unauthorized
{
    /**
     * TokenExpiredError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Token has expired.")
    {
        parent::__construct($message);
    }
}