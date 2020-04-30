<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryBranches
{
    private $collection;

    public function __construct(array $branches = [])
    {
        $this->collection = collect($branches);
    }

    public function add(array $branches)
    {
        $this->collection = $this->collection->merge($branches);
    }


    public function addCommits(string $branchName, array $commits)
    {
        $branch = $this->collection->where('name', $branchName)->first();

        if (property_exists($branch->commits->history, 'all')) {
            $branch->commits->history->all = $branch->commits->history->all->merge($commits);
        } else {
            $branch->commits->history->all = collect($commits);
        }
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
}
