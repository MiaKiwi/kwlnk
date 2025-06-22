<?php

namespace Miakiwi\Kwlnk\App;

use Miakiwi\Kwlnk\Exceptions\AccountDisabledException;
use Miakiwi\Kwlnk\Exceptions\AccountNotFoundException;
use Miakiwi\Kwlnk\Exceptions\NotAuthenticatedException;
use Miakiwi\Kwlnk\Exceptions\PasswordIncorrectException;
use MiaKiwi\Kwlnk\Exceptions\TokenExpiredException;
use MiaKiwi\Kwlnk\Exceptions\TokenNotFoundException;
use Miakiwi\Kwlnk\Models\Account;
use Miakiwi\Kwlnk\Models\Token;



class SecurityContext
{
    /**
     * Singleton instance of the security context.
     * @var 
     */
    private static ?self $instance = null;



    /**
     * The ID of the currently authenticated account.
     * @var 
     */
    private ?string $account_id = null;



    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
    }



    /**
     * Get the singleton instance of the security context.
     * @return SecurityContext
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }



    /**
     * Authenticate an account using a token and store it in the security context.
     * @param string|\Miakiwi\Kwlnk\Models\Token $token The token to authenticate or its ID.
     * @throws \MiaKiwi\Kwlnk\Exceptions\TokenNotFoundException If the token is not found.
     * @throws \MiaKiwi\Kwlnk\Exceptions\TokenExpiredException If the token is expired.
     * @throws \Miakiwi\Kwlnk\Exceptions\AccountDisabledException If the account associated with the token is disabled.
     * @return void
     */
    public static function AuthWithToken(string|Token $token): void
    {
        // If a string is passed, find the token with the corresponding ID.
        if (is_string($token)) {
            $token = Token::find($token);
        }



        // If the token is not found, throw an exception.
        if ($token === null) {
            throw new TokenNotFoundException($token);
        }



        // Check if the token is expired.
        if ($token->expired()) {
            throw new TokenExpiredException();
        }



        // Check if the account associated with the token is disabled.
        $account = $token->getAccount();

        if ($account->isDisabled()) {
            throw new AccountDisabledException();
        }



        // Set the authenticated account ID.
        self::getInstance()->account_id = $account->getId();
    }



    /**
     * Authenticate an account and store it in the security context.
     * @param string|\Miakiwi\Kwlnk\Models\Account $account The account to authenticate or its ID.
     * @param string $password The password to authenticate the account.
     * @throws \Miakiwi\Kwlnk\Exceptions\AccountNotFoundException If the account is not found.
     * @throws \Miakiwi\Kwlnk\Exceptions\AccountDisabledException If the account is disabled.
     * @throws \Miakiwi\Kwlnk\Exceptions\PasswordIncorrectException If the password is incorrect.
     * @return void
     */
    public static function Auth(string|Account $account, string $password): void
    {
        // If a string is passed, find the account with the corresponding ID.
        if (is_string($account)) {
            $account_id = $account;

            $account = Account::find($account);



            // If the account is not found, throw an exception.
            if ($account === null) {
                throw new AccountNotFoundException($account_id);
            }
        }



        // Check if the account is disabled.
        if ($account->isDisabled()) {
            throw new AccountDisabledException();
        }



        // Check if the password matches.
        if ($account->verifyPassword($password)) {

            // Set the authenticated account ID.
            self::getInstance()->account_id = $account->getId();

        } else {

            // If the password does not match, throw an exception.
            throw new PasswordIncorrectException();

        }
    }



    /**
     * Deauthenticate the currently authenticated account.
     * @return void
     */
    public static function Deauth(): void
    {
        $account = self::Account();



        // Revoke all the tokens of the account.
        foreach ($account->getActiveTokens() as $token) {
            $token->revoke();
        }



        // Clear the authenticated account ID.
        self::getInstance()->account_id = null;
    }



    /**
     * Indicates whether an account is currently authenticated.
     * @return bool True if an account is authenticated, false otherwise.
     */
    public static function IsAuthed(): bool
    {
        return self::getInstance()->account_id !== null;
    }



    /**
     * Creates a new token for the currently authenticated account.
     * @return Token
     */
    public static function NewToken(): Token
    {
        Logger::get()->debug("Creating a new token for the authenticated account.", [
            'account_id' => self::Id()
        ]);



        $token = new Token(
            bin2hex(openssl_random_pseudo_bytes(16)),
            self::Id()
        );



        $token->save();



        return $token;
    }



    /**
     * Get the last used token of the currently authenticated account, or a specific token by ID.
     * @param null|string $id The ID of the token to retrieve. If null, returns the last used token.
     * @return \Miakiwi\Kwlnk\Models\Token|null Returns the token if found, null otherwise.
     */
    public static function Token(?string $id = null): ?Token
    {
        $account = self::Account();



        // If no ID is provided, return the last used token of the account.
        if ($id === null) {

            $tokens = $account->getActiveTokens();

            $lastUsedToken = null;

            foreach ($tokens as $token) {
                if ($lastUsedToken === null || $token->getLastUsedAt() > $lastUsedToken->getLastUsedAt()) {
                    $lastUsedToken = $token;
                }
            }

            return $lastUsedToken;

        } else {

            // Otherwise, find the token by its ID.
            $token = Token::find($id);

            if ($token === null || $token->getAccountId() !== $account->getId()) {
                return null; // Token not found or does not belong to the authenticated account.
            }

            return $token;

        }
    }



    /**
     * Get the currently authenticated account.
     * @throws \Miakiwi\Kwlnk\Exceptions\NotAuthenticatedException if no account is authenticated.
     * @return Account
     */
    public static function Account(): Account
    {
        if (self::getInstance()->account_id === null) {
            Logger::get()->warning("Attempted to access account without authentication.");

            throw new NotAuthenticatedException();
        }



        $account = Account::find(self::getInstance()->account_id);



        if ($account === null) {
            throw new AccountNotFoundException(self::getInstance()->account_id);
        }



        return $account;
    }



    /**
     * Get the ID of the currently authenticated account.
     * @throws \Miakiwi\Kwlnk\Exceptions\NotAuthenticatedException if no account is authenticated.
     * @return string The ID of the authenticated account
     */
    public static function Id(): string
    {
        if (self::getInstance()->account_id === null) {
            Logger::get()->warning("Attempted to access account ID without authentication.");

            throw new NotAuthenticatedException();
        }

        return self::getInstance()->account_id;
    }
}