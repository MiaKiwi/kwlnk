<?php

namespace Miakiwi\Kwlnk\Controllers;

use Exception;
use MiaKiwi\Kaphpir\ApiResponse\HttpApiResponse;
use MiaKiwi\Kaphpir\Responses\v25_1_0\Response;
use MiaKiwi\Kaphpir\ResponseSerializer\JsonSerializer;
use Miakiwi\Kwlnk\App\Config;
use Miakiwi\Kwlnk\App\LinkKeyGenerator;
use Miakiwi\Kwlnk\App\Logger;
use Miakiwi\Kwlnk\App\SecurityContext;
use Miakiwi\Kwlnk\Exceptions\ExpiredLinkError;
use Miakiwi\Kwlnk\Exceptions\FailedToGenerateLinkKeyError;
use Miakiwi\Kwlnk\Exceptions\InvalidFieldsError;
use Miakiwi\Kwlnk\Exceptions\InvalidPaginationParametersError;
use Miakiwi\Kwlnk\Exceptions\LinkNotFoundError;
use Miakiwi\Kwlnk\Exceptions\ResourceCreationError;
use Miakiwi\Kwlnk\Exceptions\ResourceDestructionError;
use Miakiwi\Kwlnk\Exceptions\ResourceUpdateError;
use Miakiwi\Kwlnk\Models\Link;
use Pecee\SimpleRouter\SimpleRouter;
use Rakit\Validation\Validator;



class LinkController
{
    /**
     * Handles the request to show all links.
     * @return never
     */
    public function index(): never
    {
        $links = Link::all();



        // Get pagination parameters
        $get = array_change_key_case($_GET, CASE_LOWER);

        $page = isset($get['page']) ? (int) $get['page'] : 1;
        $rows = max(
            1,
            isset($get['rows']) ? (int) $get['rows'] : count($links)
        ); // Default to all links if rows not specified

        $pages = ceil(count($links) / $rows); // Calculate total pages



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



        // Paginate the links
        $paginated_links = array_slice(
            $links,
            ($page - 1) * $rows,
            $rows
        );



        // Get the pagination metadata
        $pagination = [
            'pagination' => [
                'total' => count($links),
                'page' => $page,
                'per_page' => $rows,
                'total_pages' => $pages,
            ]
        ];



        // Get the KAPIR data of the links
        $data = array_map(function (Link $link) {
            return $link->getKapirValue();
        }, $paginated_links);



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->metadata($pagination)->message('Links retrieved successfully.')
        );

        die();
    }



    /**
     * Handles the request to show a specific link by key.
     * @param string $key The key of the link to retrieve.
     * @return never
     */
    public function show(string $key): never
    {
        // Find the link with this key
        $link = Link::find($key);



        if ($link === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new LinkNotFoundError($key))->message('Link not found.')
            );

        } else {

            // Get the KAPIR data of the link
            $data = $link->getKapirValue();

            // Send the response
            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->data($data)
            );

        }

        die();
    }



    /**
     * Handles the request to create a new link.
     * @return never
     */
    public function store(): never
    {
        $input = SimpleRouter::request()->getInputHandler()->all();



        Logger::get()->info("Creating new link", [
            'input' => $input
        ]);



        // Validate the input data
        $validation = (new Validator())->validate($input, [
            'uri' => 'required|url',
        ]);

        if ($validation->fails()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError($validation->errors()))->message('Invalid input data.')
            );

            die();
        }



        // If a key is provided, check if it already exists
        if (isset($input['key']) && Link::find($input['key']) !== null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError([
                    'Key must be unique'
                ]))->message('Link already exists.')
            );

            die();

        }



        // If no key is provided, generate a new one
        if (!isset($input['key']) || empty($input['key'])) {

            // Generate a new key using the LinkKeyGenerator
            $key_generator = new LinkKeyGenerator();

            // The maximum number of attempts to generate a unique key
            $unique_key_attempts_remaining = Config::get('link.max_key_generation_attempts', 10);

            do {
                $key = $key_generator->generate();

                // Check if the generated key already exists
                $existing_link = Link::find($key);

                if ($existing_link === null) {
                    // If the key is unique, break the loop
                    $unique_key_attempts_remaining = -1;
                    break;
                }
            } while (--$unique_key_attempts_remaining > 0);

            if ($unique_key_attempts_remaining !== -1) {

                Logger::get()->error("Failed to generate a unique link key after multiple attempts.", [
                    'attempts_count' => Config::get('link.max_key_generation_attempts', 10),
                    'last_attempted_key' => $key,
                    'input' => $input
                ]);

                HttpApiResponse::send(
                    JsonSerializer::getInstance(),
                    (new Response())->error(new FailedToGenerateLinkKeyError())->message('Failed to create link.')
                );

                die();

            }

        } else {

            $key = $input['key'];

        }



        try {

            // Create the link
            $link = new Link(
                $key,
                $input['uri']
            );

            // Set the updater and creator IDs
            $link->setUpdaterId(SecurityContext::Id());
            $link->setCreatorId(SecurityContext::Id());

            // Save the link to the database
            $link->save();

        } catch (Exception $e) {

            Logger::get()->error("Failed to create link.", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceCreationError('link'))->message('Failed to create link.')
            );

            die();

        }



        // Get the KAPIR data of the link
        $data = $link->getKapirValue();



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->message('Link created successfully.')
        );

        die();
    }



    /**
     * Handles the request to update an existing link by key.
     * @param string $key The key of the link to update.
     * @return never
     */
    public function update(string $key): never
    {
        // Find the link with this key
        $link = Link::find($key);

        if ($link === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new LinkNotFoundError($key))->message('Link not found.')
            );

            die();
        }



        // Get the input data
        $input = SimpleRouter::request()->getInputHandler()->all();



        // Validate the input data
        $validation = (new Validator())->validate($input, [
            'uri' => 'url',
            'expires_at' => 'nullable|date:Y-m-d H:i:s'

        ]);

        if ($validation->fails()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new InvalidFieldsError($validation->errors()))->message('Invalid input data.')
            );

            die();
        }



        // Update the link with the input data
        $link->setUri($input['uri'] ?? $link->getUri());
        $link->setExpiryDate($input['expires_at'] ?? $link->getExpiresAt());

        $link->update();



        // Save the link to the database
        try {

            $link->save();

        } catch (Exception $e) {

            Logger::get()->error("Failed to update link.", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceUpdateError('link'))->message('Failed to update link.')
            );

            die();
        }



        // Get the KAPIR data of the link
        $data = $link->getKapirValue();



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->data($data)->message('Link updated successfully.')
        );

        die();
    }



    /**
     * Handles the request to delete a link by its key.
     * @param string $key The key of the link to delete.
     * @return never
     */
    public function destroy(string $key): never
    {
        Logger::get()->info("Deleting link", [
            'key' => $key
        ]);



        // Find the link with this key
        $link = Link::find($key);

        if ($link === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new LinkNotFoundError($key))->message('Link not found.')
            );

            die();
        }



        // Delete the link
        try {

            $link->delete();

        } catch (Exception $e) {

            Logger::get()->error("Failed to delete link.", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ResourceDestructionError('link'))->message('Failed to delete link.')
            );

            die();
        }



        // Send the response
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            (new Response())->success()->message('Link deleted successfully.')
        );

        die();
    }



    /**
     * Redirects to the URI the link of a given key points to.
     * @param string $key The key of the link.
     * @return never
     */
    public function redirect(string $key): never
    {
        // Find the link with this key
        $link = Link::find($key);



        if ($link === null) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new LinkNotFoundError($key))->message('Link not found.')
            );

            die();

        } elseif ($link->isExpired()) {

            HttpApiResponse::send(
                JsonSerializer::getInstance(),
                (new Response())->error(new ExpiredLinkError())->message('Link has expired.')
            );

            die();

        }



        // Redirect to the link's URI
        HttpApiResponse::send(
            JsonSerializer::getInstance(),
            new Response(),
            httpStatus: 301,
            headers: [
                'Location' => $link->getUri(),
                'X-Redirected-By' => 'KwLnk',
                'X-Redirect-Key' => $key
            ]
        );

        die();
    }
}