<?php
// Script to manually add columns to stock_outs table bypassing broken artisan
// Host, User and Password taken from .env

$host = 'db'; // Docker service name
$db   = 'apex_pos';
$user = 'root';
$pass = 'password';

try {
     // If 'db' isn't reachable, try localhost?
     // Many Docker dev environments on Windows use localhost:port mapping.
     // But let's try 'db' first since .env says so.
     $dsn = "pgsql:host=$host;port=5432;dbname=$db";
     $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

     echo "Connected to DB\n";

     $queries = [
         "ALTER TABLE stock_outs ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL",
         "ALTER TABLE stock_outs ADD COLUMN IF NOT EXISTS cancelled_by BIGINT NULL",
         "ALTER TABLE stock_outs ADD COLUMN IF NOT EXISTS cancel_reason TEXT NULL"
     ];

     foreach ($queries as $query) {
         $pdo->exec($query);
         echo "Executed: $query\n";
     }

     echo "Migration (Manual) DONE.\n";
} catch (PDOException $e) {
     echo "PDO Error: " . $e->getMessage() . "\n";
     
     // Retry with localhost just in case
     if ($host === 'db') {
         echo "Retrying with localhost...\n";
         try {
             $dsn = "pgsql:host=localhost;port=5432;dbname=$db";
             $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
             foreach ($queries as $query) {
                 $pdo->exec($query);
                 echo "Executed: $query\n";
             }
             echo "Migration (Localhost) DONE.\n";
         } catch (PDOException $e2) {
             echo "PDO Error 2: " . $e2->getMessage() . "\n";
         }
     }
}
