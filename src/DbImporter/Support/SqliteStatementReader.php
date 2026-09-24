<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Support;

use Generator;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotSetParameter;

/**
 * Splits a SQLite dump into single statements, reading the stream in chunks so
 * that only one statement is held in memory at a time.
 *
 * A semicolon ends a statement only outside string literals, quoted
 * identifiers and comments, and outside the body of a CREATE TRIGGER, which
 * contains semicolons of its own. That is the same rule sqlite3_complete()
 * applies, and it is tracked here with the same small state machine.
 */
class SqliteStatementReader
{
    public const int DEFAULT_CHUNK_SIZE = 65536;

    /** Start of a statement. */
    protected const int STATE_START = 0;

    /** Nothing of interest; a semicolon ends the statement. */
    protected const int STATE_NORMAL = 1;

    protected const int STATE_CREATE = 2;

    protected const int STATE_CREATE_TEMP = 3;

    /** Inside a trigger body, where a semicolon separates but does not end. */
    protected const int STATE_TRIGGER = 4;

    /** A semicolon inside a trigger body. */
    protected const int STATE_TRIGGER_SEMICOLON = 5;

    /** END directly after that semicolon; the next semicolon ends the body. */
    protected const int STATE_TRIGGER_END = 6;

    protected string $buffer = '';

    /** How far into the buffer the lexer has read. */
    protected int $offset = 0;

    protected int $state = self::STATE_START;

    /** The character that closes the literal or identifier being read. */
    protected ?string $closingQuote = null;

    protected bool $inLineComment = false;

    protected bool $inBlockComment = false;

    /** How much of the stream is read at a time.
     *
     * @var positive-int
     */
    protected int $chunkSize;

    public function __construct(int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if ($chunkSize < 1) {
            throw CannotSetParameter::mustBeAtLeast('chunkSize', 1, $chunkSize);
        }

        $this->chunkSize = $chunkSize;
    }

    /**
     * Yields the dump one statement at a time and closes the stream when it is
     * done with it.
     *
     * @param  resource  $stream
     * @return Generator<int, string>
     */
    public function pipe($stream): Generator
    {
        try {
            while (($chunk = fread($stream, $this->chunkSize)) !== false && $chunk !== '') {
                $this->buffer .= $chunk;

                yield from $this->drain();
            }

            // Whatever follows the last semicolon. A dump does not normally end
            // without one, but the statement is passed on rather than dropped.
            if ($this->isMeaningful($this->buffer)) {
                yield trim($this->buffer);
            }
        } finally {
            $this->reset();

            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Yields every complete statement the buffer holds and keeps the rest.
     *
     * @return Generator<int, string>
     */
    protected function drain(): Generator
    {
        while (($end = $this->findStatementEnd()) !== null) {
            $statement = substr($this->buffer, 0, $end);
            $this->buffer = substr($this->buffer, $end);
            $this->offset = 0;
            $this->state = self::STATE_START;

            if ($this->isMeaningful($statement)) {
                yield trim($statement);
            }
        }
    }

    /**
     * A statement of nothing but whitespace and semicolons is not passed on.
     */
    protected function isMeaningful(string $statement): bool
    {
        return trim($statement, " \t\n\r\0\x0B;") !== '';
    }

    /**
     * The offset just past the semicolon that ends the first statement in the
     * buffer, or null when the buffer holds no complete statement.
     *
     * The lexer picks up where the previous call left it, so a literal, a
     * comment or a keyword that straddles two chunks is still read as one.
     */
    protected function findStatementEnd(): ?int
    {
        $length = strlen($this->buffer);

        for ($i = $this->offset; $i < $length; $i++) {
            $char = $this->buffer[$i];
            $last = $i === $length - 1;

            if ($this->inLineComment) {
                if ($char === "\n") {
                    $this->inLineComment = false;
                }

                continue;
            }

            if ($this->inBlockComment) {
                if ($char !== '*') {
                    continue;
                }

                if ($last) {
                    return $this->pause($i);
                }

                // SQLite does not nest block comments.
                if ($this->buffer[$i + 1] === '/') {
                    $this->inBlockComment = false;
                    $i++;
                }

                continue;
            }

            if ($this->closingQuote !== null) {
                if ($char !== $this->closingQuote) {
                    continue;
                }

                if ($char !== ']' && $last) {
                    return $this->pause($i);
                }

                // A doubled quote stands for the character itself. Brackets
                // have no escape, so a `]` always closes the identifier.
                if ($char !== ']' && $this->buffer[$i + 1] === $char) {
                    $i++;

                    continue;
                }

                $this->closingQuote = null;

                continue;
            }

            if ($char === '-' || $char === '/') {
                if ($last) {
                    return $this->pause($i);
                }

                if ($char === '-' && $this->buffer[$i + 1] === '-') {
                    $this->inLineComment = true;
                    $i++;

                    continue;
                }

                if ($char === '/' && $this->buffer[$i + 1] === '*') {
                    $this->inBlockComment = true;
                    $i++;

                    continue;
                }
            }

            if ($char === "'" || $char === '"' || $char === '`' || $char === '[') {
                $this->closingQuote = $char === '[' ? ']' : $char;
                $this->consumeToken('');

                continue;
            }

            if ($char === ';') {
                if ($this->consumeSemicolon()) {
                    return $i + 1;
                }

                continue;
            }

            if ($this->isKeywordCharacter($char)) {
                $word = $this->readWord($i, $length);

                // The word may continue in the next chunk, and "TRIGG" must not
                // be classified before "ER" arrives.
                if ($word === null) {
                    return $this->pause($i);
                }

                $this->consumeToken(strtolower($word));
                $i += strlen($word) - 1;

                continue;
            }

            if (trim($char) !== '') {
                $this->consumeToken('');
            }
        }

        return $this->pause($length);
    }

    /**
     * Remembers how far the lexer got and reports that no statement is
     * complete yet.
     */
    protected function pause(int $offset): null
    {
        $this->offset = $offset;

        return null;
    }

    protected function isKeywordCharacter(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') || $char === '_';
    }

    /**
     * The word starting at the given offset, or null when it runs to the end of
     * the buffer and may be incomplete.
     */
    protected function readWord(int $start, int $length): ?string
    {
        $end = $start;

        while ($end < $length && $this->isKeywordCharacter($this->buffer[$end])) {
            $end++;
        }

        if ($end === $length) {
            return null;
        }

        return substr($this->buffer, $start, $end - $start);
    }

    /**
     * Advances the state machine over one token. Anything that is not a
     * keyword the machine cares about comes in as an empty string.
     */
    protected function consumeToken(string $word): void
    {
        $this->state = match ($this->state) {
            self::STATE_START => match ($word) {
                'create' => self::STATE_CREATE,
                // EXPLAIN does not begin a statement of its own, so
                // EXPLAIN CREATE TRIGGER is still read as a trigger.
                'explain' => self::STATE_START,
                default => self::STATE_NORMAL,
            },
            self::STATE_CREATE => match ($word) {
                'temp', 'temporary' => self::STATE_CREATE_TEMP,
                'trigger' => self::STATE_TRIGGER,
                default => self::STATE_NORMAL,
            },
            self::STATE_CREATE_TEMP => $word === 'trigger' ? self::STATE_TRIGGER : self::STATE_NORMAL,
            // Only an END that directly follows a semicolon closes the body.
            // An END that closes a CASE expression does not.
            self::STATE_TRIGGER_SEMICOLON => $word === 'end' ? self::STATE_TRIGGER_END : self::STATE_TRIGGER,
            self::STATE_TRIGGER_END => self::STATE_TRIGGER,
            default => $this->state,
        };
    }

    /**
     * Advances the state machine over a semicolon and reports whether it ends
     * the statement.
     */
    protected function consumeSemicolon(): bool
    {
        if ($this->state === self::STATE_TRIGGER || $this->state === self::STATE_TRIGGER_SEMICOLON) {
            $this->state = self::STATE_TRIGGER_SEMICOLON;

            return false;
        }

        return true;
    }

    protected function reset(): void
    {
        $this->buffer = '';
        $this->offset = 0;
        $this->state = self::STATE_START;
        $this->closingQuote = null;
        $this->inLineComment = false;
        $this->inBlockComment = false;
    }
}
