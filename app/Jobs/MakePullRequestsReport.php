<?php

namespace App\Jobs;

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
use App\Helpers\GithubRepositoryContributors;

use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE, ACTION_MERGE, ACTION_ASSIGN, ACTION_SUGGESTED_REVIEWER, ACTION_REVIEW};

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

        $repositoryPullRequests = $this->pullRequestService->getRepositoryPullRequests($this->repository->name, $this->repository->owner, $this->totalPullRequests);

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

        $this->report->pull_requests()->create([
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
        ]);

        // UPDATE BACKUP
        $pointer->put('issue', $repositoryPullRequests->getEndCursor());

        $rawPointer = $pointer->toJSon();
        $raw = $repositoryContributors->get()->toJson();

        Storage::disk('raw')->put($pointerPath, $rawPointer);
        Storage::disk('raw')->put($rawPath, $raw);
    }
}
