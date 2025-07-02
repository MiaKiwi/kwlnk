<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\NotFound;



class LinkNotFoundError extends NotFound
{
    /**
     * LinkNotFound constructor.
     * @param string $key The key of the link that was not found.
     */
    public function __construct(string $key)
    {
        parent::__construct("Link with key '$key' not found.");
    }
}