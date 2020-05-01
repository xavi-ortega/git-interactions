<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryBranches
{
    private $collection;

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


    public function addCommits(string $branchName, array $commits)
    {
        $branch = $this->collection->where('name', $branchName)->first();

        $branch->commits = $branch->commits->merge($commits);
    }

    public function get(): Collection
    {
        return $this->collection;
    }

    public function getCommits(string $branchName): Collection
    {
        $branch = $this->collection->where('name', $branchName)->first();

        return $branch->commits->all ?? collect([]);
    }

    private function formatBranches(array $rawBranches)
    {
        return array_map(function ($branch) {
            return (object) [
                'name' => $branch->name,
                'totalCommits' => $branch->commits->history->totalCount,
                'commits' => collect([])
            ];
        }, $rawBranches);
    }
}
