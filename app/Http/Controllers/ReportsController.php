<?php

namespace App\Http\Controllers;

use App\User;
use Exception;
use App\Report;
use App\Repository;
use App\Jobs\MakeCodeReport;

use Illuminate\Http\Request;
use App\Jobs\MakeIssuesReport;

use App\Helpers\GithubApiClient;
use App\Jobs\MakeContributorsReport;
use App\Jobs\MakePullRequestsReport;
use App\Services\GithubRepositoryService;
use App\Exceptions\RepositoryNotFoundException;
use App\Helpers\Constants\ReportProgressType;
use App\Notifications\ReportEnded;
use App\Notifications\ReportStarted;

class ReportsController extends Controller
{
    private $repositoryService;

    public function __construct()
    {
        $this->repositoryService = new GithubRepositoryService();
    }

    public function rateLimit()
    {
        $github = new GithubApiClient();

        return response()->json(
            $github->getRateLimit()
        );
    }

    public function prepare(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        try {
            $repository = $this->getOrCreateRepository($request->name, $request->owner);

            $report = $this->dispatchReport($user, $repository);

            $user->notify(new ReportStarted($report));

            return response()->json([
                'message' => 'Your report is in progress',
                'report' => $report
            ]);
        } catch (RepositoryNotFoundException $e) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
    }

    public function report(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        $start = microtime(true);

        $repository = $this->getOrCreateRepository($request->name, $request->owner);

        $report = $repository->reports()->with('issues', 'pull_requests', 'contributors', 'code')->latest()->first();

        $time_elapsed_secs = microtime(true) - $start;

        return response()->json([
            'time' => $time_elapsed_secs,
            'repo' => $repository,
            'issues' => $report->issues,
            'pullRequests' => $report->pull_requests,
            'contributors' => $report->contributors,
            'code' => $report->code
        ]);
    }

    public function progress(Request $request)
    {
        $request->validate([
            'report_id' => 'required'
        ]);

        $report = Report::where('id', $request->report_id)->with('progress')->first();

        if ($report === null || $report->progress === null) {
            return response()->json([
                'message' => 'The report is not in progress'
            ], 204);
        }

        return response()->json($report->progress);
    }

    public function lastUserReports(Request $request)
    {
        $user = $request->user();

        return response()->json($user->reports()->with('repository')->get());
    }

    private function getOrCreateRepository(string $name, string $owner): Repository
    {
        $repository = Repository::where('name', $name)->where('owner', $owner)->first();

        if ($repository === null) {
            $repositoryInfo = $this->repositoryService->getRepository($name, $owner);

            $repository = Repository::create([
                'name' => $repositoryInfo->name,
                'slug' => "{$repositoryInfo->owner->login}/{$repositoryInfo->name}",
                'url' => $repositoryInfo->url,
                'description' => $repositoryInfo->description,
                'owner' => $repositoryInfo->owner->login
            ]);
        }

        return $repository;
    }

    private function dispatchReport(User $user, Repository $repository): Report
    {
        $repositoryMetrics = $this->repositoryService->getRepositoryMetrics($repository->name, $repository->owner);

        $report = $user->reports()->create([
            'repository_id' => $repository->id,
        ]);

        $report->progress()->create([
            'type' => ReportProgressType::WAITING,
            'progress' => 0
        ]);

        MakeIssuesReport::withChain([
            new MakePullRequestsReport($repository, $report, $repositoryMetrics->pullRequests->totalCount),
            new MakeContributorsReport($repository, $report),
            new MakeCodeReport($repository, $report, $repositoryMetrics->branches->totalCount),
            function () use ($user, $report) {
                $user->notify(new ReportEnded($report));
            }
        ])->dispatch($repository, $report, $repositoryMetrics->issues->totalCount);

        return $report;
    }
}
