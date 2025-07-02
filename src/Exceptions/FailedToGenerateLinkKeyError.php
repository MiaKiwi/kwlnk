<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;



class FailedToGenerateLinkKeyError extends InternalServerError
{
    /**
     * FailedToGenerateLinkKeyError constructor.
     * @param string $message The error message.
     */
    public function __construct(string $message = "Failed to generate link key")
    {
        parent::__construct($message);
    }
}