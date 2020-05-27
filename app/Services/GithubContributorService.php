<?php

namespace App\Services;

use App\Helpers\GithubApiClient;

class GithubContributorService
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getByCommit(string $name, string $owner, string $commit): object
    {
        $commit = $this->github->getRepositoryCommitById([
            'name' => $name,
            'owner' => $owner,
            'oid' => $commit
        ]);

        return $commit->author;
    }
}
