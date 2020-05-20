<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ParseEndedException;

class GitParser
{
    private $file;
    private $pointer;

    public function __construct(string $path)
    {
        $this->file = fopen($path, 'r');
        $this->pointer = !feof($this->file) ? fgets($this->file) : null;
    }

    /**
     * Check if current line contains matches
     *
     * @param array $matches
     * @return bool
     */
    public function contains(string ...$matches): bool
    {
        return Str::of($this->pointer)->contains($matches);
    }

    /**
     * Gets line and points to the next one
     *
     * @return Illuminate\Support\Stringable;
     */
    public function getLine(): Stringable
    {
        $line = Str::of($this->pointer);

        $this->skipLine();

        return $line;
    }

    /**
     * Skips the line and points to the next one
     *
     * @param integer $total
     * @return GitParser
     */
    public function skipLine(int $total = 1): GitParser
    {
        while (!feof($this->file) && $total > 0) {
            $this->pointer = fgets($this->file);
            $total--;
        }

        return $this;
    }

    /**
     * Skips the line and points the first match
     *
     * @param array $matches
     * @return GitParser
     */
    public function skipUntilContains(string ...$matches): GitParser
    {
        while (!feof($this->file)) {
            if (Str::contains($this->pointer, $matches)) {
                return $this;
            }

            $this->pointer = fgets($this->file);
        }

        throw new ParseEndedException("End of file reached when skiping");
    }

    /**
     * Skips the line and points the first match
     *
     * @param array $matches
     * @return GitParser
     */
    public function skipUntilStartsWith(string ...$matches): GitParser
    {
        while (!feof($this->file)) {
            if (Str::startsWith($this->pointer, $matches)) {
                return $this;
            }

            $this->pointer = fgets($this->file);
        }

        throw new ParseEndedException("End of file reached when skiping");
    }

    /**
     * Check wether the parser has more lines
     *
     * @return boolean
     */
    public function hasNext(): bool
    {
        return !feof($this->file);
    }
}
