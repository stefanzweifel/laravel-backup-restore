<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Exceptions\DumpIsNotRestorable;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\PendingRestore;

class VerifyDumpsAction
{
    /**
     * How much of a dump is read when looking for SQL statements. A dump
     * larger than this is assumed to be a real dump and is not checked.
     */
    protected const BYTES_TO_INSPECT = 1048576;

    /**
     * Find the database dumps in the decompressed backup.
     *
     * Pass $verifyContent to also check that every dump holds something worth
     * importing. The restore command does that before --reset drops tables,
     * so an empty or truncated dump does not cost the operator their data.
     *
     * @return Collection<int, string>
     *
     * @throws NoDatabaseDumpsFound
     * @throws DumpIsNotRestorable
     */
    public function execute(PendingRestore $pendingRestore, bool $verifyContent = false): Collection
    {
        $dumps = $pendingRestore->getAvailableDbDumps();

        if ($dumps->isEmpty()) {
            throw NoDatabaseDumpsFound::notFoundInBackup($pendingRestore);
        }

        if ($verifyContent) {
            $dumps->each(fn (string $dump) => $this->verifyDump($pendingRestore, $dump));
        }

        return $dumps;
    }

    /**
     * @throws DumpIsNotRestorable
     */
    protected function verifyDump(PendingRestore $pendingRestore, string $dump): void
    {
        $path = Storage::disk($pendingRestore->restoreDisk)->path($dump);
        $name = basename($dump);

        if (filesize($path) === 0) {
            throw DumpIsNotRestorable::isEmpty($name);
        }

        $head = $this->readHead($path);

        // A binary pg_dump holds no readable statements, and a dump larger
        // than BYTES_TO_INSPECT is not worth scanning further.
        if ($head === null || strlen($head) >= static::BYTES_TO_INSPECT) {
            return;
        }

        if (preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|COPY\s+.+\s+FROM\s+stdin)\b/i', $head) !== 1) {
            throw DumpIsNotRestorable::containsNoStatements($name);
        }
    }

    /**
     * Read the start of a dump, decompressing it if needed. Returns null for
     * formats whose contents cannot be read as text.
     */
    protected function readHead(string $path): ?string
    {
        $head = match (true) {
            str_ends_with($path, '.sql.gz') => $this->readWith('gzopen', 'gzread', 'gzclose', $path),
            str_ends_with($path, '.sql.bz2') => $this->readWith('bzopen', 'bzread', 'bzclose', $path),
            str_ends_with($path, '.sql') => file_get_contents($path, length: static::BYTES_TO_INSPECT),
            default => null,
        };

        return $head === false ? null : $head;
    }

    protected function readWith(string $open, string $read, string $close, string $path): string|false
    {
        if (! function_exists($open)) {
            return false;
        }

        $handle = $open($path, 'r');

        if ($handle === false) {
            return false;
        }

        $contents = $read($handle, static::BYTES_TO_INSPECT);

        $close($handle);

        return $contents;
    }
}
