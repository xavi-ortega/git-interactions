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
use const App\Helpers\{ACTION_OPEN, ACTION_CLOSE};

class MakeIssuesReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $repository;
    private $report;
    private $totalIssues;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Repository $repository, Report $report, string $totalIssues)
    {
        $this->repository = $repository;
        $this->report = $report;
        $this->totalIssues = $totalIssues;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // RETRIEVE OR CREATE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        if (Storage::disk('raw')->exists($pointerPath)) {
            $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        } else {
            $rawPointer = [];
        }

        if (Storage::disk('raw')->exists($pointerPath)) {
            $raw = json_decode(Storage::disk('raw')->get($rawPath));
        } else {
            $raw = [];
        }


        $pointer = collect($rawPointer);
        $repositoryContributors = new GithubRepositoryContributors($raw);

        $repositoryIssues = $this->issueService->getRepositoryIssues($this->repository->name, $this->repository->owner, $this->totalIssues);

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

        // UPDATE BACKUP
        $pointer->put('issue', $repositoryIssues->getEndCursor());

        $rawPointer = $pointer->toJSon();
        $raw = $repositoryContributors->get()->toJson();

        Storage::disk('raw')->put($pointerPath, $rawPointer);
        Storage::disk('raw')->put($rawPath, $raw);

        $this->report->issues()->create([
            'total' => $total->issues,
            'open' => $total->open,
            'closed' => $total->closed,
            'closed_by_bot' => $total->closedByBot,
            'closed_less_than_one_hour' => $total->closedInLessThanOneHour,
            'avg_time_to_close' => $avgTimeToClose->forHumans()
        ]);

        // SET BACKUP PATHS
        $this->repository->pointer = storage_path("app/raw/{$pointerPath}");
        $this->repository->raw = storage_path("app/raw/{$rawPath}");
        $this->repository->save();
    }
}
