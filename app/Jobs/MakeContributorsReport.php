<?php

namespace App\Jobs;

use Exception;
use App\Report;
use Carbon\Carbon;
use App\Repository;
use Cz\Git\GitException;
use App\Events\ReportFailed;
use Illuminate\Bus\Queueable;
use App\Helpers\GitRepository;
use App\Services\GitCommitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Helpers\ReportProgressManager;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Helpers\GithubRepositoryActions;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\GithubContributorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\Constants\ReportProgressType;

class MakeContributorsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 2;

    private $repository;
    private $report;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Repository $repository, Report $report)
    {
        $this->repository = $repository;
        $this->report = $report;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(GitCommitService $commitService, GithubContributorService $contributorService)
    {
        // START PROGRESS
        $progress = $this->report->progress;

        $manager = resolve(ReportProgressManager::class);

        $manager->focusOn($progress);

        $manager->setStep(ReportProgressType::FETCHING_CONTRIBUTORS);

        // RETRIEVE BACKUP
        $rawPath = "{$this->repository->id}/raw.json";

        $raw = json_decode(Storage::disk('raw')->get($rawPath));

        $repositoryActions = new GithubRepositoryActions($raw);

        $clonePath = storage_path('app/raw/' . $this->repository->id . '/clone');

        if (!is_dir($clonePath)) {
            mkdir($clonePath, 0777, true);
        }

        try {
            $cloned = GitRepository::cloneRepository($this->repository->url, $clonePath);
        } catch (GitException $e) {
            $cloned = new GitRepository($clonePath);
        }

        $manager->setProgress(20);

        try {
            $cloned->checkout('master');
        } catch (Exception $e) {
            $cloned->checkout('main');
        }

        $manager->setProgress(21);

        $cloned->logPatches($this->repository->name . '.log');

        $manager->setProgress(24);

        try {
            $commitCount = $cloned->getCommitCount('master');
        } catch (Exception $e) {
            $commitCount = $cloned->getCommitCount('main');
        }

        $manager->setProgress(25);

        $path = $clonePath . '/' . $this->repository->name . '.log';

        $repositoryCommits = $commitService->process($commitCount, $path);

        $count = 1;

        $repositoryCommits->each(function ($commit) use ($contributorService, $repositoryActions, $count, $commitCount, $manager) {
            if (!$repositoryActions->get('contributors')->has($commit->author->email)) {
                Log::info('fetching contributor: ' . $commit->author->email . ' at ' . $commit->id);
                $contributor = $contributorService->getByCommit($this->repository->name, $this->repository->owner, $commit->id);
                $repositoryActions->registerContributor($contributor);
            }

            $progress = $this->map($count, 1, $commitCount, 91, 99);
            $manager->setProgress($progress);
            $count++;

            $repositoryActions->registerCommit($commit);
        });

        $actions = $repositoryActions->get();

        $contributorsWithUser = $actions->get('contributors')->filter(function ($contributor) {
            return (bool) $contributor->user;
        });

        $this->report->contributors()->create([
            'total' => $actions->get('contributors')->count(),
            'avg_files_per_commit' => floor($this->getFilesPerCommit($actions)->median()),
            'avg_lines_per_commit' => floor($this->getLinesPerCommit($actions)->median()),
            'avg_lines_per_file_per_commit' => floor($this->getLinesPerFilePerCommit($actions)->median()),
            'avg_pull_request_contributed' => floor($this->getPullRequestsContributed($contributorsWithUser, $actions)->median()),
            'avg_prc_good_assignees' => $this->getGoodAssignees($actions)->median(),
            'avg_prc_bad_assignees' => $this->getBadAssignees($actions)->median(),
            'avg_prc_unexpected_contributors' => $this->getUnexpectedContributors($actions)->median(),
            'avg_prc_good_reviewers' => $this->getGoodReviewers($actions)->median(),
            'avg_prc_bad_reviewers' => $this->getBadReviewers($actions)->median(),
            'avg_prc_unexpected_reviewers' => $this->getUnexpectedContributors($actions)->median()
        ]);

        // UPDATE BACKUP
        $raw = $repositoryActions->get()->toJson();

        Storage::disk('raw')->put($rawPath, $raw);

        // END PROGRESS
        $manager->setProgress(100);
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        // Without observer event
        $this->report->progress()->delete();

        event(new ReportFailed($this->report->id));

        $this->report->update(['status' => 'failed']);
    }

    private function getFilesPerCommit(Collection $actions): Collection
    {
        return $actions->get('commits')->map(function ($commit) {
            return $commit->diffs->pluck('newFile')->unique()->count();
        });
    }

    private function getLinesPerCommit(Collection $actions): Collection
    {
        return $actions->get('commits')->map(function ($commit) {
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
        });
    }

    private function getLinesPerFilePerCommit(Collection $actions): Collection
    {
        return $actions->get('commits')->map(function ($commit) {
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
        });
    }

    private function getPullRequestsContributed(Collection $contributorsWithUser, Collection $actions): Collection
    {
        return $contributorsWithUser->map(function ($contributor) use ($actions) {
            return $actions->get('pullRequests')->filter(function ($pullRequest) use ($contributor) {
                try {
                    return in_array($contributor->user->login, (array) $pullRequest->contributors);
                } catch (Exception $e) {
                    dd($pullRequest);
                }
            })->count();
        });
    }

    private function getGoodAssignees(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalContributors = count((array) $pullRequest->contributors);

            if ($totalContributors <= 0) {
                return 0;
            }

            $contributors = collect((array) $pullRequest->contributors);
            $goodAssignees = $contributors->intersect($pullRequest->assignees);

            return round($goodAssignees->count() / $totalContributors * 100, 2);
        });
    }

    private function getBadAssignees(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalContributors = count((array) $pullRequest->contributors);

            if ($totalContributors <= 0) {
                return count($pullRequest->assignees) > 0 ? 100 : 0;
            }

            $assignees = collect($pullRequest->assignees);
            $badAssignees = $assignees->diff((array) $pullRequest->contributors);

            return round($badAssignees->count() / $totalContributors * 100, 2);
        });
    }

    private function getUnexpectedContributors(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalContributors = count((array) $pullRequest->contributors);

            if ($totalContributors <= 0) {
                return 0;
            }

            $contributors = collect((array) $pullRequest->contributors);
            $unexpectedContributors = $contributors->diff($pullRequest->assignees);

            return round($unexpectedContributors->count() / $totalContributors * 100, 2);
        });
    }

    private function getGoodReviewers(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalReviewers = count($pullRequest->reviewers);

            if ($totalReviewers <= 0) {
                return 0;
            }

            $reviewers = collect($pullRequest->reviewers);
            $goodReviewers = $reviewers->intersect($pullRequest->suggestedReviewers);

            return round($goodReviewers->count() / $totalReviewers * 100, 2);
        });
    }

    private function getBadReviewers(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalReviewers = count($pullRequest->reviewers);

            if ($totalReviewers <= 0) {
                return count($pullRequest->suggestedReviewers) > 0 ? 100 : 0;
            }

            $suggestedReviewers = collect($pullRequest->suggestedReviewers);
            $badReviewers = $suggestedReviewers->diff($pullRequest->reviewers);

            return round($badReviewers->count() / $totalReviewers * 100, 2);
        });
    }

    private function getUnexpectedReviewers(Collection $actions): Collection
    {
        return $actions->get('pullRequests')->map(function ($pullRequest) {
            $totalReviewers = count($pullRequest->reviewers);

            if ($totalReviewers <= 0) {
                return 0;
            }

            $reviewers = collect($pullRequest->reviewers);
            $unexpectedReviewers = $reviewers->diff($pullRequest->suggestedReviewers);

            return round($unexpectedReviewers->count() / $totalReviewers * 100, 2);
        });
    }

    private function map($x, $in_min, $in_max, $out_min, $out_max)
    {
        return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
