<?php

namespace Miakiwi\Kwlnk\Exceptions;

use Exception;



class ModelLoadException extends Exception
{
    /**
     * ModelLoadException constructor.
     * @param string $model The model that failed to load.
     * @param string $message The error message.
     */
    public function __construct(string $model, string $message = "")
    {
        parent::__construct("Failed to load model '$model': " . $message);
    }
}