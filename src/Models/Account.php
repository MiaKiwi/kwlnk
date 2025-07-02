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
use Rakit\Validation\Validator;



class Account extends Model implements IData
{
    use HasUpdateTracker, HasCreationTracker;



    protected static string $db_id_column = 'key';

    /**
     * The unique identifier for the account.
     * @var string
     */
    private readonly string $id;

    /**
     * The hashed and salted password for the account.
     * @var string
     */
    private string $password;

    /**
     * Indicates whether the account is disabled.
     * @var bool
     */
    private bool $disabled = false;



    /**
     * Instantiate a new Account object.
     * @param string $id The unique identifier for the account.
     * @param string $password The password of the user. Can be a plain text string which will be hashed, or a pre-hashed string.
     * @param bool $disabled Indicates whether the account is disabled. Defaults to false.
     */
    public function __construct(string $id, string $password, bool $disabled = false)
    {
        $this->id = $id;

        $this->setPassword($password);

        $this->setDisabled($disabled);



        // Set the creation and update timestamps.
        $this->setCreatedAt();
        $this->setUpdatedAt();

        // Set the creator and updater IDs to the account ID.
        $this->setCreatorId($id);
        $this->setUpdaterId($id);
    }



    /**
     * Set the password for the account. Accepts both plain text and pre-hashed passwords, though plain text passwords will be hashed.
     * @param string $password The password to set for the account. If it is a plain text password, it will be hashed before being stored.
     * @return void
     */
    public function setPassword(string $password): void
    {
        // Check if the password is already hashed
        if (password_get_info($password)['algo']) {
            // It is, so we can set it directly
            $this->password = $password;
        } else {
            // It is not, so we hash it
            $this->password = password_hash($password, PASSWORD_BCRYPT);
        }
    }



    /**
     * Sets the disabled status of the account.
     * @param bool|string|int $disabled The disabled status to set. Can be a boolean, a string that can be converted to a boolean, or an integer (0 or 1).
     * @return void
     */
    public function setDisabled(bool|string|int $disabled): void
    {
        // If the value isn't a boolean, convert it to one
        if (!is_bool($disabled)) {
            $boolean_value = (is_string($disabled) ? filter_var($disabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $disabled);

            $this->disabled = $boolean_value !== null ? $boolean_value : false;
        } else {
            $this->disabled = $disabled;
        }
    }



    /**
     * Disable the account. This sets the disabled status to true.
     * @return void
     */
    public function disable(): void
    {
        $this->setDisabled(true);
    }



    /**
     * Enable the account. This sets the disabled status to false.
     * @return void
     */
    public function enable(): void
    {
        $this->setDisabled(false);
    }



    /**
     * Get the ID of the account.
     * @return string The unique identifier for the account.
     */
    public function getId(): string
    {
        return $this->id;
    }



    /**
     * Check if the account is disabled.
     * @return bool Returns true if the account is disabled, false otherwise.
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }



    /**
     * Get the password of the account.
     * @return string The hashed and salted password for the account.
     */
    public function getPassword(): string
    {
        return $this->password;
    }



    /**
     * Verify a given password against the stored password hash.
     * @param string $password The password to verify.
     * @return bool Returns true if the password matches the stored hash, false otherwise.
     */
    public function verifyPassword(string $password): bool
    {
        // Verify the password against the stored hash
        return password_verify($password, $this->getPassword());
    }



    public function getLinks(): array
    {
        // Fetch all links associated with this account from the database.
        $links = Link::findFromAccount($this->getId());

        Logger::get()->debug("Retrieved links for account.", [
            'account_id' => $this->getId(),
            'link_count' => count($links),
            'links' => $links
        ]);



        return $links;
    }



    /**
     * Get all tokens associated with this account.
     * @return \Miakiwi\Kwlnk\Models\Token[] An array of Token objects associated with this account.
     */
    public function getTokens(): array
    {
        // Fetch all tokens associated with this account from the database.
        $tokens = Token::findFromAccount($this->getId());

        Logger::get()->debug("Retrieved tokens for account.", [
            'account_id' => $this->getId(),
            'token_count' => count($tokens),
            'tokens' => $tokens
        ]);



        return $tokens;
    }



    /**
     * Get all active tokens associated with this account.
     * @return \Miakiwi\Kwlnk\Models\Token[] An array of active Token objects associated with this account.
     */
    public function getActiveTokens(): array
    {
        // Fetch all active tokens associated with this account from the database.
        $tokens = Token::findFromAccount($this->getId());



        // Filter the tokens to only include those that are active.
        $activeTokens = array_filter($tokens, function (Token $token) {
            return !$token->expired();
        });



        return $activeTokens;
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
            'password' => 'required',
            'disabled' => 'boolean|default:false'
        ]);

        if ($validation->fails()) {
            throw new ModelLoadException(self::table(), "Invalid data provided for '" . self::table() . "' model: " . implode(', ', $validation->errors()->all()));
        }



        // Create a new Account instance with the provided data
        $object = new self(
            id: $data['id'],
            password: $data['password'],
            disabled: $data['disabled'] ?? false
        );



        // Set the creation and update timestamps if they exist in the data
        $object->setCreatedAt(isset($data['created_at']) ? new \DateTime($data['created_at']) : null);
        $object->setUpdatedAt(isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null);

        // Set the creator and updater IDs if they exist in the data
        $object->setCreatorId($data['created_by_id'] ?? SecurityContext::Id() ?? $data['id']);
        $object->setUpdaterId($data['updated_by_id'] ?? SecurityContext::Id() ?? $data['id']);



        Logger::get()->debug("Loaded account from data.", [
            'table' => self::table(),
            'id' => $object->getId()
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
        Logger::get()->debug("Saving account to database.", [
            'table' => self::table(),
            'id' => $this->getId(),
        ]);



        // Set the updated timestamp and updater ID before saving
        $this->setUpdatedAt();
        $this->setUpdaterId(SecurityContext::Id());



        // Create a new database connection.
        $database = Database::getConnection();



        // Prepare the data to be saved.
        $data = [
            'id' => $this->getId(),
            'password' => $this->getPassword(),
            'disabled' => $this->disabled ? 1 : 0,
            'created_at' => $this->getCreatedAtFormatted(),
            'updated_at' => $this->getUpdatedAtFormatted(),
            'created_by_id' => $this->getCreatorId(),
            'updated_by_id' => $this->getUpdaterId()
        ];



        // Check if the account already exists in the database.
        if (self::find($this->getId())) {

            // The account exists, so we update it.
            Logger::get()->debug("Updating existing account.", [
                'id' => $this->getId()
            ]);

            try {

                $database->update(self::table(), $data, [
                    'id' => $this->getId()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Failed to update account.", [
                    'id' => $this->getId(),
                    'error' => $e->getMessage()
                ]);

                throw $e; // Re-throw the exception after logging

            }

        } else {

            // The account does not exist, so we insert it.
            Logger::get()->debug("Inserting new account.", [
                'id' => $this->getId()
            ]);

            try {

                $database->insert(self::table(), $data);

            } catch (\Exception $e) {

                Logger::get()->error("Failed to insert account.", [
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
        Logger::get()->debug("Deleting account from database.", [
            'table' => self::table(),
            'id' => $this->getId(),
        ]);



        // Set the updated timestamp and updater ID before saving
        $this->setUpdatedAt();
        $this->setUpdaterId(SecurityContext::Id());



        // Create a new database connection.
        $database = Database::getConnection();



        try {
            $database->delete(self::table(), [
                'id' => $this->getId()
            ]);

            Logger::get()->debug("Account deleted successfully.", [
                'id' => $this->getId()
            ]);

        } catch (\Exception $e) {

            Logger::get()->error("Failed to delete account.", [
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
            'disabled' => $this->disabled,
            'created_at' => $this->getCreatedAtFormatted(),
            'updated_at' => $this->getUpdatedAtFormatted(),
            'created_by_id' => $this->getCreatorId(),
            'updated_by_id' => $this->getUpdaterId(),
        ];
    }
}