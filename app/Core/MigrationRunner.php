<?php
declare(strict_types=1);
namespace Giftcheta\Core;
use PDO;
use RuntimeException;
final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo, private readonly string $directory) {}
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, batch INT UNSIGNED NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $done = $this->pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $files = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) continue;
            $sql = trim((string) file_get_contents($file));
            if ($sql === '') throw new RuntimeException("Migration {$name} is empty.");
            if ($name === '202610180001_create_migrations_table.sql') {
                $this->record($name, $batch);
                $applied[] = $name;
                continue;
            }
            try {
                $this->pdo->exec($sql);
                $this->record($name, $batch);
                $applied[] = $name;
            } catch (\Throwable $error) {
                throw new RuntimeException("Migration {$name} failed; MySQL DDL may have partially applied. Inspect the database before retrying.", 0, $error);
            }
        }
        return $applied;
    }

    private function record(string $name, int $batch): void
    {
        $statement = $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)');
        $statement->execute(['migration' => $name, 'batch' => $batch]);
    }
}
