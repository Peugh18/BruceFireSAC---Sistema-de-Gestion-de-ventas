<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;

#[Signature('backup:bd')]
#[Description('Crea una copia de la base de datos y conserva las 14 más recientes')]
class BackupDatabase extends Command
{
    public function handle(Filesystem $files): int
    {
        $directory = storage_path('app/backups');
        $files->ensureDirectoryExists($directory);
        $connection = (string) config('database.default');
        $configuration = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d_His');

        if (($configuration['driver'] ?? null) === 'mysql') {
            $destination = "{$directory}/bd_{$timestamp}.sql";
            $process = new Process(['mysqldump', '--single-transaction', '--host='.$configuration['host'], '--port='.(string) $configuration['port'], '--user='.$configuration['username'], (string) $configuration['database']]);
            $process->setEnv(['MYSQL_PWD' => (string) ($configuration['password'] ?? '')]);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump no está disponible o falló: '.$process->getErrorOutput());
            }
            $files->put($destination, $process->getOutput());
        } else {
            throw new RuntimeException("El respaldo automático no soporta el driver {$connection}.");
        }

        collect($files->files($directory))->sortByDesc(fn ($file) => $file->getMTime())->slice(14)->each(fn ($file) => $files->delete($file->getPathname()));
        $this->info("Respaldo creado en {$directory}.");

        return self::SUCCESS;
    }
}
