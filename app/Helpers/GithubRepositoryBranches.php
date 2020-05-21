<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryBranches
{
    private $collection;
    private $endCursor;
    private $commitEndCursors;

    public function __construct(array $branches = [])
    {
        $this->collection = collect(
            $this->formatBranches($branches)
        );

        $this->commitEndCursors = collect();
    }

    public function add(array $branches)
    {
        $this->collection = $this->collection->merge(
            $this->formatBranches($branches)
        );
    }

    public function setEndCursor(string $endCursor)
    {
        $this->endCursor = $endCursor;
    }

    public function getEndCursor(): string
    {
        return $this->endCursor;
    }

    public function get(): Collection
    {
        return $this->collection;
    }

    private function formatBranches(array $rawBranches)
    {
        return array_map(function ($branch) {
            $lastActivity = count($branch->commits->history->nodes) > 1 ? $branch->commits->history->nodes[0]->pushedDate : null;

            return (object) [
                'name' => $branch->name,
                'totalCommits' => $branch->commits->history->totalCount,
                'lastActivity' => $lastActivity
            ];
        }, $rawBranches);
    }
}
