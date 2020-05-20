<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Helpers\GitParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

const NEW_LINE = '/\r\n|\r|\n/';

class GitCommitService
{
    public function process(string $patches): Collection
    {
        Log::debug('Start processing');
        $parser = new GitParser(trim($patches));

        $commits = collect();

        while ($parser->hasNext()) {
            Log::info('Processing id');
            $id = $this->processHash($parser);

            Log::info('Commit: ' . $id);

            $commits->put($id, (object) [
                'id' => $id,
                'author' => $this->processAuthor($parser),
                'date' => $this->processDate($parser),
                'diffs' => $this->processDiffs($parser)
            ]);
        }

        return $commits;
    }

    private function processHash(GitParser $parser): string
    {
        $hashes = explode(" ", $parser->expect('string', 'commit ')->acceptUntil('regexp', NEW_LINE));

        return $hashes[0];
    }

    private function processAuthor(GitParser $parser): string
    {
        $author = trim($parser
            ->skipUntil('string', 'Author: ')
            ->skip('string', 'Author: ')
            ->acceptUntil('regexp', NEW_LINE));

        Log::info('Author: ' . $author);

        return  explode(" >", $author)[0];
    }

    private function processDate(GitParser $parser): Carbon
    {
        $timeString = trim($parser
            ->skipUntil('string', 'Date: ')
            ->expect('string', 'Date: ')
            ->acceptUntil('regexp', NEW_LINE));

        Log::info('Date: ' . $timeString);

        while (true) {
            try {
                Log::info('Try conversion: ' . $timeString);
                return Carbon::make($timeString);
            } catch (Exception $e) {
                $timeString = mb_substr($timeString, 1);

                if (mb_strlen($timeString) <= 0) {
                    throw new Exception("Date KO at " . $parser->toString());
                }
            }
        }
    }

    private function processFileDiff(GitParser $parser)
    {
        // [$oldFile, $newFile] = array_map(function ($file) {
        //     return mb_substr($file, 2);
        // }, explode(' ', $parser->expect('string', 'diff --git ')->acceptUntil('regexp', NEW_LINE)));
        try {
            $oldFile = trim($parser
                ->skipUntil('string', '---')
                ->expect('string', '---')
                ->acceptUntil('regexp', NEW_LINE));


            $newFile = trim($parser
                ->skipUntil('string', '+++')
                ->expect('string', '+++')
                ->acceptUntil('regexp', NEW_LINE));
        } catch (Exception $e) {
            dd('No files', $parser->text);
        }

        if ($oldFile !== '/dev/null' && $oldFile !== $newFile) {
            // file renamed $oldFile
        }

        if ($newFile === '/dev/null') {
            // file deleted $oldFile
        }

        // $matches = [];
        // try {
        $diffData = $parser->skipUntil('string', '@@ -')->accept('regexp', '/@@[^\n]*\n/');
        // } catch (Exception $e) {
        //     dd($parser->skipUntil('string', '@@ -')->accept('regexp', '/@@[^\n]*\n/'), $parser);
        // }

        preg_match(
            '/@@ (-(\d+),(\d+) \+(\d+),(\d+)|-(\d+),(\d+) \+(\d+)) @@[^\n]*\n/',
            $diffData,
            $matches
        );

        if (count($matches) === 6) {
            [,, $oldStart, $oldEnd, $newStart, $newEnd] = $matches;
        } else if (count($matches) === 9) {
            [,,,,,, $oldStart, $oldEnd, $newStart] = $matches;
        } else {
            dd(['unexpected diffData', $diffData, $matches, $parser->toString()]);
        }

        [$oldStart, $newStart] = [+$oldStart, +$newStart];

        return (object) [
            'oldFile' => $oldFile === '/dev/null' ? 'deleted' : mb_substr($oldFile, 2),
            'newFile' => $newFile === '/dev/null' ? 'deleted' : mb_substr($newFile, 2),
            'oldStart' => $oldStart,
            'newStart' => $newStart
        ];
    }

    private function processDiff(GitParser $parser)
    {
        if ($parser->skipUntil('string', 'diff --git')->peek('string', 'diff --git')) {
            return $this->processFileDiff($parser);
        }

        if ($parser->peek('string', 'diff --combined')) {
            // processMergeDiff
        }

        dd('unkown diff', $parser->text);
        throw new Exception("Unknown diff " . $parser->toString());
    }

    private function processDiffs(GitParser $parser)
    {
        $parser->skipUntil('regexp', NEW_LINE)->skipUntil('string', 'diff ');

        $diffs = [];

        while ($parser->skipUntil('regexp', '/(diff|commit)/')->peek('string', 'diff')) {
            $diff = $parser->acceptUntil('regexp', '/(\r\n|\r|\n)+(diff|commit)/');
            $diffs[] = $this->processDiff(new GitParser(trim($diff)));
            // $parser->skip('string', 'diff');
        }

        // $parser->skip('regexp', NEW_LINE);

        return $diffs;
    }
}
