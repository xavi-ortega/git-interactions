<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Repository;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\GithubApiClient;

use App\Services\GithubRepositoryService;
use App\Helpers\GithubRepositoryContributors;
use App\Helpers\GithubRepositoryPullRequests;
use App\Services\GithubRepositoryIssueService;
use App\Services\GithubRepositoryBranchService;

use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE, ACTION_MERGE, ACTION_ASSIGN, ACTION_SUGGESTED_REVIEWER, ACTION_REVIEW};
use App\Services\GithubRepositoryPullRequestsService;
use DateInterval;
use GuzzleHttp\Exception\ServerException;

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




        // $repositoryBranches = $this->branchesService->getRepositoryBranches($request->name, $request->owner, $repositoryMetrics->branches->totalCount);

        $repositoryContributors = new GithubRepositoryContributors();

        // $issuesReport = $this->makeIssuesReport($request->name, $request->owner, $repositoryMetrics->issues->totalCount, $repositoryContributors);
        $pullRequestsReport = $this->makePullRequestsReport($request->name, $request->owner, $repositoryMetrics->pullRequests->totalCount, $repositoryContributors);

        return response()->json([
            'repo' => $repository,
            'pullRequests' => $pullRequestsReport,
            'contributors' => $repositoryContributors->get()
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

    private function makeIssuesReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {
        $repositoryIssues = $this->issueService->getRepositoryIssues($name, $owner, $totalCount);

        $totalTimeDiff = Carbon::make('@0'); // timestamp 0
        $oneHour = new DateInterval('PT1H');

        $issuesReport = $repositoryIssues->get()->reduce(function ($report, $issue) use ($repositoryContributors, $totalTimeDiff, $oneHour) {
            $report['total']++;

            if ($issue->closed) {
                $report['closed']++;
            } else {
                $report['open']++;
            }

            $issueInfo = (object) [
                'id' => $issue->id,
                'timeToClose' => Carbon::make($issue->createdAt)->diff($issue->closedAt)
            ];

            $totalTimeDiff->add($issueInfo->timeToClose);

            if ($issueInfo->timeToClose < $oneHour) {
                $report['closed_les_than_one_hour']++;
            }

            if ($issue->author !== null) {
                $repositoryContributors->registerIssueAction($issue->author, ACTION_OPEN, $issueInfo);
            }

            if ($issue->closedBy !== null) {
                $repositoryContributors->registerIssueAction($issue->closedBy, ACTION_CLOSE, $issueInfo);

                if (Str::contains($issue->closedBy, [
                    'bot', 'b0t'
                ])) {
                    $report['closed_by_bot']++;
                }
            }

            return $report;
        }, [
            'total' => 0,
            'open' => 0,
            'closed' => 0,
            'closed_by_bot' => 0,
            'closed_less_than_one_hour' => 0
        ]);

        $avg_seconds_to_close = round($totalTimeDiff->getTimestamp() / $repositoryIssues->get()->count());
        $avg_minutes_to_close = round($avg_seconds_to_close / 60);

        $issuesReport['avg_time_to_close'] = floor($avg_minutes_to_close /  60) . ':' . $avg_minutes_to_close % 60;

        return $issuesReport;
    }

    private function makePullRequestsReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {
        // try {
        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($name, $owner, $totalCount);
        // } catch (ServerException $e) {
        //     var_dump("Server exception");
        //     echo $e->getResponse();
        //     $repositoryPullRequests = new GithubRepositoryPullRequests();
        //     return $e->getResponse();
        // }

        $totals = (object) [
            'closeTime' => Carbon::make('@0'), // timestamp 0
            'mergeTime' => Carbon::make('@0'),
            'prc_good_assignees' => 0,
            'prc_good_reviewers' => 0,
            'prc_unexpected_reviewers' => 0,
            'commits' => 0
        ];

        $oneHour = new DateInterval('PT1H');

        $pullRequestsReport = $repositoryPullRequests->get()->reduce(function ($report, $pullRequest) use ($repositoryContributors, $totals, $oneHour) {
            $report['total']++;

            if ($pullRequest->closed) {
                $report['closed']++;

                if ($pullRequest->totalCommits <= 0) {
                    $report['closed_without_commits']++;
                } else {
                    $totals->commits += $pullRequest->totalCommits;
                }
            } else {
                $report['open']++;
            }

            $pullRequestInfo = (object) [
                'id' => $pullRequest->id,
                'timeToClose' => Carbon::make($pullRequest->createdAt)->diff($pullRequest->closedAt),
                'timeToMerge' => Carbon::make($pullRequest->createdAt)->diff($pullRequest->mergedAt)
            ];

            $totals->closeTime->add($pullRequestInfo->timeToClose);
            $totals->mergeTime->add($pullRequestInfo->timeToMerge);

            if ($pullRequestInfo->timeToClose < $oneHour) {
                $report['closed_les_than_one_hour']++;
            }

            if ($pullRequest->author !== null) {
                $repositoryContributors->registerPullRequestAction($pullRequest->author, ACTION_OPEN, $pullRequestInfo);
            }

            if ($pullRequest->closedBy !== null) {
                $repositoryContributors->registerPullRequestAction($pullRequest->closedBy, ACTION_CLOSE, $pullRequestInfo);
            }

            if ($pullRequest->mergedBy !== null) {
                $repositoryContributors->registerPullRequestAction($pullRequest->mergedBy, ACTION_MERGE, $pullRequestInfo);
            }

            foreach ($pullRequest->assignees as $assignee) {
                $repositoryContributors->registerPullRequestAction($assignee, ACTION_ASSIGN, $pullRequestInfo);
            }

            foreach ($pullRequest->suggestedReviewers as $suggestedReviewer) {
                $repositoryContributors->registerPullRequestAction($suggestedReviewer, ACTION_SUGGESTED_REVIEWER, $pullRequestInfo);
            }

            foreach ($pullRequest->reviewers as $reviewer) {
                $repositoryContributors->registerPullRequestAction($reviewer, ACTION_REVIEW, $pullRequestInfo);
            }

            // $totals->prc_good_assignees
            if (count($pullRequest->suggestedReviewers) > 0) {
                $good_reviewers = array_intersect($pullRequest->suggestedReviewers, $pullRequest->reviewers);
                $unexpected_reviewers = array_diff($pullRequest->reviewers, $pullRequest->suggestedReviewers);

                $prc_good_reviewers = count($good_reviewers) / count($pullRequest->suggestedReviewers) * 100;
                $prc_unexpected_reviewers = count($unexpected_reviewers) / count($pullRequest->suggestedReviewers) * 100;

                $totals->prc_good_reviewers += $prc_good_reviewers;
                $totals->prc_unexpected_reviewers += $prc_unexpected_reviewers;
            }

            return $report;
        }, [
            'total' => 0,
            'open' => 0,
            'closed' => 0,
            'closed_without_commits' => 0,
        ]);

        $totalPullRequests = $repositoryPullRequests->get()->count();

        if ($totalPullRequests > 0) {

            $pullRequestsReport['avg_prc_good_assignees'] = round($totals->prc_good_assignees / $totalPullRequests, 2);
            $pullRequestsReport['avg_prc_bad_assignees'] = 100 - $pullRequestsReport['avg_prc_good_assignees'];
            $pullRequestsReport['avg_prc_good_reviewers'] = round($totals->prc_good_reviewers / $totalPullRequests, 2);
            $pullRequestsReport['avg_prc_unexpected_reviewers'] = round($totals->prc_unexpected_reviewers / $totalPullRequests, 2);
            $pullRequestsReport['avg_prc_bad_reviewers'] = 100 - $pullRequestsReport['avg_prc_good_reviewers'] - $pullRequestsReport['avg_prc_unexpected_reviewers'];
            $pullRequestsReport['avg_commits_per_pr'] = round($totals->commits / $totalPullRequests, 2);

            $avg_minutes_to_close = round($totals->closeTime->getTimestamp() / 60 / $totalPullRequests);
            $avg_minutes_to_merge = round($totals->mergeTime->getTimestamp() / 60 / $totalPullRequests);

            $pullRequestsReport['avg_time_to_close'] = floor($avg_minutes_to_close / 60) . ':' . ($avg_minutes_to_close % 60);
            $pullRequestsReport['avg_time_to_merge'] = floor($avg_minutes_to_merge / 60) . ':' . ($avg_minutes_to_merge % 60);
        }

        return $pullRequestsReport;
    }
}
