<?php

namespace Miakiwi\Kwlnk\Controllers;

use Exception;
use MiaKiwi\Kaphpir\ApiResponse\HttpApiResponse;
use MiaKiwi\Kaphpir\Errors\Http\InternalServerError;
use MiaKiwi\Kaphpir\Errors\Http\Unauthorized;
use MiaKiwi\Kaphpir\Responses\v25_1_0\Response;
use MiaKiwi\Kaphpir\ResponseSerializer\JsonSerializer;
use Miakiwi\Kwlnk\App\Config;
use Miakiwi\Kwlnk\App\Logger;
use Miakiwi\Kwlnk\App\SecurityContext;
use Miakiwi\Kwlnk\Exceptions\AccountDisabledException;
use Miakiwi\Kwlnk\Exceptions\AccountNotFoundError;
use Miakiwi\Kwlnk\Exceptions\AccountNotFoundException;
use Miakiwi\Kwlnk\Exceptions\GenericLoginError;
use Miakiwi\Kwlnk\Exceptions\InvalidFieldsError;
use Miakiwi\Kwlnk\Exceptions\InvalidPaginationParametersError;
use Miakiwi\Kwlnk\Exceptions\NotAuthenticatedException;
use Miakiwi\Kwlnk\Exceptions\PasswordIncorrectException;
use Miakiwi\Kwlnk\Exceptions\ResourceCreationError;
use Miakiwi\Kwlnk\Exceptions\ResourceDestructionError;
use Miakiwi\Kwlnk\Exceptions\ResourceUpdateError;
use Miakiwi\Kwlnk\Exceptions\TokenNotFoundError;
use Miakiwi\Kwlnk\Models\Account;
use Miakiwi\Kwlnk\Models\Token;
use Pecee\SimpleRouter\SimpleRouter;
use Rakit\Validation\Validator;



class AccountController
{
    /**
     * Handles the request to show all accounts.
     * @return never
     */
    public function index(): never
    {
        $accounts = Account::all();



        // Get pagination parameters
        $get = array_change_key_case($_GET, CASE_LOWER);

        $page = isset($get['page']) ? (int) $get['page'] : 1;
        $rows = max(
            1,
            isset($get['rows']) ? (int) $get['rows'] : count($accounts)
        ); // Default to all accounts if rows not specified

        $pages = ceil(count($accounts) / $rows); // Calculate total pages



        // Validate the pagination parameters
        if ($page < 1) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidPaginationParametersError("Page number must be greater than 0."))->message('Invalid pagination parameters.')
            );

            die();

        } elseif ($rows < 1) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidPaginationParametersError("Rows per page must be greater than 0."))->message('Invalid pagination parameters.')
            );

            die();

        } elseif ($page > $pages && $pages > 0) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidPaginationParametersError("Page number exceeds total pages."))->message('Invalid pagination parameters.')
            );

            die();

        }



        // Paginate the accounts
        $paginated_accounts = array_slice(
            $accounts,
            ($page - 1) * $rows,
            $rows
        );



        // Get the pagination metadata
        $pagination = [
            'pagination' => [
                'total' => count($accounts),
                'page' => $page,
                'per_page' => $rows,
                'total_pages' => $pages,
            ]
        ];



        // Get the KAPIR data of the accounts
        $data = array_map(function (Account $account) {
            return $account->getKapirValue();
        }, $paginated_accounts);



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->metadata($pagination)->message('Accounts retrieved successfully.')
        );

        die();
    }



    /**
     * Handles the request to show a specific account by ID.
     * @param string $id The ID of the account to retrieve.
     * @return never
     */
    public function show(string $id): never
    {
        // If the ID is 'me', get the currently authenticated account
        if ($id === 'me') {

            try {
                $account = SecurityContext::Account();

            } catch (NotAuthenticatedException $e) {

                HttpApiResponse::send(
                    JsonSerializer::getInstance(),
                    (new Response())->error(new Unauthorized("You must be authenticated to access this resource."))->message('Authentication error.')
                );

                die();
            }

        } else {

            // Find the account with this ID
            $account = Account::find($id);

        }



        if ($account === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new AccountNotFoundError($id))->message('Account not found.')
            );

        } else {

            // Get the KAPIR data of the account
            $data = $account->getKapirValue();

            // Send the response
            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->data($data)
            );

        }

        die();
    }



    /**
     * Handles the request to create a new account.
     * @return never
     */
    public function store(): never
    {
        $input = SimpleRouter::request()->getInputHandler()->all();



        Logger::get()->info("Creating new account", [
            'input' => $input
        ]);



        // Validate the input data
        $validation = (new Validator())->validate($input, [
            'id' => "required|regex:/" . Config::get('account.id.regex', '^[a-zA-Z0-9_\-]*$') . "/|not_in:me",
            'password' => 'required|min:8'
        ]);

        if ($validation->fails()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError($validation->errors()))->message('Invalid input data.')
            );

            die();
        }



        // Make sure the account does not already exist
        if (Account::find($input['id']) !== null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError([
                    'ID must be unique'
                ]))->message('Account already exists.')
            );

            die();

        }



        try {

            // Create the account
            $account = new Account(
                $input['id'],
                $input['password']
            );

            // Set the updater and creator IDs
            $account->setUpdaterId(SecurityContext::Id());
            $account->setCreatorId(SecurityContext::Id());

            // Save the account to the database
            $account->save();

        } catch (Exception $e) {

            Logger::get()->error("Failed to create account.", [
                'id' => $input['id'],
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceCreationError('account'))->message('Failed to create account.')
            );

            die();

        }



        // Get the KAPIR data of the account
        $data = $account->getKapirValue();



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->message('Account created successfully.')
        );

        die();
    }



    /**
     * Handles the request to update an existing account by ID.
     * @param string $id The ID of the account to update.
     * @return never
     */
    public function update(string $id): never
    {
        // Find the account with this ID
        $account = Account::find($id);

        if ($account === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new AccountNotFoundError($id))->message('Account not found.')
            );

            die();
        }



        // Get the input data
        $input = SimpleRouter::request()->getInputHandler()->all();



        // Validate the input data
        $validation = (new Validator())->validate($input, [
            'password' => 'min:8',
            'disabled' => 'boolean'
        ]);

        if ($validation->fails()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError($validation->errors()))->message('Invalid input data.')
            );

            die();
        }



        // Update the account with the input data
        $account->setPassword($input['password'] ?? $account->getPassword());
        $account->setDisabled($input['disabled'] ?? $account->isDisabled());

        $account->update();



        // Save the account to the database
        try {

            $account->save();

        } catch (Exception $e) {

            Logger::get()->error("Failed to update account.", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceUpdateError('account'))->message('Failed to update account.')
            );

            die();
        }



        // Get the KAPIR data of the account
        $data = $account->getKapirValue();



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->message('Account updated successfully.')
        );

        die();
    }



    /**
     * Handles the request to delete an account by ID.
     * @param string $id The ID of the account to delete.
     * @return never
     */
    public function destroy(string $id): never
    {
        Logger::get()->info("Deleting account", [
            'id' => $id
        ]);



        // Find the account with this ID
        $account = Account::find($id);

        if ($account === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new AccountNotFoundError($id))->message('Account not found.')
            );

            die();
        }



        // Delete the account
        try {

            $account->delete();

        } catch (Exception $e) {

            Logger::get()->error("Failed to delete account.", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceDestructionError('account'))->message('Failed to delete account.')
            );

            die();
        }



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->success()->message('Account deleted successfully.')
        );

        die();
    }



    /**
     * Handles the request to log in to an account by ID.
     * @return never
     */
    public function login(): never
    {
        // Get the input data
        $input = SimpleRouter::request()->getInputHandler()->all();



        // Validate the input data
        $validation = (new Validator())->validate($input, [
            'id' => "required|regex:/" . Config::get('account.id.regex', '^[a-zA-Z0-9_\-]*$') . "/",
            'password' => 'required'
        ]);

        if ($validation->fails()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError($validation->errors()))->message('Invalid input data.')
            );

            die();
        }

        $id = $input['id'];



        // Try to authenticate the account
        try {

            SecurityContext::Auth(
                $id,
                $input['password']
            );

        } catch (AccountNotFoundException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new GenericLoginError())->message('Invalid account ID or password.')
            );

            die();

        } catch (AccountDisabledException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new AccountDisabledException())->message('Account is disabled.')
            );

            die();

        } catch (PasswordIncorrectException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new GenericLoginError())->message('Invalid account ID or password.')
            );

            die();

        } catch (Exception $e) {

            Logger::get()->error("Failed to login account.", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InternalServerError('Failed to login'))->message('An error occurred while logging in.')
            );

            die();

        }



        // Create a new token for the authenticated account
        $token = SecurityContext::NewToken();



        // Return the account data and the token
        $data = [
            'account' => SecurityContext::Account()->getKapirValue(),
            'token' => $token->getKapirValue()
        ];



        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->message('Login successful.')
        );

        die();
    }



    /**
     * Handles the request to log out the currently authenticated account.
     * @return never
     */
    public function logout(): never
    {
        // Deauthenticate the currently authenticated account
        try {

            SecurityContext::Deauth();

        } catch (NotAuthenticatedException $e) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new Unauthorized("You must be authenticated to log out."))->message('Authentication error.')
            );

            die();

        } catch (Exception $e) {

            Logger::get()->error("Failed to logout account.", [
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InternalServerError('Failed to logout'))->message('An error occurred while logging out.')
            );

            die();

        }



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->success()->message('Logout successful.')
        );

        die();
    }



    public function tokens(string $account_id, ?string $token_id = null): never
    {
        // If the account ID is 'me', get the currently authenticated account
        if ($account_id === 'me') {

            try {
                $account = SecurityContext::Account();

            } catch (NotAuthenticatedException $e) {

                HttpApiResponse::send(
                    JsonSerializer::getInstance(),
                    (new Response())->error(new Unauthorized("You must be authenticated to access this resource."))->message('Authentication error.')
                );

                die();
            }

        } else {

            // Find the account with this ID
            $account = Account::find($account_id);

            if ($account === null) {

                HttpApiResponse::send(
                    JsonSerializer::getInstance(),
                    (new Response())->error(new AccountNotFoundError($account_id))->message('Account not found.')
                );

                die();
            }
        }



        // If no token ID is provided, return all tokens of the account
        if ($token_id === null) {

            $tokens = $account->getActiveTokens();

            $data = array_map(function ($token) {
                return $token->getKapirValue();
            }, $tokens);

            // Send the response with all tokens
            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->data($data)->message('Tokens retrieved successfully.')
            );

            die();

        } else {

            // If 'current' is provided as the token ID, return the last used token
            if ($token_id === 'current') {

                Logger::get()->debug("Retrieving current token for account", [
                    'account_id' => $account->getId()
                ]);

                $token = SecurityContext::Token();

            } else {

                // Otherwise, find the token by its ID
                $tokens = $account->getTokens();

                Logger::get()->debug("Retrieving token by ID for account", [
                    'account_id' => $account->getId(),
                    'tokens' => $tokens,
                    'token_id' => $token_id
                ]);

                $token = array_filter($tokens, function ($t) use ($token_id) {
                    return $t->getId() === $token_id;
                })[0];

            }

            if ($token === null || !($token instanceof Token)) {

                Logger::get()->warning("Token not found for account", [
                    'account_id' => $account->getId(),
                ]);

                HttpApiResponse::send(
                    JsonSerializer::getInstance(),
                    (new Response())->error(new TokenNotFoundError())->message('Token not found.')
                );

                die();
            }

            // Send the response with the specific token
            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->data($token->getKapirValue())
            );

            die();

        }
    }
}