<?php

namespace App\Services;

use App\Helpers\GithubApiClient;

class GithubRepositoryService
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepository(string $name, string $owner)
    {
        return $this->github->getRepositoryInfo([
            'name' => $name,
            'owner' => $owner
        ]);
    }

    public function getRepositoryMetrics(string $name, string $owner)
    {
        return $this->github->getRepositoryMetrics([
            'name' => $name,
            'owner' => $owner
        ]);
    }
}
