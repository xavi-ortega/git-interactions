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
use App\Helpers\ReportProgressManager;
use App\Services\GithubRepositoryIssueService;

class MakeIssuesReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $repository;
    private $report;
    private $totalIssues;
    private $issueService;

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
    public function handle(GithubRepositoryIssueService $issueService)
    {
        // START PROGRESS
        $progress = $this->report->progress;

        $manager = resolve(ReportProgressManager::class);

        $manager->focusOn($progress);

        $manager->setStep(ReportProgressType::FETCHING_ISSUES);

        // RETRIEVE OR CREATE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        if (Storage::disk('raw')->exists($pointerPath)) {
            $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        } else {
            $rawPointer = (object) [];
        }

        if (Storage::disk('raw')->exists($pointerPath)) {
            $raw = json_decode(Storage::disk('raw')->get($rawPath));
        } else {
            $raw = (object) [];
        }


        $pointer = collect($rawPointer);
        $repositoryActions = new GithubRepositoryActions($raw);

        $repositoryIssues = $issueService->getRepositoryIssues($this->repository->name, $this->repository->owner, $this->totalIssues);

        $oneHour = Carbon::make('@0')->add(new DateInterval('PT1H'));

        $repositoryIssues->get()->each([$repositoryActions, 'registerIssue']);

        $count = 1;

        $total = $repositoryActions->get('issues')->reduce(function ($total, $issue) use ($oneHour, $count, $manager) {
            $total->issues++;

            if ($issue->closed) {
                $total->closed++;
            } else {
                $total->open++;
            }

            $intervalToClose = Carbon::make($issue->createdAt)->diff($issue->closedAt);

            $closeTime = Carbon::make('@0')->add($intervalToClose);

            $total->closeTime->push($closeTime->timestamp);

            if ($closeTime < $oneHour) {
                $total->closedInLessThanOneHour;
            }

            $manager->setProgress($this->map($count, 1, $this->totalIssues, 91, 99));
            $count++;

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
        $raw = $repositoryActions->get()->toJson();

        Storage::disk('raw')->put($pointerPath, $rawPointer);
        Storage::disk('raw')->put($rawPath, $raw);

        $this->report->issues()->create([
            'total' => $total->issues,
            'open' => $total->open,
            'closed' => $total->closed,
            'closed_less_than_one_hour' => $total->closedInLessThanOneHour,
            'avg_time_to_close' => $avgTimeToClose->forHumans()
        ]);

        // SET BACKUP PATHS
        $this->repository->pointer = storage_path("app/raw/{$pointerPath}");
        $this->repository->raw = storage_path("app/raw/{$rawPath}");
        $this->repository->save();

        // END PROGRESS
        $manager->setProgress(100);
    }

    private function map($x, $in_min, $in_max, $out_min, $out_max)
    {
        return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
