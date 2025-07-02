<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;



class ResourceDestructionError extends InternalServerError
{
    /**
     * ResourceDestructionError constructor.
     * @param string $resource The resource that failed to be destroyed.
     */
    public function __construct(string $resource)
    {
        parent::__construct('Failed to destroy resource: ' . $resource);
    }
}