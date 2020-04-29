<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryPullRequests
{
    private $collection;

    public function __construct(array $pullRequests = [])
    {
        $this->collection = collect($pullRequests);
    }

    public function add(array $pullRequests)
    {
        $this->collection = $this->collection->merge($pullRequests);
    }

    public function get(): Collection
    {
        return $this->collection;
    }
}
