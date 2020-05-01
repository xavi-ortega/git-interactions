<?php

namespace App\Services;

use App\Helpers\GithubApiClient;
use App\Helpers\GithubRepositoryBranches;

const MAX_BRANCHES = 100;
const MAX_COMMITS = 100;

class GithubRepositoryBranchService
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepositoryBranches(string $name, string $owner, int $total): GithubRepositoryBranches
    {
        if ($total > MAX_BRANCHES) {
            $repositoryBranches = $this->getRepositoryBranchesOverMax($name, $owner, $total);
        } else {
            $repositoryBranches = $this->getRepositoryBranchesUnderMax($name, $owner, $total);
        }

        return $repositoryBranches;
    }

    private function getRepositoryBranchesUnderMax(string $name, string $owner, int $total): GithubRepositoryBranches
    {
        $repositoryBranches = new GithubRepositoryBranches(
            $this->github->getRepositoryBranches([
                'name' => $name,
                'owner' => $owner,
                'first' => $total
            ])
        );

        $branchNames = $repositoryBranches->get()->pluck('totalCommits', 'name');

        foreach ($branchNames as $branchName => $totalCommits) {
            $commits = $this->getBranchCommits($name, $owner, $totalCommits, $branchName);

            $repositoryBranches->addCommits($branchName, $commits);
        }

        return $repositoryBranches;
    }

    private function getRepositoryBranchesOverMax(string $name, string $owner, int $total): GithubRepositoryBranches
    {
        $repositoryBranches = new GithubRepositoryBranches();

        $pages = $total / MAX_BRANCHES + 1;
        $lastPageCount = $total % MAX_BRANCHES;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            $paginatedBranches = $this->github->getRepositoryBranchesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => MAX_BRANCHES,
                'after' => $after
            ]);

            $repositoryBranches->add(
                $paginatedBranches->nodes
            );

            $after = $paginatedBranches->pageInfo->endCursor;
        }

        $repositoryBranches->add(
            $this->github->getRepositoryBranchesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ])->nodes
        );

        $branchNames = $repositoryBranches->get()->pluck('totalCommits', 'name');

        foreach ($branchNames as $branchName => $totalCommits) {
            $commits = $this->getBranchCommits($name, $owner, $totalCommits, $branchName);

            $repositoryBranches->addCommits($branchName, $commits);
        }

        return $repositoryBranches;

        return $repositoryBranches;
    }

    private function getBranchCommits(string $name, string $owner, int $total, string $branch)
    {
        if ($total > MAX_COMMITS) {
            $commits = $this->getBranchCommitsOverMax($name, $owner, $total, $branch);
        } else {
            $commits = $this->getBranchCommitsUnderMax($name, $owner, $total, $branch);
        }

        return $commits ?? [];
    }

    private function getBranchCommitsUnderMax(string $name, string $owner, int $total, string $branch)
    {
        return $this->github->getRepositoryCommitsByBranch([
            'name' => $name,
            'owner' => $owner,
            'first' => $total,
            'branch' => 'refs/heads/' . $branch
        ]);
    }

    private function getBranchCommitsOverMax(string $name, string $owner, int $total, string $branch)
    {
        $commits = collect([]);
        $pages = $total / MAX_COMMITS + 1;
        $lastPageCount = $total % MAX_COMMITS;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            $paginatedCommits = $this->github->getRepositoryCommitsByBranchPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => MAX_COMMITS,
                'branch' => 'refs/heads/' . $branch,
                'after' => $after,
            ]);

            $commits = $commits->merge($paginatedCommits->nodes);

            $after = $paginatedCommits->pageInfo->endCursor;
        }

        $paginatedCommits = $this->github->getRepositoryCommitsByBranchPaginated([
            'name' => $name,
            'owner' => $owner,
            'first' => $lastPageCount,
            'branch' => 'refs/heads/' . $branch,
            'after' => $after,
        ]);

        $commits = $commits->merge($paginatedCommits->nodes);

        return $commits->toArray();
    }
}
