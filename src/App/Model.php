<?php

namespace Miakiwi\Kwlnk\App;

use Miakiwi\Kwlnk\Exceptions\ModelLoadException;



abstract class Model implements IModel
{
    /**
     * The database table column used as the unique identifier for the model.
     * @var string
     */
    protected static string $db_id_column = 'id';

    /**
     * The name of the table associated with the model.
     * @var string
     */
    public static function table(): string
    {
        $parts = explode('\\', static::class);

        return strtolower(end($parts)) . 's';
    }



    /**
     * Get all items in the model.
     * @return static[] An array of all items in the model.
     */
    public static function all(): array
    {
        Logger::get()->debug("Fetching all records", [
            'table' => self::table()
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        // Fetch all records from the table.
        $records = $database->select(self::table(), '*');



        $results = [];

        foreach ($records as $record) {
            // Instantiate the model with the record data.
            try {

                $results[] = static::load($record);

            } catch (ModelLoadException $e) {

                Logger::get()->error("Failed to load record.", [
                    'table' => self::table(),
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            } catch (\Exception $e) {

                Logger::get()->error("Error loading record.", [
                    'table' => self::table(),
                    'record' => $record,
                    'error' => $e->getMessage()
                ]);

            }

        }



        return $results;
    }



    /**
     * Get a single item by its ID.
     * @param string $id The ID of the item to retrieve.
     * @return static|null Returns the item if found, null otherwise.
     */
    public static function find(string $id): ?self
    {
        Logger::get()->debug("Fetching record.", [
            'table' => self::table(),
            'id_column' => self::$db_id_column,
            'id' => $id
        ]);



        // Create a new database connection.
        $database = Database::getConnection();



        // Fetch the record from the table.
        try {

            return static::load($database->get(self::table(), '*', [
                self::$db_id_column => $id
            ]) ?? []);

        } catch (ModelLoadException $e) {

            Logger::get()->error("Failed to load record.", [
                'table' => self::table(),
                'id_column' => self::$db_id_column,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return null;

        } catch (\Exception $e) {

            Logger::get()->error("Error fetching record.", [
                'table' => self::table(),
                'id_column' => self::$db_id_column,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return null;

        }
    }
}