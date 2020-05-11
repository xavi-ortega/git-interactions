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
        if ($total > MAX_ISSUES) {
            $repositoryIssues = $this->getRepositoryIssuesOverMax($name, $owner, $total);
        } else {
            $repositoryIssues = $this->getRepositoryIssuesUnderMax($name, $owner, $total);
        }

        return $repositoryIssues;
    }

    private function getRepositoryIssuesUnderMax(string $name, string $owner, int $total): GithubRepositoryIssues
    {
        $repositoryIssues = new GithubRepositoryIssues(
            $this->github->getRepositoryIssues([
                'name' => $name,
                'owner' => $owner,
                'first' => $total
            ])
        );

        return $repositoryIssues;
    }

    private function getRepositoryIssuesOverMax(string $name, string $owner, int $total): GithubRepositoryIssues
    {
        $repositoryIssues = new GithubRepositoryIssues();

        $pages = $total / MAX_ISSUES + 1;
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

            Log::debug($i . ' of ' . $pages . ' of issues');
        }

        $repositoryIssues->add(
            $this->github->getRepositoryIssuesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ])->nodes
        );

        Log::debug($i . ' of ' . $pages . ' of issues');


        return $repositoryIssues;
    }
}
