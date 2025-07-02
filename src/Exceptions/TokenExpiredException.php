<?php

namespace MiaKiwi\Kwlnk\Exceptions;

use Exception;



class TokenExpiredException extends Exception
{
    /**
     * TokenExpiredException constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Token has expired.")
    {
        parent::__construct($message);
    }
}