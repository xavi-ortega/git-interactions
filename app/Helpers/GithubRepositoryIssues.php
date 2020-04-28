<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryIssues
{
    private $collection;

    public function __construct(array $issues = [])
    {
        $this->collection = collect($issues);
    }

    public function add($issues)
    {
        $this->collection = $this->collection->merge($issues);
    }

    public function get()
    {
        return $this->collection;
    }
}
