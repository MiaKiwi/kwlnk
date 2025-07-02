<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;



class ResourceCreationError extends InternalServerError
{
    /**
     * ResourceCreationError constructor.
     * @param string $resource The resource that failed to be created.
     */
    public function __construct(string $resource)
    {
        parent::__construct('Failed to create resource: ' . $resource);
    }
}