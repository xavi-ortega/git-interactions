<?php

namespace App\Services;

use App\Exceptions\ParseEndedException;
use Exception;
use Carbon\Carbon;
use App\Helpers\GitParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        $hash = $parser->skipUntilStartsWith('commit')->getLine()->after('commit ');

        return $hash;
    }

    private function processAuthor(GitParser $parser): string
    {
        $author = $parser->skipUntilStartsWith('Author: ')->getLine()->after('Author: ');

        Log::info('Author: ' . $author);

        return explode(" <", $author)[0];
    }

    private function processDate(GitParser $parser): Carbon
    {
        $timeString = $parser->skipUntilStartsWith('Date: ')->getLine()->after('Date: ')->trim();

        Log::info('Date: ' . $timeString);

        return Carbon::make((string) $timeString);
    }

    private function processDiffs(GitParser $parser)
    {
        $diffs = [];

        $processDiff = $parser->skipUntilStartsWith('diff', 'commit')->contains('diff');

        while ($processDiff) {
            $diffs[] = $this->processDiff($parser);

            try {
                $processDiff = $parser->skipUntilStartsWith('diff', 'commit')->contains('diff');
            } catch (ParseEndedException $e) {
                $processDiff = false;
            }
        }

        return $diffs;
    }

    private function processDiff(GitParser $parser)
    {
        if ($parser->contains('diff --git')) {
            return $this->processFileDiff($parser);
        }

        if ($parser->contains('diff --combined')) {
            // processMergeDiff
        }

        throw new Exception("Unknown diff at " . $parser->getLine());
    }

    private function processFileDiff(GitParser $parser)
    {

        $parser->skipLine();

        if ($parser->contains('similarity index')) {
            // rename file
        } else {
            $oldFile = $parser->skipUntilStartsWith('--- ')->getLine()->after('--- ');
            $newFile = $parser->skipUntilStartsWith('+++ ')->getLine()->after('+++ ');

            if ($oldFile !== '/dev/null' && $oldFile !== $newFile) {
                // file renamed $oldFile
            }

            if ($newFile === '/dev/null') {
                // file deleted $oldFile
            }

            $line = $parser->skipUntilStartsWith('@@ -')->getLine();

            $matches =  [];
            preg_match('/@@ -(\d+)?,?(\d+) \+(\d+)?,?(\d+) @@/', $line, $matches);

            try {
                [, $oldStart, $oldEnd, $newStart, $newEnd] = $matches;
            } catch (Exception $e) {
                dd($line, $matches);
            }

            return (object) [
                'oldFile' => $oldFile === '/dev/null' ? 'deleted' : mb_substr($oldFile, 2),
                'newFile' => $newFile === '/dev/null' ? 'deleted' : mb_substr($newFile, 2),
                'oldStart' => $oldStart,
                'oldEnd' => $oldEnd,
                'newStart' => $newStart,
                'newEnd' => $newEnd
            ];
        }
    }
}
