<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;



class GenericLoginError extends Unauthorized
{
    /**
     * GenericLoginError constructor.
     */
    public function __construct()
    {
        parent::__construct('Invalid login credentials provided.');
    }
}