<?php

namespace App\Jobs;

use App\Report;
use Carbon\Carbon;
use App\Repository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\GithubRepositoryContributors;

class MakeContributorsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $repository;
    private $report;
    private $totalBranches;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Repository $repository, Report $report, string $totalBranches)
    {
        $this->repository = $repository;
        $this->report = $report;
        $this->totalBranches = $totalBranches;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // RETRIEVE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        $raw = json_decode(Storage::disk('raw')->get($rawPath));

        $pointer = collect($rawPointer);
        $repositoryContributors = new GithubRepositoryContributors($raw);

        $repositoryBranches = $this->branchService->getRepositoryBranches($this->repository->name, $this->repository->owner, $this->totalBranches);

        // NEW COMMITS PARSER

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

        $this->report->issues()->create([
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
            })->median()
        ]);

        // SET BACKUP PATHS
        $this->repository->pointer = storage_path("app/raw/{$pointerPath}");
        $this->repository->raw = storage_path("app/raw/{$rawPath}");
        $this->repository->save();
    }
}
