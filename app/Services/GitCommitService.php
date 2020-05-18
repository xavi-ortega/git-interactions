<?php

namespace App\Services;

use App\Helpers\GitParser;
use Carbon\Carbon;
use Exception;

class GitCommitService
{
    public function process(string $patches)
    {
        $parser = new GitParser($patches);

        $commits = [];

        while ($parser->hasNext()) {
            $commits[] = (object) [
                'id' => $this->processHash($parser),
                'author' => $this->processAuthor($parser),
                'date' => $this->processDate($parser),
                'diffs' => $this->processDiffs($parser)
            ];
        }
    }

    private function processHash(GitParser $parser): string
    {
        $hashes = preg_split(" ", $parser->expect('string', 'commit ')->acceptUntil('string', '\n'));

        return $hashes[0];
    }

    private function processAuthor(GitParser $parser): string
    {
        return $parser
            ->skipUntil('string', 'Author: ')
            ->expect('string', 'Author: ')
            ->acceptUntil('string', '\n');
    }

    private function processDate(GitParser $parser): Carbon
    {
        $timeString = $parser
            ->skip('string', '\n')
            ->expect('string', 'Date:')
            ->skipSpaces()
            ->acceptUntil('string', '\n');

        return Carbon::make($timeString);
    }

    private function processFileDiff(GitParser $parser)
    {
        [$oldFile, $newFile] = array_map(function ($file) {
            return mb_substr($file, 2);
        }, preg_split(' ', $parser->expect('string', 'diff --git ')->acceptUntil('string', '\n')));

        if ($oldFile !== '/dev/null' && $oldFile !== $newFile) {
            // file deleted $oldFile
        }

        if ($newFile === '/dev/null') {
            // file deleted $oldFile
        }

        $matches = [];
        preg_match(
            '/@@ -(\d+),(\d+) \+(\d+),(\d+) @@[^\n]*\n/',
            $parser->skipUntil('string', '\n@@ -')->skip('string', '\n')->accept('regexp', '/@@[^\n]*\n/'),
            $matches
        );

        [, $oldStart,, $newStart] = $matches;

        [$oldStart, $newStart] = [+$oldStart, +$newStart];

        return (object) [
            'oldFile' => $oldFile,
            'newFile' => $newFile,
            'oldStart' => $oldStart,
            'newStart' => $newStart
        ];
    }

    private function processDiff(GitParser $parser)
    {
        if ($parser->peek('string', 'diff --git')) {
            return $this->processFileDiff($parser);
        }

        if ($parser->peek('string', 'diff --combined')) {
            // processMergeDiff
        }

        throw new Exception("Unknown diff " + $parser->toString());
    }

    private function processDiffs(GitParser $parser)
    {
        $parser->skipUntil('string', '\ndiff ');

        $diffs = [];

        while ($parser->skip('regexp', '/\n*/')->peek('regexp', '/^diff/')) {
            $diff = $parser->acceptUntil('regexp', '/\n+(diff|commit)/');
            $diffs[] = $this->processDiff(new GitParser($diff));
        }

        $parser->skip('regexp', '/\n*/');

        return $diffs;
    }
}
