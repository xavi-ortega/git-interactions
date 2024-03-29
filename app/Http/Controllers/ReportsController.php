<?php

namespace App\Http\Controllers;

use App\User;
use Exception;
use App\Report;
use App\Repository;
use App\ReportSearch;

use App\ReportProgress;
use App\Jobs\MakeCodeReport;

use Illuminate\Http\Request;
use App\Events\ReportFinished;
use App\Jobs\MakeIssuesReport;
use App\Helpers\GithubApiClient;
use App\Notifications\ReportEnded;
use Illuminate\Support\Facades\DB;
use App\Jobs\MakeContributorsReport;
use App\Jobs\MakePullRequestsReport;
use App\Notifications\ReportStarted;
use App\Services\GithubRepositoryService;
use App\Helpers\Constants\ReportProgressType;
use App\Exceptions\RepositoryNotFoundException;

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

    public function search(Request $request)
    {
        $request->validate([
            'owner' => 'required',
            'name' => 'required'
        ]);

        try {
            $repository = $this->getOrCreateRepository($request->name, $request->owner);

            $searches = $repository->searches;

            if ($searches) {
                $repository->searches()->update([
                    'total' => $searches->total + 1
                ]);
            } else {
                $repository->searches()->create();
            }

            $reports = $repository->reports()->latest()->take(5)->get();

            return response()->json([
                'repository' => $repository,
                'reports' => $reports->map(function ($report) {
                    $report->since = $report->created_at->diffForHumans();
                    return $report;
                })
            ]);
        } catch (RepositoryNotFoundException $e) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
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

            return response()->json([
                'message' => 'Your report is in progress',
                'report' => $report
            ]);
        } catch (RepositoryNotFoundException $e) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
    }

    public function report(Report $report)
    {
        if ($report->progress) {
            return $this->progress($report);
        }

        return response()->json([
            'repository' => $report->repository,
            'report' => [
                'id' => $report->id,
                'progress' => null,
                'status' => $report->status,
            ],
            'issues' => $report->issues,
            'pullRequests' => $report->pull_requests,
            'contributors' => $report->contributors,
            'code' => $report->code
        ]);
    }

    public function progress(Report $report)
    {
        if (!$report || !$progress = $report->progress) {
            return response()->json([
                'message' => 'The report is not in progress'
            ], 204);
        }

        return response()->json([
            'repository' => $report->repository,
            'report' => $report,
            'progress' => $progress
        ]);
    }

    public function queue()
    {
        $queue = ReportProgress::with('report')->get();

        return response()->json($queue);
    }

    public function lastUserReports(Request $request)
    {
        $user = $request->user();

        return response()->json($user->reports()->with('repository')->has('issues')->has('pull_requests')->has('contributors')->has('code')->get());
    }

    public function popularReports(Request $request)
    {
        $mostSearched = ReportSearch::orderBy('total', 'desc')->with('repository')->has('repository.latest_report')->take(12)->get();

        return response()->json($mostSearched);
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

        $user->notify(new ReportStarted($report));

        MakeIssuesReport::withChain([
            new MakePullRequestsReport($repository, $report, $repositoryMetrics->pullRequests->totalCount),
            new MakeContributorsReport($repository, $report),
            new MakeCodeReport($repository, $report, $repositoryMetrics->branches->totalCount),
            function () use ($user, $report) {
                $progress = $report->progress;

                $progress->delete();
                $report->update(['status' => 'finished']);

                $user->notify(new ReportEnded($report));
                event(new ReportFinished($report));
            }
        ])->dispatch($repository, $report, $repositoryMetrics->issues->totalCount);

        return $report;
    }
}
