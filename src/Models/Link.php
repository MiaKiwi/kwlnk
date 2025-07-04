<?php

namespace Miakiwi\Kwlnk\Models;

use MiaKiwi\Kaphpir\IData;
use Miakiwi\Kwlnk\App\Database;
use Miakiwi\Kwlnk\App\HasCreationTracker;
use Miakiwi\Kwlnk\App\HasUpdateTracker;
use Miakiwi\Kwlnk\App\Logger;
use Miakiwi\Kwlnk\App\Model;
use Miakiwi\Kwlnk\App\SecurityContext;
use Miakiwi\Kwlnk\Exceptions\ModelLoadException;
use Miakiwi\Kwlnk\Exceptions\NotAuthenticatedException;
use Rakit\Validation\Validator;



class Link extends Model implements IData
{
    use HasUpdateTracker, HasCreationTracker;

    protected static string $db_id_column = 'key';

    /**
     * The unique key identifier for the link.
     * @var string
     */
    protected readonly string $key;

    /**
     * The URI the link points to.
     * @var string
     */
    protected string $uri;

    /**
     * The time when the link expires. If null, the link does not expire.
     * @var null|\DateTime
     */
    protected ?\DateTime $expires_at = null;




    /**
     * Instantiates a new Link object.
     * @param string $key The unique key identifier for the link.
     * @param string $uri The URI the link points to.
     * @param mixed $expires_at The time when the link expires. If null, the link does not expire.
     */
    public function __construct(string $key, string $uri, ?\DateTime $expires_at = null)
    {
        $this->key = $key;
        $this->expires_at = $expires_at;
        $this->uri = $uri;

        // Get the current account ID
        try {
            $accountId = SecurityContext::Id();
        } catch (NotAuthenticatedException $e) {
            $accountId = 'public'; // Default to 'public' if not authenticated
        }

        // Set the creation and update timestamps
        $this->setCreatedAt();
        $this->setUpdatedAt();

        // Set the creator and updater IDs
        $this->setCreatorId($accountId);
        $this->setUpdaterId($accountId);
    }



    /**
     * Sets the URI the link points to.
     * @param string $uri The URI to set for the link.
     * @throws \InvalidArgumentException if the provided URI is not valid.
     * @return void
     */
    public function setUri(string $uri): void
    {
        // Check if the URI is valid
        if (!filter_var($uri, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("The provided URI is not valid.");
        }

        $this->uri = $uri;
    }



    /**
     * Sets the expiry date for the link.
     * @param null|\DateTime|string $expires_at The time when the link expires. If null, the link does not expire.
     * @return void
     */
    public function setExpiryDate(null|\DateTime|string $expires_at): void
    {
        if (is_string($expires_at)) {
            $expires_at = new \DateTime($expires_at);
        }

        $this->expires_at = $expires_at;
    }



    /**
     * Sets the expiry date for the link using a TTL in minutes.
     * @param int $ttl_minutes The time-to-live in minutes for the link.
     * @throws \InvalidArgumentException if the TTL is negative.
     * @return void
     */
    public function setTtlMinutes(int $ttl_minutes): void
    {
        if ($ttl_minutes < 0) {

            throw new \InvalidArgumentException("TTL minutes cannot be negative.");

        } elseif ($ttl_minutes === 0) {

            // If the TTL is equal to 0, set the expiration to null (no expiration)
            $this->expires_at = null; // No expiration

        } else {

            // Otherwise, set the expiration to the current time plus the TTL
            $this->expires_at = (new \DateTime())->modify("+{$ttl_minutes} minutes");

        }
    }



    /**
     * Returns the unique key identifier for the link.
     * @return string The unique key identifier for the link.
     */
    public function getKey(): string
    {
        return $this->key;
    }



    /**
     * Returns the URI the link points to.
     * @return string The URI the link points to.
     */
    public function getUri(): string
    {
        return $this->uri;
    }



    /**
     * Returns the time when the link expires.
     * @return \DateTime|null The time when the link expires. If null, the link does not expire.
     */
    public function getExpiresAt(): ?\DateTime
    {
        return $this->expires_at;
    }



    /**
     * Checks if the link has expired.
     * @return bool True if the link has expired, false otherwise.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < new \DateTime();
    }



    /**
     * Instantiate the object from an array of data.
     * @param array $data An associative array containing the data to populate the object.
     * @throws \Miakiwi\Kwlnk\Exceptions\ModelLoadException If the data is invalid or missing required fields.
     * @return self The instantiated object.
     */
    public static function load(array $data): self
    {
        // Check if all the required fields are present
        $validation = (new Validator())->validate(array_change_key_case($data, CASE_LOWER), [
            'key' => 'required',
            'uri' => 'required|url',
            'expires_at' => 'nullable|date:Y-m-d H:i:s'
        ]);

        if ($validation->fails()) {
            throw new ModelLoadException(static::table(), "Invalid data provided for '" . static::table() . "' model: " . implode(', ', $validation->errors()->all()));
        }



        // Create a new Link instance with the provided data
        $object = new static(
            key: $data['key'],
            uri: $data['uri'],
            expires_at: isset($data['expires_at']) ? (new \DateTime($data['expires_at'])) : null
        );



        // Get the current account ID
        try {
            $accountId = SecurityContext::Id();
        } catch (NotAuthenticatedException $e) {
            $accountId = $data['account_id'] ?? null;
        }



        // Set the creation and update timestamps if they are present in the data
        $object->setCreatedAt(isset($data['created_at']) ? new \DateTime($data['created_at']) : null);
        $object->setUpdatedAt(isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null);

        // Set the creator and updater IDs if they exist in the data
        $object->setCreatorId($data['created_by_id'] ?? $accountId ?? $data['id']);
        $object->setUpdaterId($data['updated_by_id'] ?? $accountId ?? $data['id']);



        Logger::get()->debug("Loaded link from data.", [
            'table' => static::table(),
            'key' => $object->getKey()
        ]);



        return $object;
    }



    /**
     * Find links associated with a specific account.
     * @param \Miakiwi\Kwlnk\Models\Account|string $account The account object or ID to find links for.
     * @return array An array of Link objects associated with the account.
     */
    public static function findFromAccount(Account|string $account): array
    {
        // If an Account object is provided, get its ID.
        $accountId = $account instanceof Account ? $account->getId() : $account;



        Logger::get()->debug("Finding links for account.", [
            'account_id' => $accountId
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        // Fetch all links associated with the account ID.
        $records = $database->select(static::table(), '*', [
            'created_by_id' => $accountId
        ]);

        Logger::get()->debug("Fetched link records from database.", [
            'account_id' => $accountId,
            'count' => count($records),
            'records' => $records
        ]);



        $results = [];

        foreach ($records as $record) {
            // Instantiate the model with the record data.
            try {

                $results[] = static::load($record);

                Logger::get()->debug("Loaded link record successfully.", [
                    'account_id' => $accountId,
                    'record' => $record
                ]);

            } catch (ModelLoadException $e) {

                Logger::get()->error("Failed to load link record.", [
                    'account_id' => $accountId,
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Error loading link record.", [
                    'account_id' => $accountId,
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            }
        }



        return $results;
    }



    /**
     * Save or update the object in the database.
     * @param array $options Optional parameters for saving the object.
     * @return void
     */
    public function save(array $options = []): void
    {
        Logger::get()->debug("Saving link to database.", [
            'table' => static::table(),
            'key' => $this->getKey(),
        ]);



        // Set the updated timestamp and updater ID before saving
        $this->setUpdatedAt();
        $this->setUpdaterId(SecurityContext::Id());



        // Create a new database connection.
        $database = Database::getConnection();



        // Prepare the data to be saved.
        $data = [
            'key' => $this->getKey(),
            'uri' => $this->getUri(),
            'expires_at' => $this->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => $this->getCreatedAtFormatted(),
            'updated_at' => $this->getUpdatedAtFormatted(),
            'created_by_id' => $this->getCreatorId(),
            'updated_by_id' => $this->getUpdaterId()
        ];



        // Check if the link already exists in the database.
        if (self::find($this->getKey())) {

            // The link exists, so we update it.
            Logger::get()->debug("Updating existing link.", [
                'key' => $this->getKey()
            ]);

            try {

                $database->update(static::table(), $data, [
                    'key' => $this->getKey()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Failed to update link.", [
                    'key' => $this->getKey(),
                    'error' => $e->getMessage()
                ]);

                throw $e; // Re-throw the exception after logging

            }

        } else {

            // The link does not exist, so we insert it.
            Logger::get()->debug("Inserting new link.", [
                'key' => $this->getKey()
            ]);

            try {

                $database->insert(static::table(), $data);

            } catch (\Exception $e) {

                Logger::get()->error("Failed to insert link.", [
                    'key' => $this->getKey(),
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
        Logger::get()->debug("Deleting link from database.", [
            'table' => static::table(),
            'key' => $this->getKey(),
        ]);



        // Set the updated timestamp and updater ID before saving
        $this->setUpdatedAt();
        $this->setUpdaterId(SecurityContext::Id());



        // Create a new database connection.
        $database = Database::getConnection();



        try {
            $database->delete(static::table(), [
                'key' => $this->getKey()
            ]);

            Logger::get()->debug("Link deleted successfully.", [
                'key' => $this->getKey()
            ]);

        } catch (\Exception $e) {

            Logger::get()->error("Failed to delete link.", [
                'key' => $this->getKey(),
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw the exception after logging
        }
    }



    public function getKapirValue(): array
    {
        return [
            'key' => $this->getKey(),
            'uri' => $this->getUri(),
            'expires_at' => $this->getExpiresAt() ? $this->getExpiresAt()->format('Y-m-d H:i:s') : null,
            'created_at' => $this->getCreatedAtFormatted(),
            'updated_at' => $this->getUpdatedAtFormatted(),
            'created_by_id' => $this->getCreatorId(),
            'updated_by_id' => $this->getUpdaterId(),
        ];
    }
}