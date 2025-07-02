<?php

namespace Miakiwi\Kwlnk\Middlewares;

use Exception;
use MiaKiwi\Kaphpir\ApiResponse\HttpApiResponse;
use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;
use MiaKiwi\Kaphpir\Responses\v25_1_0\Response;
use MiaKiwi\Kaphpir\ResponseSerializer\JsonSerializer;
use Miakiwi\Kwlnk\App\Logger;
use Miakiwi\Kwlnk\App\SecurityContext;
use Miakiwi\Kwlnk\Exceptions\AccountDisabledError;
use Miakiwi\Kwlnk\Exceptions\AccountDisabledException;
use Miakiwi\Kwlnk\Exceptions\TokenExpiredError;
use MiaKiwi\Kwlnk\Exceptions\TokenExpiredException;
use Miakiwi\Kwlnk\Exceptions\TokenNotFoundError;
use MiaKiwi\Kwlnk\Exceptions\TokenNotFoundException;
use Miakiwi\Kwlnk\Models\Token;
use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;
use Miakiwi\Kwlnk\Exceptions\MissingOrInvalidTokenError;



class Auth implements IMiddleware
{
    /**
     * Handles the request and performs authentication using a token from the request header.
     * @param \Pecee\Http\Request $request The HTTP request object.
     * @return void
     */
    public function handle(Request $request): void
    {
        // Check if a token is provided in the request header
        $token = $request->getHeader('Authorization');



        // Send an error if no token is provided or if the format is invalid
        if ($token === null || !str_starts_with($token, 'Bearer ')) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new MissingOrInvalidTokenError("Header 'Authorization' is missing or malformed."))->message('Authentication error.')
            );

            die();

        }



        // Extract the token value from the header
        $tokenValue = substr($token, 7); // Remove 'Bearer ' prefix

        if ($tokenValue === null || $tokenValue === '') {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new MissingOrInvalidTokenError("Token value is missing."))->message('Authentication error.')
            );

            die();

        }



        // Get the token
        $token = Token::find($tokenValue);

        if ($token === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new MissingOrInvalidTokenError("Token not found."))->message('Authentication error.')
            );

            die();

        }



        // Authenticate the account with the token
        try {

            SecurityContext::AuthWithToken($token);

        } catch (TokenNotFoundException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new TokenNotFoundError())->message('Authentication error.')
            );

            die();

        } catch (TokenExpiredException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new TokenExpiredError())->message('Your session has expired.')
            );

            die();

        } catch (AccountDisabledException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new AccountDisabledError())->message('This account is disabled.')
            );

            die();

        } catch (Exception $e) {

            Logger::get()->error("An error occurred during authentication.", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InternalServerError('Internal authentication error'))->message('An internal error has occurred.')
            );

            die();

        }



        // If the token is valid, continue processing the request
        Logger::get()->info("Authentication successful.", [
            'token' => $tokenValue,
            'account_id' => SecurityContext::Account()->getId()
        ]);
    }
}