<?php

namespace Miakiwi\Kwlnk\App;

use MiaKiwi\Kaphpir\ApiResponse\HttpApiResponse;
use MiaKiwi\Kaphpir\Errors\Http\Forbidden;
use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;
use MiaKiwi\Kaphpir\Errors\Http\MethodNotAllowed;
use MiaKiwi\Kaphpir\Errors\Http\NotFound;
use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;
use MiaKiwi\Kaphpir\Responses\v25_1_0\Response;
use MiaKiwi\Kaphpir\ResponseSerializer\JsonSerializer;
use MiaKiwi\Kaphpir\Settings\DefaultSettings;
use Miakiwi\Kwlnk\Controllers\AccountController;
use Miakiwi\Kwlnk\Controllers\LinkController;
use Miakiwi\Kwlnk\Middlewares\Auth;
use Pecee\Http\Request;
use Pecee\SimpleRouter\SimpleRouter;



// Set the error reporting level based on the LOG_LEVEL environment variable
if (!isset($_ENV['LOG_LEVEL']) || $_ENV['LOG_LEVEL'] !== 'debug') {
    // Hide all errors and warnings
    error_reporting(0);
    ini_set('display_errors', '0');
}



// Load the Kaphpir settings
$KaphpirSettings = Config::get('kaphpir.settings', []);

foreach ($KaphpirSettings as $key => $value) {
    Logger::get()->debug(
        "Setting Kaphpir setting",
        [
            'key' => $key,
            'value' => $value
        ]
    );


    DefaultSettings::getInstance()->setSetting($key, $value);
}



SecurityContext::Auth('default_administrator', 'kiwis are birds but also fruits and people for some reason?');



// ----- Accounts ----- \\
SimpleRouter::group(
    [
        'middleware' => [Auth::class]
    ],
    function () {
        // Fetch all users
        SimpleRouter::get(
            $_ENV['API_ROOT'] . 'users',
            [AccountController::class, 'index']
        );


        // Fetch a specific user by ID
        SimpleRouter::get(
            $_ENV['API_ROOT'] . 'users/{id}',
            [AccountController::class, 'show']
        );


        // Create a new user
        SimpleRouter::post(
            $_ENV['API_ROOT'] . 'users',
            [AccountController::class, 'store']
        );


        // Update an existing user
        SimpleRouter::put(
            $_ENV['API_ROOT'] . 'users/{id}',
            [AccountController::class, 'update']
        );

        SimpleRouter::patch(
            $_ENV['API_ROOT'] . 'users/{id}',
            [AccountController::class, 'update']
        );


        // Delete a user
        SimpleRouter::delete(
            $_ENV['API_ROOT'] . 'users/{id}',
            [AccountController::class, 'destroy']
        );


        // Fetch the tokens of a user
        SimpleRouter::get(
            $_ENV['API_ROOT'] . 'users/{user_id}/tokens/{token_id?}',
            [AccountController::class, 'tokens']
        );
    }
);



// ----- Authentication ----- \\
SimpleRouter::post(
    $_ENV['API_ROOT'] . 'login',
    [AccountController::class, 'login']
);

SimpleRouter::get(
    $_ENV['API_ROOT'] . 'logout',
    [AccountController::class, 'logout']
)->addMiddleware(Auth::class);



// ----- Links ----- \\
SimpleRouter::group(
    [
        'middleware' => [Auth::class]
    ],
    function () {
        // Fetch all links
        SimpleRouter::get(
            $_ENV['API_ROOT'] . 'links',
            [LinkController::class, 'index']
        );


        // Fetch a specific link by key
        SimpleRouter::get(
            $_ENV['API_ROOT'] . 'links/{key}',
            [LinkController::class, 'show']
        );


        // Create a new link
        SimpleRouter::post(
            $_ENV['API_ROOT'] . 'links',
            [LinkController::class, 'store']
        );


        // Update an existing link
        SimpleRouter::put(
            $_ENV['API_ROOT'] . 'links/{key}',
            [LinkController::class, 'update']
        );

        SimpleRouter::patch(
            $_ENV['API_ROOT'] . 'links/{key}',
            [LinkController::class, 'update']
        );


        // Delete a link
        SimpleRouter::delete(
            $_ENV['API_ROOT'] . 'links/{key}',
            [LinkController::class, 'destroy']
        );
    }
);

// Redirect to the URI of a link
SimpleRouter::get(
    $_ENV['LINKS_ROOT'] . '{key}',
    [LinkController::class, 'redirect']
);



// ----- Errors ----- \\
SimpleRouter::error(function (Request $request, \Exception $exception) {
    Logger::get()->critical("Routing error", [
        'exception' => [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ],
        'request' => [
            'method' => $request->getMethod(),
            'uri' => $request->getUrl(),
            'headers' => $request->getHeaders(),
        ]
    ]);



    switch ($exception->getCode()) {
        case 404:
            $error = new NotFound('Routing error.');
            $message = 'The requested resource was not found.';
            break;

        case 401:
            $error = new Unauthorized('Routing error.');
            $message = 'You are not authorized to access this resource.';
            break;

        case 403:
            if (preg_match('/or method \"(?:post|get|put|patch|delete|head)\" not allowed\./', $exception->getMessage())) {

                $error = new MethodNotAllowed('Routing error.');
                $message = 'The requested method is not allowed for this resource.';

            } else {

                $error = new Forbidden('Routing error.');
                $message = 'You do not have permission to access this resource.';

            }

            break;

        default:
            $error = new InternalServerError('Routing error.');
            $message = 'An internal error occurred.';
            break;
    }

    Logger::get()->error("Routing error", [
        'exception' => [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ]);

    HttpApiResponse::send(
        JsonSerializer::getInstance(),
        (new Response())->error($error)->message($message)
    );

    die();
});