<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\Gone;



class ExpiredLinkError extends Gone
{
    /**
     * ExpiredLinkError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "The link has expired.")
    {
        parent::__construct($message);
    }
}