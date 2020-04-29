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
        $branch = $this->collection->where('name', $branchName);

        if (property_exists($branch->commits, 'all')) {
            $branch->commits->all->merge($commits);
        } else {
            $branch->commits->all = collect($commits);
        }
    }

    public function get(): Collection
    {
        return $this->collection;
    }

    public function getCommits(string $branchName): Collection
    {
        $branch = $this->collection->where('name', $branchName);

        return $branch->commits->all ?? collect([]);
    }
}
