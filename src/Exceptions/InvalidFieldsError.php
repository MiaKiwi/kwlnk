<?php

namespace Miakiwi\Kwlnk\Exceptions;

use MiaKiwi\Kaphpir\Errors\Http\BadRequest;
use Rakit\Validation\ErrorBag;



class InvalidFieldsError extends BadRequest
{
    /**
     * InvalidFieldsError constructor.
     * @param array|ErrorBag $errors An array of error messages or an ErrorBag instance.
     */
    public function __construct(array|ErrorBag $errors = [])
    {
        $errors = $errors instanceof ErrorBag ? $errors->all() : $errors;

        // Convert the errors into InvalidFieldError instances
        $errors = array_map(function ($error) {
            return new InvalidFieldError($error);
        }, $errors);

        parent::__construct("Invalid fields provided.", $errors);
    }
}