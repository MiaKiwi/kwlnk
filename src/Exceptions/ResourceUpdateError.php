<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;



class ResourceUpdateError extends InternalServerError
{
    /**
     * ResourceUpdateError constructor.
     * @param string $resource The resource that failed to be updated.
     */
    public function __construct(string $resource)
    {
        parent::__construct('Failed to update resource: ' . $resource);
    }
}