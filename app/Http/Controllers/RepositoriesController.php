<?php

namespace App\Http\Controllers;

use App\Repository;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\GithubApiClient;
use App\Helpers\GithubRepositoryIssues;
use App\Helpers\GithubRepositoryPullRequests;

const MAX_ISSUES = 100;
const MAX_PULL_REQUESTS = 90;

class RepositoriesController extends Controller
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function rateLimit()
    {
        return response()->json(
            $this->github->getRateLimit()
        );
    }

    public function report(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        $repository = $this->getOrCreateRepository($request->name, $request->owner);

        $repositoryMetrics = $this->github->getRepositoryMetrics([
            'name' => $request->name,
            'owner' => $request->owner
        ]);

        $repositoryIssues = $this->getRepositoryIssues($request->name, $request->owner, $repositoryMetrics->issues->totalCount);
        $repositoryPullRequests = $this->getRepositoryPullRequests($request->name, $request->owner, $repositoryMetrics->issues->totalCount);

        return response()->json([
            'repo' => $repository,
            'issues' => $repositoryIssues->get()->count(),
            'pullRequests' => $repositoryPullRequests->get()->count()
        ]);
    }

    // REPOSITORY INFO

    private function getOrCreateRepository(string $name, string $owner): Repository
    {
        $repository = Repository::where('name', $name)->where('owner', $owner)->first();

        if ($repository === null) {
            $repositoryInfo = $this->github->getRepositoryInfo([
                'name' => $name,
                'owner' => $owner
            ]);

            $repository = Repository::create([
                'name' => $repositoryInfo->name,
                'slug' => Str::slug($repositoryInfo->name),
                'url' => $repositoryInfo->url,
                'description' => $repositoryInfo->description,
                'owner' => $repositoryInfo->owner->login
            ]);
        }

        return $repository;
    }

    // REPOSITORY ISSUES

    private function getRepositoryIssues(string $name, string $owner, int $total): GithubRepositoryIssues
    {
        if ($total > 100) {
            $repositoryIssues = $this->getRepositoryIssuesOver100($name, $owner, $total);
        } else {
            $repositoryIssues = $this->getRepositoryIssuesUnder100($name, $owner, $total);
        }

        return $repositoryIssues;
    }

    private function getRepositoryIssuesUnder100(string $name, string $owner, int $total): GithubRepositoryIssues
    {
        $repositoryIssues = new GithubRepositoryIssues(
            $this->github->getRepositoryIssues([
                'name' => $name,
                'owner' => $owner,
                'first' => $total
            ])->nodes
        );

        return $repositoryIssues;
    }

    private function getRepositoryIssuesOver100(string $name, string $owner, int $total): GithubRepositoryIssues
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
        }

        $repositoryIssues->add(
            $this->github->getRepositoryIssuesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ])->nodes
        );


        return $repositoryIssues;
    }

    // REPOSITORY PULL REQUESTs

    private function getRepositoryPullRequests(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        if ($total > 100) {
            $repositoryPullRequests = $this->getRepositoryPullRequestsOver100($name, $owner, $total);
        } else {
            $repositoryPullRequests = $this->getRepositoryPullRequestsUnder100($name, $owner, $total);
        }

        return $repositoryPullRequests;
    }

    private function getRepositoryPullRequestsUnder100(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        $repositoryPullRequests = new GithubRepositoryPullRequests(
            $this->github->getRepositoryPullRequests([
                'name' => $name,
                'owner' => $owner,
                'first' => $total
            ])->nodes
        );

        return $repositoryPullRequests;
    }

    private function getRepositoryPullRequestsOver100(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        $repositoryPullRequests = new GithubRepositoryPullRequests();

        $pages = $total / MAX_PULL_REQUESTS + 1;
        $lastPageCount = $total % MAX_PULL_REQUESTS;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            $paginatedPullRequests = $this->github->getRepositoryPullRequestsPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => MAX_ISSUES,
                'after' => $after
            ]);

            $repositoryPullRequests->add(
                $paginatedPullRequests->nodes
            );

            $after = $paginatedPullRequests->pageInfo->endCursor;
        }

        $repositoryPullRequests->add(
            $this->github->getRepositoryPullRequestsPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ])->nodes
        );


        return $repositoryPullRequests;
    }
}
