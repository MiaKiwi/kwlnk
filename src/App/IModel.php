<?php

namespace Miakiwi\Kwlnk\App;



interface IModel
{
    /**
     * Get all items in the model.
     * @return static[] An array of all items in the model.
     */
    public static function all(): array;



    /**
     * Get a single item by its ID.
     * @param string $id The ID of the item to retrieve.
     * @return static|null Returns the item if found, null otherwise.
     */
    public static function find(string $id): ?self;



    /**
     * Save or update the object in the database.
     * @param array $options Optional parameters for saving the object.
     * @return void
     */
    public function save(array $options = []): void;



    /**
     * Instantiate the object from an array of data.
     * @param array $data An associative array containing the data to populate the object.
     * @throws \Miakiwi\Kwlnk\Exceptions\ModelLoadException If the data is invalid or missing required fields.
     * @return void
     */
    public static function load(array $data): self;



    /**
     * Delete the object from the database.
     * @param array $options Optional parameters for deleting the object.
     * @return void
     */
    public function delete(array $options = []): void;
}