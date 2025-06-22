<?php

namespace Miakiwi\Kwlnk\App;



class Config
{
    /**
     * Singleton instance of the Config class.
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Configuration settings.
     * @var array
     */
    private array $settings = [];



    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
    }



    /**
     * Get the singleton instance of the Config class.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }



        return self::$instance;
    }



    /**
     * Set a configuration value.
     * @param string $key The configuration key.
     * @param mixed $value The configuration value.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        // Get the config
        $config = self::instance();



        // Set the value in the config
        $config->settings[$key] = $value;
    }



    /**
     * Get a configuration value or return a default value if it does not exist.
     * @param string $key The configuration key.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The configuration value or the default value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Get the config
        $config = self::instance();



        // Return the value if it exists, otherwise return the default value
        return $config->settings[$key] ?? $default;
    }



    /**
     * Get all configuration settings.
     * @return array The configuration settings.
     */
    public static function settings(): array
    {
        // Get the config
        $config = self::instance();



        // Return the settings
        return $config->settings;
    }



    /**
     * Load configuration from a JSON file.
     * @param string $file The path to the configuration file.
     * @throws \RuntimeException if the file does not exist or if there is an error decoding the JSON.
     * @return void
     */
    public static function load(string $file): void
    {
        // Check if the file exists
        if (!file_exists($file)) {
            throw new \RuntimeException("Configuration file '$file' does not exist.");
        }



        // Assume the file is JSON and decode it
        $json_data = file_get_contents($file);

        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Error decoding JSON from configuration file '$file': " . json_last_error_msg());
        }



        // Set the configuration values
        foreach ($data as $key => $value) {
            self::set($key, $value);
        }
    }
}