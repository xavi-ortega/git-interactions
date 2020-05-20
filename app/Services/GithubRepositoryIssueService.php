<?php

namespace App\Services;

use App\Helpers\GithubApiClient;
use Illuminate\Support\Facades\Log;
use App\Helpers\GithubRepositoryIssues;

const MAX_ISSUES = 100;

class GithubRepositoryIssueService
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepositoryIssues(string $name, string $owner, int $total): GithubRepositoryIssues
    {
        $repositoryIssues = new GithubRepositoryIssues();

        $pages = floor($total / MAX_ISSUES + 1);
        $lastPageCount = $total % MAX_ISSUES;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            $paginatedIssues = $this->github->getRepositoryIssuesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => MAX_ISSUES,
                'after' => $after
            ]);

            $repositoryIssues->add(
                $paginatedIssues->nodes
            );

            $after = $paginatedIssues->pageInfo->endCursor;

            Log::debug($i . ' of ' . $pages . ' pages of issues');
        }

        $paginatedIssues = $this->github->getRepositoryIssuesPaginated([
            'name' => $name,
            'owner' => $owner,
            'first' => $lastPageCount,
            'after' => $after
        ]);

        $repositoryIssues->add(
            $paginatedIssues->nodes
        );

        $repositoryIssues->setEndCursor($paginatedIssues->pageInfo->endCursor);

        Log::debug($i . ' of ' . $pages . ' pages of issues');


        return $repositoryIssues;
    }
}
