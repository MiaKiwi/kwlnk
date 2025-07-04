<?php

namespace Miakiwi\Kwlnk\Models;

use MiaKiwi\Kaphpir\IData;
use Miakiwi\Kwlnk\App\Config;
use Miakiwi\Kwlnk\App\Database;
use Miakiwi\Kwlnk\App\HasCreationTracker;
use Miakiwi\Kwlnk\App\Logger;
use Miakiwi\Kwlnk\App\Model;
use Miakiwi\Kwlnk\App\SecurityContext;
use Miakiwi\Kwlnk\Exceptions\AccountNotFoundException;
use Miakiwi\Kwlnk\Exceptions\ModelLoadException;
use Rakit\Validation\Validator;



class Token extends Model implements IData
{
    use HasCreationTracker;



    /**
     * The unique identifier and value of the token.
     * @var string
     */
    private readonly string $id;

    /**
     * The account ID associated with the token.
     * @var string
     */
    private string $account_id;

    /**
     * The expiration date of the token.
     * @var \DateTime
     */
    private \DateTime $expires_at;

    /**
     * The date the token was last used.
     * @var \DateTime|null
     */
    private ?\DateTime $last_used_at;



    /**
     * Instantiate a new Token object.
     * @param string $id The unique identifier for the token.
     * @param \Miakiwi\Kwlnk\Models\Account|string $account The account associated with the token, either as an Account object or an account ID.
     * @param mixed $expires_at The expiration date of the token.
     * @param mixed $last_used_at The date the token was last used, or null if it has not been used yet.
     */
    public function __construct(string $id, Account|string $account, ?\DateTime $expires_at = null, ?\DateTime $last_used_at = null)
    {
        $this->id = $id;
        $this->account_id = $account instanceof Account ? $account->getId() : $account;
        $this->expires_at = $expires_at ?? new \DateTime('+' . Config::get('token.default_expiration_minutes', '60') . ' minutes');
        $this->last_used_at = $last_used_at;

        // Set the creation date and creator ID.
        $this->setCreatedAt();
        $this->setCreatorId($account);
    }



    /**
     * Get the ID of the token.
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }



    /**
     * Get the account ID associated with the token.
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->account_id;
    }



    /**
     * Get the account associated with the token.
     * @return Account
     */
    public function getAccount(): Account
    {
        $account = Account::find($this->account_id);

        if ($account === null) {
            throw new AccountNotFoundException($this->account_id);
        }

        return $account;
    }



    /**
     * Get the expiration date of the token.
     * @return \DateTime
     */
    public function getExpiresAt(): \DateTime
    {
        return $this->expires_at;
    }



    /**
     * Get the date the token was last used.
     * @return \DateTime|null
     */
    public function getLastUsedAt(): ?\DateTime
    {
        return $this->last_used_at;
    }



    /**
     * Indicate whether the token has expired.
     * @return bool
     */
    public function expired(): bool
    {
        return $this->expires_at < new \DateTime();
    }



    /**
     * Use the token, updating its last used date to the current time.
     * @return void
     */
    public function use(): void
    {
        // Update the last used date to the current time.
        $this->last_used_at = new \DateTime();



        // Save the token to the database.
        $this->save();
    }



    /**
     * Revoke the token, effectively marking it as expired.
     * @return void
     */
    public function revoke(): void
    {
        $this->use();



        // Set the expiration date to 1993-04-29
        $this->expires_at = new \DateTime('1993-04-29');



        // Save the token to the database.
        $this->save();
    }



    /**
     * Find tokens associated with a specific account.
     * @param \Miakiwi\Kwlnk\Models\Account|string $account The account object or ID to find tokens for.
     * @return array An array of Token objects associated with the account.
     */
    public static function findFromAccount(Account|string $account): array
    {
        // If an Account object is provided, get its ID.
        $accountId = $account instanceof Account ? $account->getId() : $account;



        Logger::get()->debug("Finding tokens for account.", [
            'account_id' => $accountId
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        // Fetch all tokens associated with the account ID.
        $records = $database->select(static::table(), '*', [
            'account_id' => $accountId
        ]);

        Logger::get()->debug("Fetched token records from database.", [
            'account_id' => $accountId,
            'count' => count($records),
            'records' => $records
        ]);



        $results = [];

        foreach ($records as $record) {
            // Instantiate the model with the record data.
            try {

                $results[] = static::load($record);

                Logger::get()->debug("Loaded token record successfully.", [
                    'account_id' => $accountId,
                    'record' => $record
                ]);

            } catch (ModelLoadException $e) {

                Logger::get()->error("Failed to load token record.", [
                    'account_id' => $accountId,
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Error loading token record.", [
                    'account_id' => $accountId,
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            }
        }



        return $results;
    }



    /**
     * Instantiate the object from an array of data.
     * @param array $data An associative array containing the data to populate the object.
     * @throws \Miakiwi\Kwlnk\Exceptions\ModelLoadException If the data is invalid or missing required fields.
     * @return self The instantiated object.
     */
    public static function load(array $data): self
    {
        // Check if all required fields are present
        $validation = (new Validator())->validate(array_change_key_case($data, CASE_LOWER), [
            'id' => 'required',
            'account_id' => 'required',
            'expires_at' => 'required|date:Y-m-d H:i:s',
            'last_used_at' => 'date:Y-m-d H:i:s|nullable',
        ]);

        if ($validation->fails()) {
            throw new ModelLoadException(static::table(), "Invalid data provided for '" . static::table() . "' model: " . implode(', ', $validation->errors()->all()));
        }



        // Create a new Token instance with the provided data
        $object = new self(
            id: $data['id'],
            account: $data['account_id'],
            expires_at: new \DateTime($data['expires_at']),
            last_used_at: $data['last_used_at'] ? new \DateTime($data['last_used_at']) : null
        );



        // Set the creation timestamp if it exists in the data
        $object->setCreatedAt(isset($data['created_at']) ? new \DateTime($data['created_at']) : new \DateTime());

        // Set the creator ID if it exists in the data
        $object->setCreatorId($data['created_by_id'] ?? SecurityContext::Id());



        Logger::get()->debug("Loaded token from data.", [
            'table' => static::table(),
            'token' => $object,
            'type' => get_class($object),
            'id' => $object->getId(),
        ]);



        return $object;
    }



    /**
     * Save or update the object in the database.
     * @param array $options Optional parameters for saving the object.
     * @return void
     */
    public function save(array $options = []): void
    {
        Logger::get()->debug("Saving token to database.", [
            'table' => static::table()
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        // Prepare the data to be saved.
        $data = [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'expires_at' => $this->getExpiresAt()->format('Y-m-d H:i:s'),
            'last_used_at' => $this->getLastUsedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'created_by_id' => $this->getCreatorId()
        ];

        Logger::get()->debug("Prepared data for saving token.", [
            'data' => $data
        ]);



        // Check if the token already exists in the database.
        if (self::find($this->getId())) {

            // The token exists, so we update it.
            Logger::get()->debug("Updating existing token.", [
                'id' => $this->getId()
            ]);

            try {

                $database->update(static::table(), $data, [
                    'id' => $this->getId()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Failed to update token.", [
                    'id' => $this->getId(),
                    'error' => $e->getMessage()
                ]);

                throw $e; // Re-throw the exception after logging

            }

        } else {

            // The token does not exist, so we insert it.
            Logger::get()->debug("Inserting new token.", [
                'id' => $this->getId()
            ]);

            try {

                $database->insert(static::table(), $data, $this->getId());

            } catch (\Exception $e) {

                Logger::get()->error("Failed to insert token.", [
                    'id' => $this->getId(),
                    'error' => $e->getMessage()
                ]);

                throw $e; // Re-throw the exception after logging

            }

        }
    }



    /**
     * Delete the object from the database.
     * @param array $options Optional parameters for deleting the object.
     * @return void
     */
    public function delete(array $options = []): void
    {
        Logger::get()->debug("Deleting token from database.", [
            'table' => static::table(),
            'id' => $this->getId(),
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        try {
            $database->delete(static::table(), [
                'id' => $this->getId()
            ]);

            Logger::get()->debug("Token deleted successfully.", [
                'id' => $this->getId()
            ]);

        } catch (\Exception $e) {

            Logger::get()->error("Failed to delete token.", [
                'id' => $this->getId(),
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw the exception after logging
        }
    }



    public function getKapirValue(): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'expires_at' => $this->getExpiresAt()->format('Y-m-d H:i:s'),
            'last_used_at' => $this->getLastUsedAt() ? $this->getLastUsedAt()->format('Y-m-d H:i:s') : null,
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'created_by_id' => $this->getCreatorId()
        ];
    }
}