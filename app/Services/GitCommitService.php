<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Helpers\GitParser;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ParseEndedException;

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
        $hash = $parser->skipUntilStartsWith('commit')->getLine()->after('commit ')->trim();

        return $hash;
    }

    private function processAuthor(GitParser $parser): object
    {
        $author = $parser->skipUntilStartsWith('Author: ')->getLine()->after('Author: ')->trim();

        Log::info('Author: ' . $author);

        return (object) [
            'name' => explode(" <", $author)[0],
            'email' => explode(">", explode(" <", $author)[1])[0]
        ];
    }

    private function processDate(GitParser $parser): Carbon
    {
        $timeString = $parser->skipUntilStartsWith('Date: ')->getLine()->after('Date: ')->trim();

        Log::info('Date: ' . $timeString);

        return Carbon::make((string) $timeString);
    }

    private function processDiffs(GitParser $parser): Collection
    {
        $diffs = collect();

        $processDiff = $parser->skipUntilStartsWith('diff', 'commit')->contains('diff');

        while ($processDiff) {
            $diffs->push($this->processDiff($parser));

            try {
                $processDiff = $parser->skipUntilStartsWith('diff', 'commit')->contains('diff');
            } catch (ParseEndedException $e) {
                $processDiff = false;
            }
        }

        return $diffs;
    }

    private function processDiff(GitParser $parser): object
    {
        if ($parser->contains('diff --git')) {
            return $this->processFileDiff($parser);
        }

        if ($parser->contains('diff --combined')) {
            // processMergeDiff
        }

        throw new Exception("Unknown diff at " . $parser->getLine());
    }

    private function processFileDiff(GitParser $parser): object
    {

        $parser->skipLine();

        if ($parser->contains('similarity index')) {
            $oldFile = $parser->skipUntilStartsWith('rename from ')->getLine()->after('rename from ')->trim();
            $newFile = $parser->skipUntilStartsWith('rename to ')->getLine()->after('rename to ')->trim();

            return (object) [
                'oldFile' => (string) $oldFile,
                'newFile' => (string) $newFile,
                'patches' => collect()
            ];
        } else {
            $oldFile = $parser->skipUntilStartsWith('--- ')->getLine()->after('--- ')->trim();
            $newFile = $parser->skipUntilStartsWith('+++ ')->getLine()->after('+++ ')->trim();

            if ($oldFile !== '/dev/null' && $oldFile !== $newFile) {
                // file renamed $oldFile
            }

            if ($newFile === '/dev/null') {
                // file deleted $oldFile
            }

            $patches = collect();

            $line = $parser->skipUntilStartsWith('@@ -');

            while ($parser->contains('@@ -')) {
                $line = $parser->getLine();

                $matches =  [];
                preg_match('/@@ -(\d+)(,\d+)? \+(\d+)(,\d+)? @@/', $line, $matches);

                try {
                    if (count($matches) === 5) {
                        [, $oldStart, $oldCount, $newStart, $newCount] = $matches;
                    } else if (count($matches) === 4) {
                        [, $oldStart, $oldCount, $newStart] = $matches;
                        $newCount = null;
                    } else {
                        [, $oldStart, $oldCount, $newStart, $newCount] = $matches;

                        // check if fails for more exceptions
                    }

                    $oldCount = !empty($oldCount) ? (string) Str::of($oldCount)->trim(',') : 1;
                    $newCount = !empty($newCount) ? (string) Str::of($newCount)->trim(',') : 1;

                    $patches->push((object) [
                        'oldStart' => $oldStart,
                        'oldCount' => $oldCount,
                        'newStart' => $newStart,
                        'newCount' => $newCount
                    ]);
                } catch (Exception $e) {
                    dd($line, $matches);
                }

                try {
                    $parser->skipUntilStartsWith('@@ -', 'diff', 'commit');
                } catch (ParseEndedException $e) {
                }
            }


            return (object) [
                'oldFile' => $oldFile->contains('/dev/null') ? (string) $newFile->substr(2) : (string) $oldFile->substr(2),
                'newFile' => $newFile->contains('/dev/null') ? (string) $oldFile->substr(2) : (string) $newFile->substr(2),
                'patches' => $patches
            ];
        }
    }
}
