<?php

namespace App\Http\Controllers;

use DateInterval;
use Carbon\Carbon;
use App\Repository;
use Carbon\CarbonInterval;
use Illuminate\Support\Str;

use Illuminate\Http\Request;
use App\Helpers\GithubApiClient;
use App\Services\GithubRepositoryService;
use GuzzleHttp\Exception\ServerException;
use App\Helpers\GithubRepositoryContributors;

use App\Helpers\GithubRepositoryPullRequests;
use App\Services\GithubRepositoryIssueService;
use App\Services\GithubRepositoryBranchService;
use App\Services\GithubRepositoryPullRequestsService;
use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE, ACTION_MERGE, ACTION_ASSIGN, ACTION_COMMIT_BRANCH, ACTION_COMMIT_PR, ACTION_SUGGESTED_REVIEWER, ACTION_REVIEW};

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

        $repositoryContributors = new GithubRepositoryContributors();

        $issuesReport = $this->makeIssuesReport($request->name, $request->owner, $repositoryMetrics->issues->totalCount, $repositoryContributors);
        $pullRequestsReport = $this->makePullRequestsReport($request->name, $request->owner, $repositoryMetrics->pullRequests->totalCount, $repositoryContributors);
        $contributorsReport = $this->makeContributorsReport($request->name, $request->owner, $repositoryMetrics->branches->totalCount, $repositoryContributors);

        return response()->json([
            'repo' => $repository,
            'issues' => $issuesReport,
            'pullRequests' => $pullRequestsReport,
            'contributors' => $contributorsReport
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
        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($name, $owner, $totalCount);

        $totals = (object) [
            'closeTime' => Carbon::make('@0'), // timestamp 0
            'mergeTime' => Carbon::make('@0'),
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
                $report['closed_less_than_one_hour']++;
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

            return $report;
        }, [
            'total' => 0,
            'open' => 0,
            'closed' => 0,
            'closed_without_commits' => 0,
        ]);

        $totalPullRequests = $repositoryPullRequests->get()->count();

        if ($totalPullRequests > 0) {

            $pullRequestsReport['prc_closed_with_commits'] = round($pullRequestsReport['closed_without_commits'] / $totalPullRequests, 2);
            $pullRequestsReport['avg_commits_per_pr'] = round($totals->commits / $totalPullRequests);

            $avg_minutes_to_close = round($totals->closeTime->getTimestamp() / 60 / $totalPullRequests);
            $avg_minutes_to_merge = round($totals->mergeTime->getTimestamp() / 60 / $totalPullRequests);

            $pullRequestsReport['avg_time_to_close'] = floor($avg_minutes_to_close / 60) . ':' . ($avg_minutes_to_close % 60);
            $pullRequestsReport['avg_time_to_merge'] = floor($avg_minutes_to_merge / 60) . ':' . ($avg_minutes_to_merge % 60);
        }

        return $pullRequestsReport;
    }

    private function makeContributorsReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {
        $repositoryBranches = $this->branchesService->getRepositoryBranches($name, $owner, $totalCount);

        $repositoryBranches->get()->each(function ($branch) use ($repositoryContributors) {
            $branch->commits->each(function ($commit) use ($branch, $repositoryContributors) {

                if ($commit->author !== null && $commit->changedFiles > 0) {

                    $timeToPush = $commit->pushedAt !== null ? Carbon::make('@0')->add(
                        Carbon::make($commit->committedAt)->diffAsCarbonInterval($commit->pushedAt)
                    )->timestamp : null;

                    $commitInfo = (object) [
                        'id' => $commit->id,
                        'branch' => $branch->name,
                        'changedLines' => $commit->additions + $commit->deletions,
                        'changedFiles' => $commit->changedFiles,
                        'timeToPush' => $timeToPush
                    ];

                    $repositoryContributors->registerCommitAction($commit->author, ACTION_COMMIT_BRANCH, $commitInfo);

                    foreach ($commit->pullRequests as $pullRequest) {
                        $commitInfo->pullRequest = $pullRequest;

                        $repositoryContributors->registerCommitAction($commit->author, ACTION_COMMIT_PR, $commitInfo);
                    }
                }
            });
        });

        $contributors = $repositoryContributors->get()->flatten(1)->reduce(function ($contributors, $contributor) {
            return (object) [
                'issues' => $contributors->issues->merge($contributor->issues),
                'pullRequests' => $contributors->pullRequests->merge($contributor->pullRequests),
                'commits' => $contributors->commits->merge($contributor->commits)
            ];
        }, (object) [
            'issues' => collect(),
            'pullRequests' => collect(),
            'commits' => collect()
        ]);

        $pullRequests = $contributors->pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
            $pullRequest->contributorsCollection = $repositoryContributors->getContributorsCommitedTo($pullRequest->id);
            $pullRequest->reviewersCollection = $repositoryContributors->getContributorsReviewedTo($pullRequest->id);

            return $pullRequest;
        });

        $avgTimeToPush = Carbon::createFromTimestamp(
            floor($contributors->commits->pluck('timeToPush')->filter(function ($timeToPush) {
                return $timeToPush !== null;
            })->median())
        )->diffAsCarbonInterval(
            Carbon::createFromTimestamp(0)
        );

        return [
            'total' => $repositoryContributors->get()->count(),
            'avg_files_per_commit' => $contributors->commits->pluck('changedFiles')->median(),
            'avg_lines_per_commit' => $contributors->commits->pluck('changedLines')->median(),
            'avg_lines_per_file_per_commit' => $contributors->commits->pluck('linesPerFile')->median(),
            'avg_pull_request_contributed' => $repositoryContributors->get()->map(function ($contributor) {
                return $contributor->pullRequests->count();
            })->median(),
            'avg_prc_good_assignees' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $contributors = $pullRequest->contributorsCollection;
                $good_assignees = $contributors->intersect($repositoryContributors->getContributorsAssignedTo($pullRequest->id));

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return 0;
                }

                return round($good_assignees->count() / $totalContributors, 2);
            })->median(),
            'avg_prc_bad_assignees' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $contributors = $pullRequest->contributorsCollection;
                $bad_assignees = $repositoryContributors->getContributorsAssignedTo($pullRequest->id)->diff($contributors);

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return $bad_assignees->count() > 0 ? 100 : 0;
                }

                return round($bad_assignees->count() / $totalContributors, 2);
            })->median(),
            'avg_prc_unexpected_contributors' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $contributors = $pullRequest->contributorsCollection;
                $unexpected_contributors = $contributors->diff($repositoryContributors->getContributorsAssignedTo($pullRequest->id));

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return 0;
                }

                return round($unexpected_contributors->count() / $totalContributors, 2);
            })->median(),
            'avg_prc_good_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $good_reviewers = $reviewers->intersect($repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($good_reviewers->count() / $totalReviewers, 2);
            })->median(),
            'avg_prc_bad_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $bad_reviewers = $repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id)->diff($reviewers);

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return $bad_reviewers->count() > 0 ? 100 : 0;
                }

                return round($bad_reviewers->count() / $totalReviewers, 2);
            })->median(),
            'avg_prc_unexpected_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $unexpected_reviewers = $reviewers->diff($repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($unexpected_reviewers->count() / $totalReviewers, 2);
            })->median(),
            'avg_time_to_push' => $avgTimeToPush->forHumans(),
            'prc_new_code' => 0,
            'prc_rewrite_others_code' => 0,
            'prc_rewrite_own_code' => 0
        ];
    }
}
