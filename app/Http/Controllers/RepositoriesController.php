<?php

namespace App\Http\Controllers;

use App\User;
use App\Report;
use DateInterval;
use Carbon\Carbon;
use App\Repository;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\GitRepository;
use App\Helpers\GithubApiClient;
use Cz\Git\GitException;

use App\Services\GitCommitService;
use Illuminate\Support\Facades\Storage;
use App\Services\GithubRepositoryService;

use App\Helpers\GithubRepositoryActions;
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
    private $branchService;
    private $commitService;

    public function __construct()
    {
        $this->repositoryService = new GithubRepositoryService();
        $this->issueService = new GithubRepositoryIssueService();
        $this->pullRequestService = new GithubRepositoryPullRequestsService();
        $this->branchService = new GithubRepositoryBranchService();
        $this->commitService = new GitCommitService();
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

        // try {
        //     $repository = $this->getOrCreateRepository($request->name, $request->owner);

        //     $report = $this->getOrCreateReport($repository);

        //     return response()->json([
        //         'repo' => $repository,
        //         'issues' => $report->issues,
        //         'pullRequests' => $report->pull_requests,
        //         'contributors' => $report->contributors
        //     ]);
        // } catch (RepositoryNotFoundException $e) {
        //     return response()->json(['error' => 'Repository not found'], 404);
        // }

        $clonePath = storage_path('app/raw/' . $repository->id . '/clone');

        if (!is_dir($clonePath)) {
            mkdir($clonePath, 0777, true);
        }

        try {
            $cloned = GitRepository::cloneRepository($repository->url, $clonePath);
        } catch (GitException $e) {
            $cloned = new GitRepository($clonePath);
        }

        $cloned->checkout('master');

        $cloned->logPatches($repository->name . '.log');

        $path = $clonePath . '/' . $repository->name . '.log';

        $start = microtime(true);
        $commits = $this->commitService->process($path);
        $time_elapsed_secs = microtime(true) - $start;

        return response()->json(['commits' => $commits, 'time' => $time_elapsed_secs]);
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
            $repositoryActions = new GithubRepositoryActions();
            $repositoryMetrics = $this->repositoryService->getRepositoryMetrics($repository->name, $repository->owner);

            $issuesReport = $this->makeIssuesReport($repository->name, $repository->owner, $repositoryMetrics->issues->totalCount, $repositoryActions);
            $pullRequestsReport = $this->makePullRequestsReport($repository->name, $repository->owner, $repositoryMetrics->pullRequests->totalCount, $repositoryActions);
            $contributorsReport = $this->makeContributorsReport($repository->name, $repository->owner, $repositoryMetrics->branches->totalCount, $repositoryActions);

            $raw = $repositoryActions->get()->toJson();

            Storage::disk('raw')->put($repository->id . '/raw.json', $raw);

            $repository->raw = storage_path("app/raw/{$repository->id}/raw.json");
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

    private function makeIssuesReport(string $name, string $owner, int $totalCount, GithubRepositoryActions $repositoryActions)
    {
        $repositoryIssues = $this->issueService->getRepositoryIssues($name, $owner, $totalCount);

        $oneHour = new DateInterval('PT1H');

        $total = $repositoryIssues->get()->reduce(function ($total, $issue) use ($repositoryActions, $oneHour) {
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
                $repositoryActions->registerIssueAction($issue->author, ACTION_OPEN, $issueInfo);
            }

            if ($issue->closedBy !== null) {
                $repositoryActions->registerIssueAction($issue->closedBy, ACTION_CLOSE, $issueInfo);
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

    private function makePullRequestsReport(string $name, string $owner, int $totalCount, GithubRepositoryActions $repositoryActions)
    {

        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($name, $owner, $totalCount);

        $oneHour = new DateInterval('PT1H');

        $total = $repositoryPullRequests->get()->reduce(function ($total, $pullRequest) use ($repositoryActions, $oneHour) {
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
                $repositoryActions->registerPullRequestAction($pullRequest->author, ACTION_OPEN, $pullRequestInfo);
            }

            if ($pullRequest->closedBy !== null) {
                $repositoryActions->registerPullRequestAction($pullRequest->closedBy, ACTION_CLOSE, $pullRequestInfo);
            }

            if ($pullRequest->mergedBy !== null) {
                $repositoryActions->registerPullRequestAction($pullRequest->mergedBy, ACTION_MERGE, $pullRequestInfo);
            }

            foreach ($pullRequest->assignees as $assignee) {
                $repositoryActions->registerPullRequestAction($assignee, ACTION_ASSIGN, $pullRequestInfo);
            }

            foreach ($pullRequest->suggestedReviewers as $suggestedReviewer) {
                $repositoryActions->registerPullRequestAction($suggestedReviewer, ACTION_SUGGESTED_REVIEWER, $pullRequestInfo);
            }

            foreach ($pullRequest->reviewers as $reviewer) {
                $repositoryActions->registerPullRequestAction($reviewer, ACTION_REVIEW, $pullRequestInfo);
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

    private function makeContributorsReport(Repository $repository, int $totalCount, GithubRepositoryActions $repositoryActions)
    {
        $repositoryBranches = $this->branchService->getRepositoryBranches($repository->name, $repository->owner, $totalCount);

        $clonePath = storage_path('app/raw/' . $repository->id . '/clone');

        if (!is_dir($clonePath)) {
            mkdir($clonePath, 0777, true);
        }

        try {
            $cloned = GitRepository::cloneRepository($repository->url, $clonePath);
        } catch (GitException $e) {
            $cloned = new GitRepository($clonePath);
        }

        $cloned->checkout('master');

        $cloned->logPatches($repository->name . '.log');

        $path = $clonePath . '/' . $repository->name . '.log';

        $repositoryCommits = $this->commitService->process($path);

        $repositoryBranches->get()->each(function ($branch) use ($repositoryActions) {
        });

        $repositoryCommits->each(function ($commit) use ($repositoryActions) {

            if ($commit->author !== null && $commit->changedFiles > 0) {

                $commitInfo = (object) [
                    'id' => $commit->id,
                    'changedLines' => $commit->additions + $commit->deletions,
                    'changedFiles' => $commit->changedFiles,
                ];

                $repositoryActions->registerCommitAction($commit->author, ACTION_COMMIT_BRANCH, $commitInfo);

                foreach ($commit->pullRequests as $pullRequest) {
                    $commitInfo->pullRequest = $pullRequest;

                    $repositoryActions->registerCommitAction($commit->author, ACTION_COMMIT_PR, $commitInfo);
                }
            }
        });

        $contributors = $repositoryActions->get()->flatten(1)->reduce(function ($contributors, $contributor) {
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

        $pullRequests = $contributors->pullRequests->map(function ($pullRequest) use ($repositoryActions) {
            $pullRequest->contributorsCollection = $repositoryActions->getContributorsCommitedTo($pullRequest->id);
            $pullRequest->reviewersCollection = $repositoryActions->getContributorsReviewedTo($pullRequest->id);

            return $pullRequest;
        });

        return [
            'total' => $repositoryActions->get()->count(),
            'avg_files_per_commit' => floor($contributors->commits->pluck('changedFiles')->median()),
            'avg_lines_per_commit' => floor($contributors->commits->pluck('changedLines')->median()),
            'avg_lines_per_file_per_commit' => floor($contributors->commits->pluck('linesPerFile')->median()),
            'avg_pull_request_contributed' => $repositoryActions->get()->map(function ($contributor) {
                return $contributor->pullRequests->count();
            })->median(),
            'avg_prc_good_assignees' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $contributors = $pullRequest->contributorsCollection;
                $good_assignees = $contributors->intersect($repositoryActions->getContributorsAssignedTo($pullRequest->id));

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return 0;
                }

                return round($good_assignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_bad_assignees' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $contributors = $pullRequest->contributorsCollection;
                $bad_assignees = $repositoryActions->getContributorsAssignedTo($pullRequest->id)->diff($contributors);

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return $bad_assignees->count() > 0 ? 100 : 0;
                }

                return round($bad_assignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_unexpected_contributors' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $contributors = $pullRequest->contributorsCollection;
                $unexpected_contributors = $contributors->diff($repositoryActions->getContributorsAssignedTo($pullRequest->id));

                $totalContributors = $contributors->count();

                if ($totalContributors <= 0) {
                    return 0;
                }

                return round($unexpected_contributors->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_good_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $reviewers = $pullRequest->reviewersCollection;
                $good_reviewers = $reviewers->intersect($repositoryActions->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($good_reviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_bad_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $reviewers = $pullRequest->reviewersCollection;
                $bad_reviewers = $repositoryActions->getContributorsSuggestedForReviewTo($pullRequest->id)->diff($reviewers);

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return $bad_reviewers->count() > 0 ? 100 : 0;
                }

                return round($bad_reviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_unexpected_reviewers' => $pullRequests->map(function ($pullRequest) use ($repositoryActions) {
                $reviewers = $pullRequest->reviewersCollection;
                $unexpected_reviewers = $reviewers->diff($repositoryActions->getContributorsSuggestedForReviewTo($pullRequest->id));

                $totalReviewers = $reviewers->count();

                if ($totalReviewers <= 0) {
                    return 0;
                }

                return round($unexpected_reviewers->count() / $totalReviewers * 100, 2);
            })->median()
        ];
    }
}
