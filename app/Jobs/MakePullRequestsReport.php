<?php

namespace App\Jobs;

use App\Helpers\Constants\ReportProgressType;
use App\Report;
use DateInterval;
use Carbon\Carbon;
use App\Repository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\GithubRepositoryActions;
use App\Services\GithubRepositoryPullRequestsService;

class MakePullRequestsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $repository;
    private $report;
    private $totalPullRequests;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Repository $repository, Report $report, string $totalPullRequests)
    {
        $this->repository = $repository;
        $this->report = $report;
        $this->totalPullRequests = $totalPullRequests;
        $this->pullRequestService = new GithubRepositoryPullRequestsService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(GithubRepositoryPullRequestsService $pullRequestService)
    {
        // START PROGRESS
        $progress = $this->report->progress();

        $progress->update([
            'type' => ReportProgressType::FETCHING_PULL_REQUESTS,
            'progress' => 0
        ]);

        // RETRIEVE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        $raw = json_decode(Storage::disk('raw')->get($rawPath));

        $pointer = collect($rawPointer);
        $repositoryActions = new GithubRepositoryActions($raw);

        $repositoryPullRequests = $pullRequestService->getRepositoryPullRequests($this->repository->name, $this->repository->owner, $this->totalPullRequests);

        $oneHour = Carbon::make('@0')->add(new DateInterval('PT1H'));

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

            $closeTime = Carbon::make('@0')->add($intervalToClose);
            $mergeTime = Carbon::make('@0')->add($intervalToMerge);

            $total->closeTime->push($closeTime->timestamp);
            $total->mergeTime->push($mergeTime->timestamp);

            if ($closeTime < $oneHour) {
                $total->closedInLessThanOneHour++;
            }

            if ($mergeTime < $oneHour) {
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

        $this->report->pull_requests()->create([
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
        ]);

        // UPDATE BACKUP
        $pointer->put('pullRequest', $repositoryPullRequests->getEndCursor());

        $rawPointer = $pointer->toJSon();
        $raw = $repositoryActions->get()->toJson();

        Storage::disk('raw')->put($pointerPath, $rawPointer);
        Storage::disk('raw')->put($rawPath, $raw);

        // END PROGRESS
        $progress->update([
            'progress' => 100
        ]);
    }
}
