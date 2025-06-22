<?php

namespace MiaKiwi\Kwlnk\Exceptions;

use Exception;



class TokenNotFoundException extends Exception
{
    /**
     * TokenNotFoundException constructor.
     * @param string $token The token that was not found.
     */
    public function __construct(string $token)
    {
        parent::__construct("Token '$token' not found.");
    }
}