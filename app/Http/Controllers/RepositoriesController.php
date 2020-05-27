<?php

namespace App\Http\Controllers;

use App\User;
use Exception;
use App\Report;
use DateInterval;
use Carbon\Carbon;

use App\Repository;
use Cz\Git\GitException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Jobs\MakeCodeReport;

use Illuminate\Http\Request;
use App\Helpers\GitRepository;
use App\Jobs\MakeIssuesReport;

use App\Helpers\GithubApiClient;
use App\Services\GitCommitService;
use Illuminate\Support\Facades\Log;
use App\Jobs\MakeContributorsReport;
use App\Jobs\MakePullRequestsReport;
use Illuminate\Support\Facades\Storage;
use App\Helpers\GithubRepositoryActions;
use App\Services\GithubRepositoryService;
use App\Services\GithubContributorService;
use App\Services\GithubRepositoryIssueService;
use App\Exceptions\RepositoryNotFoundException;
use App\Services\GithubRepositoryBranchService;
use App\Services\GithubRepositoryPullRequestsService;
use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE, ACTION_MERGE, ACTION_ASSIGN, ACTION_COMMIT_BRANCH, ACTION_COMMIT_PR, ACTION_SUGGESTED_REVIEWER, ACTION_REVIEW};

const MAX_ISSUES = 100;
const IGNORED_FILES = [
    'package.json', 'package-lock.json'
];

class RepositoriesController extends Controller
{
    private $repositoryService;
    private $issueService;
    private $pullRequestService;
    private $branchService;
    private $commitService;
    private $contributorService;

    public function __construct()
    {
        $this->repositoryService = new GithubRepositoryService();
        $this->issueService = new GithubRepositoryIssueService();
        $this->pullRequestService = new GithubRepositoryPullRequestsService();
        $this->branchService = new GithubRepositoryBranchService();
        $this->commitService = new GitCommitService();
        $this->contributorService = new GithubContributorService();
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
        $user = User::find(1);

        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        $repository = $this->getOrCreateRepository($request->name, $request->owner);

        try {
            $start = microtime(true);

            $repository = $this->getOrCreateRepository($request->name, $request->owner);

            $report = $this->dispatchReport($user, $repository);

            $time_elapsed_secs = microtime(true) - $start;

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

        $repository = $this->getOrCreateRepository($request->name, $request->owner);

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

    private function dispatchReport(User $user, Repository $repository): Report
    {
        $repositoryMetrics = $this->repositoryService->getRepositoryMetrics($repository->name, $repository->owner);

        $report = $user->reports()->create([
            'repository_id' => $repository->id,
        ]);

        MakeIssuesReport::withChain([
            new MakePullRequestsReport($repository, $report, $repositoryMetrics->pullRequests->totalCount),
            new MakeContributorsReport($repository, $report),
            new MakeCodeReport($repository, $report, $repositoryMetrics->branches->totalCount)
        ])->dispatch($repository, $report, $repositoryMetrics->issues->totalCount);

        return $report;
    }

    private function makeIssuesReport(Repository $repository, int $totalCount, GithubRepositoryActions $repositoryActions)
    {
        $repositoryIssues = $this->issueService->getRepositoryIssues($repository->name, $repository->owner, $totalCount);

        $oneHour = new DateInterval('PT1H');

        $repositoryIssues->get()->each([$repositoryActions, 'registerIssue']);

        $total = $repositoryActions->get('issues')->reduce(function ($total, $issue) use ($oneHour) {
            $total->issues++;

            if ($issue->closed) {
                $total->closed++;
            } else {
                $total->open++;
            }

            $intervalToClose = Carbon::make($issue->createdAt)->diff($issue->closedAt);

            $closeTime = Carbon::make('@0')->add($intervalToClose)->timestamp;

            $total->closeTime->push($closeTime);

            if ($intervalToClose < $oneHour) {
                $total->closedInLessThanOneHour;
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
            'closed_less_than_one_hour' => $total->closedInLessThanOneHour,
            'avg_time_to_close' => $avgTimeToClose->forHumans()
        ];
    }

    private function makePullRequestsReport(Repository $repository, int $totalCount, GithubRepositoryActions $repositoryActions)
    {

        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($repository->name, $repository->owner, $totalCount);

        $oneHour = new DateInterval('PT1H');

        $repositoryPullRequests->get()->each([$repositoryActions, 'registerPullRequest']);

        $total = $repositoryActions->get('pullRequests')->reduce(function ($total, $pullRequest) use ($oneHour) {
            $total->pullRequests++;

            if ($pullRequest->closed) {
                $total->closed++;

                if ($pullRequest->commits <= 0) {
                    $total->closedWithoutCommits++;
                } else {
                    $total->commits += $pullRequest->commits;
                }
            } else {
                $total->open++;
            }

            if ($pullRequest->merged) {
                $total->merged++;
            }

            $intervalToClose = Carbon::make($pullRequest->createdAt)->diff($pullRequest->closedAt);
            $intervalToMerge = Carbon::make($pullRequest->createdAt)->diff($pullRequest->mergedAt);

            $closeTime = Carbon::make('@0')->add($intervalToClose)->timestamp;
            $mergeTime = Carbon::make('@0')->add($intervalToMerge)->timestamp;

            $total->closeTime->push($closeTime);
            $total->mergeTime->push($mergeTime);

            if ($intervalToClose < $oneHour) {
                $total->closedInLessThanOneHour++;
            }

            if ($intervalToMerge < $oneHour) {
                $total->mergedInLessThanOneHour++;
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
            'avg_commits_per_pr' => $repositoryPullRequests->get()->pluck('commits')->median(),
            'avg_time_to_close' => $avgTimeToClose->forHumans(),
            'avg_time_to_merge' => $avgTimeToMerge->forHumans()
        ];
    }

    private function makeContributorsReport(Repository $repository, GithubRepositoryActions $repositoryActions)
    {
        Log::debug('cloning repo');

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

        $repositoryCommits->each(function ($commit) use ($repository, $repositoryActions) {
            if (!$repositoryActions->get('contributors')->has($commit->author->email)) {
                Log::info('getting contributor: ' . $commit->author->email . ' at ' . $commit->id);
                $contributor = $this->contributorService->getByCommit($repository->name, $repository->owner, $commit->id);
                $repositoryActions->registerContributor($contributor);
            }

            $repositoryActions->registerCommit($commit);
        });

        $actions = $repositoryActions->get();

        $contributorsWithUser = $actions->get('contributors')->filter(function ($contributor) {
            return (bool) $contributor->user;
        });

        return [
            'total' => $actions->get('contributors')->count(),
            'avg_files_per_commit' => floor($actions->get('commits')->map(function ($commit) {
                return $commit->diffs->pluck('newFile')->unique()->count();
            })->median()),
            'avg_lines_per_commit' => floor($actions->get('commits')->map(function ($commit) {
                return $commit->diffs->map(function ($diff) {
                    if ($diff->patches->isNotEmpty()) {
                        return $diff->patches->map(function ($patch) {
                            if ($patch->newCount < $patch->oldCount) {
                                return $patch->oldCount - $patch->newCount;
                            } else {
                                return $patch->newCount;
                            }
                        })->sum();
                    } else {
                        return 0;
                    }
                })->sum();
            })->median()),
            'avg_lines_per_file_per_commit' => floor($actions->get('commits')->map(function ($commit) {
                $totalFiles = $commit->diffs->whereNotNull('newFile')->pluck('newFile')->unique()->count();

                if ($totalFiles === 0) {

                    return 0;
                }

                $totalLines = $commit->diffs->whereNotNull('newFile')->map(function ($diff) {
                    return $diff->patches->map(function ($patch) {
                        if ($patch->newCount < $patch->oldCount) {
                            return $patch->oldCount - $patch->newCount;
                        } else {
                            return $patch->newCount;
                        }
                    })->sum();
                })->sum();

                return $totalLines / $totalFiles;
            })->median()),
            'avg_pull_request_contributed' => floor($contributorsWithUser->map(function ($contributor) use ($actions) {
                return $actions->get('pullRequests')->filter(function ($pullRequest) use ($contributor) {
                    return in_array($contributor->user->login, $pullRequest->contributors);
                })->count();
            })->median()),
            'avg_prc_good_assignees' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalContributors = count($pullRequest->contributors);

                if ($totalContributors <= 0) {
                    return 0;
                }

                $contributors = collect($pullRequest->contributors);
                $goodAssignees = $contributors->intersect($pullRequest->assignees);

                return round($goodAssignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_bad_assignees' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalContributors = count($pullRequest->contributors);

                if ($totalContributors <= 0) {
                    return count($pullRequest->assignees) > 0 ? 100 : 0;
                }

                $assignees = collect($pullRequest->assignees);
                $badAssignees = $assignees->diff($pullRequest->contributors);

                return round($badAssignees->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_unexpected_contributors' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalContributors = count($pullRequest->contributors);

                if ($totalContributors <= 0) {
                    return 0;
                }

                $contributors = collect($pullRequest->contributors);
                $unexpectedContributors = $contributors->diff($pullRequest->assignees);

                return round($unexpectedContributors->count() / $totalContributors * 100, 2);
            })->median(),
            'avg_prc_good_reviewers' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalReviewers = count($pullRequest->reviewers);

                if ($totalReviewers <= 0) {
                    return 0;
                }

                $reviewers = collect($pullRequest->reviewers);
                $goodReviewers = $reviewers->intersect($pullRequest->suggestedReviewers);

                return round($goodReviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_bad_reviewers' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalReviewers = count($pullRequest->reviewers);

                if ($totalReviewers <= 0) {
                    return count($pullRequest->suggestedReviewers) > 0 ? 100 : 0;
                }

                $suggestedReviewers = collect($pullRequest->suggestedReviewers);
                $badReviewers = $suggestedReviewers->diff($pullRequest->reviewers);

                return round($badReviewers->count() / $totalReviewers * 100, 2);
            })->median(),
            'avg_prc_unexpected_reviewers' => $actions->get('pullRequests')->map(function ($pullRequest) {
                $totalReviewers = count($pullRequest->reviewers);

                if ($totalReviewers <= 0) {
                    return 0;
                }

                $reviewers = collect($pullRequest->reviewers);
                $unexpectedReviewers = $reviewers->diff($pullRequest->suggestedReviewers);

                return round($unexpectedReviewers->count() / $totalReviewers * 100, 2);
            })->median()
        ];
    }

    private function makeCodeReport(Repository $repository, int $totalCount, GithubRepositoryActions $repositoryActions)
    {
        $repositoryBranches = $this->branchService->getRepositoryBranches($repository->name, $repository->owner, $totalCount);

        $repositoryBranches->get()->each([$repositoryActions, 'registerBranch']);

        $actions = $repositoryActions->get();

        $oneMonth = new DateInterval('P1M');

        $files = collect();

        $actions->get('commits')->each(function ($commit) use ($files) {
            $commit->diffs->each(function ($diff) use ($files, $commit) {
                $oldFile = $diff->oldFile;
                $fileName = $diff->newFile;

                if (!Str::contains($fileName, IGNORED_FILES)) {
                    if ($oldFile !== $fileName) {
                        // RENAMED FILE
                        $file = $files->pull($oldFile);

                        if ($file) {
                            $file->renames->push((object) [
                                'old' => $oldFile,
                                'new' => $fileName
                            ]);
                        }
                    } else if ($files->has($fileName)) {
                        $file = $files->get($fileName);
                    } else {
                        $file = (object) [
                            'name' => $fileName,
                            'owner' => $commit->author,
                            'patches' => collect(),
                            'renames' => collect()
                        ];
                    }

                    if ($file) {
                        $patches = $diff->patches->map(function ($patch) use ($commit) {
                            $patch->owner = $commit->author;
                            return $patch;
                        });

                        $file->patches = $file->patches->merge($patches);

                        $files->put($fileName, $file);
                    }
                }
            });
        });

        $code = $files->reduce(function ($total, $file) {
            $fileMap = collect();

            $file->patches->each(function ($patch) use ($total, $fileMap) {
                $startLine = $patch->newStart;
                $endLine = $startLine + $patch->newCount;
                $author = $patch->owner->email;

                for ($line = $startLine; $line < $endLine; $line++) {
                    $total->lines++;

                    if ($fileMap->has($line)) {
                        if ($fileMap->get($line) === $author) {
                            $total->rewriteOwn++;
                        } else {
                            $total->rewriteOthers++;
                        }
                    } else {
                        $fileMap->put($line, $author);
                        $total->new++;
                    }
                }
            });

            return $total;
        }, (object) [
            'lines' => 0,
            'new' => 0,
            'rewriteOwn' => 0,
            'rewriteOthers' => 0
        ]);

        return [
            'branches' => $actions->get('branches')->count(),
            'branches_without_activity' => $actions->get('branches')->filter(function ($branch) use ($oneMonth) {
                return $branch->lastActivity < $oneMonth;
            })->count(),
            'prc_new_code' => round($code->new / $code->lines * 100, 2),
            'prc_rewrite_others_code' => round($code->rewriteOthers / $code->lines * 100, 2),
            'prc_rewrite_own_code' => round($code->rewriteOwn / $code->lines * 100, 2),
            'top_changed_files' => $files->sort(function ($a, $b) {
                return $b->patches->count() - $a->patches->count();
            })->take(10)
        ];
    }
}
