<?php

namespace Miakiwi\Kwlnk\App;

use Miakiwi\Kwlnk\Exceptions\LinkKeyAlreadyExistsException;
use Miakiwi\Kwlnk\Models\Link;



class LinkKeyGenerator
{
    /**
     * The length of the generated key.
     * @var int
     */
    protected int $length = 8;

    /**
     * The characters used to generate the key.
     * @var string
     */
    protected string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';



    /**
     * Instantiates a new LinkKeyGenerator.
     * @param int $length The length of the generated keys.
     * @param string $characters The characters used to generate the keys.
     */
    public function __construct(int $length = null, string $characters = null)
    {
        $this->setLength($length ?? Config::get('link.default_key_length', 8));
        $this->setCharacters($characters ?? Config::get('link.default_key_characters', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'));
    }



    /**
     * Sets the desired length of the generated key.
     * @param int $length The desired length of the key.
     * @throws \InvalidArgumentException if the length is less than 1.
     * @return void
     */
    public function setLength(int $length): void
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be at least 1.');
        }

        $this->length = $length;
    }



    /**
     * Sets the characters used to generate the key.
     * @param string|array $characters The characters to use for generating the key.
     * @throws \InvalidArgumentException if there are no characters provided.
     * @return void
     */
    public function setCharacters(string|array $characters): void
    {
        if (is_array($characters)) {
            $characters = implode('', $characters);
        }

        if (empty($characters)) {
            throw new \InvalidArgumentException('Characters cannot be empty.');
        }

        $this->characters = $characters;
    }



    public function generate(?string $override = null): string
    {
        // If an override is provided, use it as the key
        if ($override !== null && !empty($override)) {
            $key = $override;
        } else {
            // Generate a random key
            $key = '';

            $charactersLength = strlen($this->characters);

            for ($i = 0; $i < $this->length; $i++) {
                $key .= $this->characters[random_int(0, $charactersLength - 1)];
            }
        }



        // Ensure the key is unique by checking against existing keys
        if (Link::find($key) !== null) {
            throw new LinkKeyAlreadyExistsException();
        }



        // Return the generated key
        return $key;
    }
}