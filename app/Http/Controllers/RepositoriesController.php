<?php

namespace App\Http\Controllers;

use App\Exceptions\RepositoryNotFoundException;
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
use App\Report;
use App\Services\GithubRepositoryIssueService;
use App\Services\GithubRepositoryBranchService;
use App\Services\GithubRepositoryPullRequestsService;
use App\User;

use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE, ACTION_MERGE, ACTION_ASSIGN, ACTION_COMMIT_BRANCH, ACTION_COMMIT_PR, ACTION_SUGGESTED_REVIEWER, ACTION_REVIEW};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        try {
            $repository = $this->getOrCreateRepository($request->name, $request->owner);

            $report = $this->getOrCreateReport($repository);

            return response()->json([
                'repo' => $repository,
                'issues' => $report->issues,
                'pullRequests' => $report->pull_requests,
                'contributors' => $report->contributors
            ]);
        } catch (RepositoryNotFoundException $e) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
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

    private function getOrCreateReport(Repository $repository): Report
    {
        $user = User::find(1);

        $report = $repository->reports()->first();

        if ($report === null) {
            $repositoryContributors = new GithubRepositoryContributors();
            $repositoryMetrics = $this->repositoryService->getRepositoryMetrics($repository->name, $repository->owner);

            $issuesReport = $this->makeIssuesReport($repository->name, $repository->owner, $repositoryMetrics->issues->totalCount, $repositoryContributors);
            $pullRequestsReport = $this->makePullRequestsReport($repository->name, $repository->owner, $repositoryMetrics->pullRequests->totalCount, $repositoryContributors);
            $contributorsReport = $this->makeContributorsReport($repository->name, $repository->owner, $repositoryMetrics->branches->totalCount, $repositoryContributors);

            $raw = $repositoryContributors->get()->toJson();

            $path = Storage::disk('raw')->put($repository->id . '/raw.json', $raw);

            $repository->raw = $path;
            $repository->save();

            $report = $user->reports()->create([
                'repository_id' => $repository->id,
            ]);

            $report->issues()->create($issuesReport);
            $report->pull_requests()->create($pullRequestsReport);
            $report->contributors()->create($contributorsReport);
        }

        return $report;
    }

    private function makeIssuesReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {
        $repositoryIssues = $this->issueService->getRepositoryIssues($name, $owner, $totalCount);

        $repositoryContributors->registerEndCursor('issues', $repositoryIssues->getEndCursor());

        $oneHour = new DateInterval('PT1H');

        $total = $repositoryIssues->get()->reduce(function ($total, $issue) use ($repositoryContributors, $oneHour) {
            $total->issues++;

            if ($issue->closed) {
                $total->closed++;
            } else {
                $total->open++;
            }

            $intervalToClose = Carbon::make($issue->createdAt)->diff($issue->closedAt);

            $issueInfo = (object) [
                'id' => $issue->id,
                'timeToClose' => Carbon::make('@0')->add($intervalToClose)->timestamp
            ];

            $total->closeTime->push($issueInfo->timeToClose);

            if ($intervalToClose < $oneHour) {
                $total->closedInLessThanOneHour;
            }

            if ($issue->author !== null) {
                $repositoryContributors->registerIssueAction($issue->author, ACTION_OPEN, $issueInfo);
            }

            if ($issue->closedBy !== null) {
                $repositoryContributors->registerIssueAction($issue->closedBy, ACTION_CLOSE, $issueInfo);
            }

            return $total;
        }, (object) [
            'issues' => 0,
            'open' => 0,
            'closed' => 0,
            'closedInLessThanOneHour' => 0,
            'closeTime' => collect()
        ]);

        $avgTimeToClose = Carbon::createFromTimestamp(
            floor($total->closeTime->median())
        )->diffAsCarbonInterval(
            Carbon::createFromTimestamp(0)
        );


        return [
            'total' => $total->issues,
            'open' => $total->open,
            'closed' => $total->closed,
            'closed_by_bot' => $total->closedByBot,
            'closed_less_than_one_hour' => $total->closedInLessThanOneHour,
            'avg_time_to_close' => $avgTimeToClose->forHumans()
        ];
    }

    private function makePullRequestsReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {

        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($name, $owner, $totalCount);

        $repositoryContributors->registerEndCursor('pullRequests', $repositoryPullRequests->getEndCursor());

        $oneHour = new DateInterval('PT1H');

        $total = $repositoryPullRequests->get()->reduce(function ($total, $pullRequest) use ($repositoryContributors, $oneHour) {
            $total->pullRequests++;

            if ($pullRequest->closed) {
                $total->closed++;

                if ($pullRequest->totalCommits <= 0) {
                    $total->closedWithoutCommits++;
                } else {
                    $total->commits += $pullRequest->totalCommits;
                }
            } else if ($pullRequest->merged) {
                $total->merged++;
            } else {
                $total->open++;
            }

            $intervalToClose = Carbon::make($pullRequest->createdAt)->diff($pullRequest->closedAt);
            $intervalToMerge = Carbon::make($pullRequest->createdAt)->diff($pullRequest->mergedAt);

            $pullRequestInfo = (object) [
                'id' => $pullRequest->id,
                'timeToClose' => Carbon::make('@0')->add($intervalToClose)->timestamp,
                'timeToMerge' => Carbon::make('@0')->add($intervalToMerge)->timestamp,
            ];

            $total->closeTime->push($pullRequestInfo->timeToClose);
            $total->mergeTime->push($pullRequestInfo->timeToMerge);

            if ($intervalToClose < $oneHour) {
                $total->closedInLessThanOneHour++;
            }

            if ($intervalToMerge < $oneHour) {
                $total->mergedInLessThanOneHour++;
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

            return $total;
        }, (object) [
            'pullRequests' => 0,
            'open' => 0,
            'closed' => 0,
            'merged' => 0,
            'closedWithoutCommits' => 0,
            'closedInLessThanOneHour' => 0,
            'mergedInLessThanOneHour' => 0,
            'closeTime' => collect(),
            'mergeTime' => collect(),
            'commits' => 0
        ]);

        $avgTimeToClose = Carbon::createFromTimestamp(
            floor($total->closeTime->median())
        )->diffAsCarbonInterval(
            Carbon::createFromTimestamp(0)
        );

        $avgTimeToMerge = Carbon::createFromTimestamp(
            floor($total->mergeTime->median())
        )->diffAsCarbonInterval(
            Carbon::createFromTimestamp(0)
        );

        return [
            'total' => $total->pullRequests,
            'open' => $total->open,
            'closed' => $total->closed,
            'merged' => $total->merged,
            'closed_without_commits' => $total->closedWithoutCommits,
            'closed_less_than_one_hour' => $total->closedInLessThanOneHour,
            'merged_less_than_one_hour' => $total->mergedInLessThanOneHour,
            'prc_closed_with_commits' => 100 - round($total->closedWithoutCommits / $total->pullRequests * 100, 2),
            'avg_commits_per_pr' => $repositoryPullRequests->get()->pluck('totalCommits')->median(),
            'avg_time_to_close' => $avgTimeToClose->forHumans(),
            'avg_time_to_merge' => $avgTimeToMerge->forHumans()
        ];
    }

    private function makeContributorsReport(string $name, string $owner, int $totalCount, GithubRepositoryContributors $repositoryContributors)
    {
        $repositoryBranches = $this->branchesService->getRepositoryBranches($name, $owner, $totalCount);

        $repositoryContributors->registerEndCursor('branches', $repositoryBranches->getEndCursor());
        $repositoryContributors->registerCommitEndCursors($repositoryBranches->getCommitEndCursors());

        $repositoryBranches->get()->each(function ($branch) use ($repositoryContributors) {
            $branch->commits->each(function ($commit) use ($branch, $repositoryContributors) {

                if ($commit->author !== null && $commit->changedFiles > 0) {

                    $timeToPush = $commit->pushedAt !== null ? Carbon::make('@0')->add(
                        Carbon::make($commit->committedAt)->diff($commit->pushedAt)
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
            'avg_files_per_commit' => floor($contributors->commits->pluck('changedFiles')->median()),
            'avg_lines_per_commit' => floor($contributors->commits->pluck('changedLines')->median()),
            'avg_lines_per_file_per_commit' => floor($contributors->commits->pluck('linesPerFile')->median()),
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

                return round($good_assignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_bad_assignees' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $contributors = $pullRequest->contributorsCollection;
                $bad_assignees = $repositoryContributors->getContributorsAssignedTo($pullRequest->id)->diff($contributors);

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return $bad_assignees->count() > 0 ? 100 : 0;
                }

                return round($bad_assignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_unexpected_contributors' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $contributors = $pullRequest->contributorsCollection;
                $unexpected_contributors = $contributors->diff($repositoryContributors->getContributorsAssignedTo($pullRequest->id));

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return 0;
                }

                return round($unexpected_contributors->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_good_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $good_reviewers = $reviewers->intersect($repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($good_reviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_bad_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $bad_reviewers = $repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id)->diff($reviewers);

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return $bad_reviewers->count() > 0 ? 100 : 0;
                }

                return round($bad_reviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_unexpected_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryContributors) {
                $reviewers = $pullRequest->reviewersCollection;
                $unexpected_reviewers = $reviewers->diff($repositoryContributors->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($unexpected_reviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_time_to_push' => $avgTimeToPush->forHumans(),
            'prc_new_code' => 0,
            'prc_rewrite_others_code' => 0,
            'prc_rewrite_own_code' => 0
        ];
    }
}
