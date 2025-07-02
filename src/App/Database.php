<?php

namespace Miakiwi\Kwlnk\App;

use Medoo\Medoo;
use Miakiwi\Kwlnk\Models\Account;
use Miakiwi\Kwlnk\Models\Link;
use Miakiwi\Kwlnk\Models\Token;




class Database
{
    private function __construct()
    {
        // Prevent direct instantiation.
    }



    /**
     * Get a Medoo database connection.
     * @return Medoo The Medoo database connection instance.
     */
    public static function getConnection(): Medoo
    {
        Logger::get()->debug("Creating new database connection.");



        return new Medoo([
            'type' => $_ENV['DATABASE_TYPE'] ?? null,
            'host' => $_ENV['DATABASE_HOST'] ?? null,
            'database' => $_ENV['DATABASE_NAME'] ?? null,
            'username' => $_ENV['DATABASE_USER'] ?? null,
            'password' => $_ENV['DATABASE_PASSWORD'] ?? null
        ]);
    }



    public static function setup(): void
    {
        Logger::get()->debug("Creating new database connection.");



        // Get the database connection.
        $database = self::getConnection();



        // Create the users table.
        Logger::get()->debug("Creating table.", [
            'table' => Account::table()
        ]);

        $database->drop(Account::table());

        $database->create(Account::table(), [
            'id' => [
                'VARCHAR',
                'NOT NULL',
                'PRIMARY KEY',
                'UNIQUE'
            ],
            'password' => [
                'VARCHAR',
                'NOT NULL'
            ],
            'disabled' => [
                'BOOLEAN',
                'NOT NULL',
                'DEFAULT 0'
            ],
            'created_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'created_by_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ],
            'updated_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'updated_by_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ]
        ]);



        // Create the tokens table.
        Logger::get()->debug("Creating table.", [
            'table' => Token::table()
        ]);

        $database->drop(Token::table());

        $database->create(Token::table(), [
            'id' => [
                'VARCHAR',
                'NOT NULL',
                'PRIMARY KEY'
            ],
            'account_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ],
            'expires_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'last_used_at' => [
                'DATETIME'
            ],
            'created_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'created_by_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ]
        ]);



        // Create the links table.
        Logger::get()->debug("Creating table.", [
            'table' => Link::table()
        ]);

        $database->drop(Link::table());

        $database->create(Link::table(), [
            'key' => [
                'VARCHAR',
                'NOT NULL',
                'PRIMARY KEY'
            ],
            'uri' => [
                'TEXT',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ],
            'expires_at' => [
                'DATETIME',
                'NULL'
            ],
            'created_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'created_by_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ],
            'updated_at' => [
                'DATETIME',
                'NOT NULL'
            ],
            'updated_by_id' => [
                'VARCHAR',
                'NOT NULL',
                'REFERENCES ' . Account::table() . '(id) ON DELETE SET NULL ON UPDATE CASCADE'
            ]
        ]);



        // Create the default admin user.
        Logger::get()->debug("Creating default administrator account.");

        $database->insert(Account::table(), [
            'id' => 'default_administrator',
            'password' => password_hash('kiwis are birds but also fruits and people for some reason?', PASSWORD_DEFAULT),
            'disabled' => false,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'created_by_id' => 'default_administrator',
            'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'updated_by_id' => 'default_administrator'
        ]);
    }
}