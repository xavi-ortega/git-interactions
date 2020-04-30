<?php

namespace App\Http\Controllers;

use App\Helpers\GithubApiClient;
use App\Repository;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

use App\Services\GithubRepositoryService;
use App\Services\GithubRepositoryIssueService;
use App\Services\GithubRepositoryBranchService;
use App\Services\GithubRepositoryPullRequestsService;

const MAX_ISSUES = 100;


class RepositoriesController extends Controller
{
    private $repositoryService;
    private $issueService;
    private $pullRequestService;
    private $branchesService;

    public function __construct()
    {
        $this->repositoryService = new GithubRepositoryService();
        $this->issueService = new GithubRepositoryIssueService();
        $this->pullRequestService = new GithubRepositoryPullRequestsService();
        $this->branchesService = new GithubRepositoryBranchService();
    }

    public function rateLimit()
    {
        $github = new GithubApiClient();

        return response()->json(
            $github->getRateLimit()
        );
    }

    public function report(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        $repository = $this->getOrCreateRepository($request->name, $request->owner);

        $repositoryMetrics = $this->repositoryService->getRepositoryMetrics($request->name, $request->owner);

        // return response()->json($repositoryMetrics);

        $repositoryIssues = $this->issueService->getRepositoryIssues($request->name, $request->owner, $repositoryMetrics->issues->totalCount);
        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($request->name, $request->owner, $repositoryMetrics->issues->totalCount);
        $repositoryBranches = $this->branchesService->getRepositoryBranches($request->name, $request->owner, $repositoryMetrics->branches->totalCount);

        return response()->json([
            'repo' => $repository,
            'issues' => $repositoryIssues->get()->count(),
            'pullRequests' => $repositoryPullRequests->get()->count(),
            'branches' => $repositoryBranches->get()->map(function ($branch) {
                return [
                    'name' => $branch->name,
                    'commits' => $branch->commits->history->all->count()
                ];
            })
        ]);
    }

    private function getOrCreateRepository(string $name, string $owner): Repository
    {
        $repository = Repository::where('name', $name)->where('owner', $owner)->first();

        if ($repository === null) {
            $repositoryInfo = $this->repositoryService->getRepository($name, $owner);

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
}
