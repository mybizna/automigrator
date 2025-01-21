<?php
namespace Mybizna\Automigrator\Commands;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;

class TableComparator
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection([
            'dbname'   => env('DB_DATABASE'),
            'user'     => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'host'     => env('DB_HOST'),
            'driver'   => 'pdo_mysql',
        ]);
    }

    public function compareTables(string $mainTable, string $tmpTable): bool
    {
        // Get schema manager from the connection
        $schemaManager = $this->connection->createSchemaManager();
        $platform      = $this->connection->getDatabasePlatform();

        $modelTableDetails = $schemaManager->introspectTable($mainTable);
        $tempTableDetails  = $schemaManager->introspectTable($tmpTable);
        
        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($modelTableDetails, $tempTableDetails);

        if ($diff) {
            $schemaManager->alterTable($diff);

            return true; // Return true if there were changes and they were applied
        }

        return false; // Return false if no changes were found
    }

}
