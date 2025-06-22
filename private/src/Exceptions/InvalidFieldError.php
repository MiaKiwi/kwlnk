<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\NestedError;



class InvalidFieldError extends NestedError
{
    /**
     * InvalidFieldError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Invalid field provided.")
    {
        parent::__construct('invalid_field', $message);
    }
}