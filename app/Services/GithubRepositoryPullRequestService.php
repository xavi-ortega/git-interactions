<?php

namespace App\Services;

use App\Helpers\GithubApiClient;
use Illuminate\Support\Facades\Log;
use App\Helpers\GithubRepositoryPullRequests;

const MAX_PULL_REQUESTS = 90;

class GithubRepositoryPullRequestsService
{

    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepositoryPullRequests(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        // if ($total > MAX_PULL_REQUESTS) {
        $repositoryPullRequests = $this->getRepositoryPullRequestsOverMax($name, $owner, $total);
        // } else {
        //     $repositoryPullRequests = $this->getRepositoryPullRequestsUnderMax($name, $owner, $total);
        // }

        return $repositoryPullRequests;
    }

    private function getRepositoryPullRequestsUnderMax(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        $repositoryPullRequests = new GithubRepositoryPullRequests(
            $this->github->getRepositoryPullRequests([
                'name' => $name,
                'owner' => $owner,
                'first' => $total
            ])
        );

        return $repositoryPullRequests;
    }

    private function getRepositoryPullRequestsOverMax(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        $repositoryPullRequests = new GithubRepositoryPullRequests();

        $pages = floor($total / MAX_PULL_REQUESTS + 1);
        $lastPageCount = $total % MAX_PULL_REQUESTS;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            $paginatedPullRequests = $this->github->getRepositoryPullRequestsPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => MAX_PULL_REQUESTS,
                'after' => $after
            ]);

            $repositoryPullRequests->add(
                $paginatedPullRequests->nodes
            );

            $after = $paginatedPullRequests->pageInfo->endCursor;

            Log::debug($i . ' of ' . $pages . ' pages of pullRequests');
        }

        $paginatedPullRequests = $this->github->getRepositoryPullRequestsPaginated([
            'name' => $name,
            'owner' => $owner,
            'first' => $lastPageCount,
            'after' => $after
        ]);

        $repositoryPullRequests->add(
            $paginatedPullRequests->nodes
        );

        $repositoryPullRequests->setEndCursor($paginatedPullRequests->pageInfo->endCursor);

        Log::debug($i . ' of ' . $pages . ' pages of pullRequests');

        return $repositoryPullRequests;
    }
}
