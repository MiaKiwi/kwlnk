<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class LinkKeyAlreadyExistsException extends Exception
{
    /**
     * LinkKeyAlreadyExistsException constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "The link key already exists.")
    {
        parent::__construct($message);
    }
}