<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;

class GitParser
{
    private $file;

    public function __construct(string $path)
    {
        $this->file = fopen($path, 'r');
    }

    /**
     * Gets line and point to the next one
     *
     * @return void
     */
    public function getLine()
    {
        if (feof($this->file)) {
            return null;
        }

        return fgets($this->file);
    }

    /**
     * Skips line and point to the next one
     *
     * @return void
     */
    public function skipLine(int $total)
    {
        while ($total > 0) {
            $this->getLine();
            $total--;
        }
    }
}
