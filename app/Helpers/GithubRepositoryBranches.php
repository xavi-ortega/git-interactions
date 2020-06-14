<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GithubRepositoryBranches
{
    private $collection;
    private $endCursor;

    public function __construct(array $branches = [])
    {
        $this->collection = collect(
            $this->formatBranches($branches)
        );
    }

    public function add(array $branches)
    {
        $this->collection = $this->collection->merge(
            $this->formatBranches($branches)
        );
    }

    public function setEndCursor($endCursor)
    {
        $this->endCursor = $endCursor;
    }

    public function getEndCursor()
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
            $lastActivity = count($branch->commits->history->nodes) > 0 ? Carbon::make($branch->commits->history->nodes[0]->pushedDate)->diffForHumans() : null;

            return (object) [
                'name' => $branch->name,
                'totalCommits' => $branch->commits->history->totalCount,
                'lastActivity' => $lastActivity
            ];
        }, $rawBranches);
    }
}
