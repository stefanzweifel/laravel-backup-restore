<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Support;

use Generator;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\DumpContainsMetaCommand;

/**
 * psql reads backslash meta-commands from stdin whether or not stdin is a
 * terminal, and `\!` runs a shell command. There is no psql flag that turns
 * that off, so a dump from a disk an attacker can write to is a shell script.
 *
 * This scanner passes the dump through line by line and refuses any
 * meta-command that pg_dump does not itself emit.
 */
class PsqlMetaCommandScanner
{
    /**
     * The meta-commands real dumps contain. `\.` terminates COPY data,
     * `\connect` comes from pg_dump --create and pg_dumpall, and
     * `\restrict` / `\unrestrict` are emitted by pg_dump 18 and later.
     *
     * @var array<int, string>
     */
    public const array ALLOWED_META_COMMANDS = [
        '\.',
        '\c',
        '\connect',
        '\restrict',
        '\unrestrict',
    ];

    protected bool $inCopyData = false;

    protected bool $inString = false;

    protected bool $stringUsesBackslashEscapes = false;

    protected ?string $dollarQuoteTag = null;

    protected int $blockCommentDepth = 0;

    /**
     * Yields the dump one line at a time so the whole file is never held in
     * memory.
     *
     * @param  resource  $stream
     * @return Generator<int, string>
     *
     * @throws DumpContainsMetaCommand
     */
    public function pipe($stream): Generator
    {
        $lineNumber = 0;

        try {
            while (($line = fgets($stream)) !== false) {
                $lineNumber++;

                if ($this->inCopyData) {
                    if (rtrim($line, "\r\n") === '\.') {
                        $this->inCopyData = false;
                    }

                    yield $line;

                    continue;
                }

                $this->consume($line, $lineNumber);

                if ($this->atTopLevel() && $this->startsCopyData($line)) {
                    $this->inCopyData = true;
                }

                yield $line;
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    protected function atTopLevel(): bool
    {
        return ! $this->inString
            && $this->dollarQuoteTag === null
            && $this->blockCommentDepth === 0;
    }

    /**
     * psql starts a meta-command at an unquoted backslash anywhere, not only at
     * the start of a line and not only after a semicolon. `SELECT 1; \! touch x`
     * and `SELECT 1 /* c *\/ \! touch x` both run the shell command.
     *
     * @throws DumpContainsMetaCommand
     */
    protected function guardAgainstMetaCommand(string $line, int $offset, int $lineNumber): void
    {
        preg_match('/^\\\\\S*/', substr($line, $offset), $matches);

        $metaCommand = $matches[0] ?? '\\';

        if (in_array($metaCommand, static::ALLOWED_META_COMMANDS, true)) {
            return;
        }

        throw DumpContainsMetaCommand::found($lineNumber, $metaCommand);
    }

    protected function startsCopyData(string $line): bool
    {
        return preg_match('/^\s*COPY\s.*\sFROM\s+stdin\s*;\s*$/i', $line) === 1;
    }

    /**
     * Walks the line, carrying string, dollar-quote and comment state across
     * lines, and refuses a meta-command at any position where that state is
     * empty. A backslash inside a value, a function body, a comment or COPY
     * data is data and is left alone.
     *
     * @throws DumpContainsMetaCommand
     */
    protected function consume(string $line, int $lineNumber): void
    {
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            $next = $line[$i + 1] ?? '';

            if ($this->blockCommentDepth > 0) {
                if ($char === '*' && $next === '/') {
                    $this->blockCommentDepth--;
                    $i++;
                } elseif ($char === '/' && $next === '*') {
                    $this->blockCommentDepth++;
                    $i++;
                }

                continue;
            }

            if ($this->dollarQuoteTag !== null) {
                if ($char === '$' && substr($line, $i, strlen($this->dollarQuoteTag)) === $this->dollarQuoteTag) {
                    $i += strlen($this->dollarQuoteTag) - 1;
                    $this->dollarQuoteTag = null;
                }

                continue;
            }

            if ($this->inString) {
                if ($this->stringUsesBackslashEscapes && $char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === "'") {
                    if ($next === "'") {
                        $i++;

                        continue;
                    }

                    $this->inString = false;
                }

                continue;
            }

            // A -- comment runs to the end of the line, and psql does not read a
            // meta-command inside one.
            if ($char === '-' && $next === '-') {
                return;
            }

            if ($char === '\\') {
                $this->guardAgainstMetaCommand($line, $i, $lineNumber);

                // psql reads the rest of the line as the meta-command's
                // arguments, so nothing after it changes the state we carry.
                return;
            }

            if ($char === '/' && $next === '*') {
                $this->blockCommentDepth = 1;
                $i++;

                continue;
            }

            if ($char === "'") {
                $previous = $i > 0 ? $line[$i - 1] : '';
                $this->inString = true;
                $this->stringUsesBackslashEscapes = $previous === 'E' || $previous === 'e';

                continue;
            }

            if ($char === '$' && preg_match('/^(\$\$|\$[A-Za-z_\x80-\xff][A-Za-z_0-9\x80-\xff]*\$)/', substr($line, $i), $matches) === 1) {
                $this->dollarQuoteTag = $matches[1];
                $i += strlen($matches[1]) - 1;
            }
        }
    }
}
