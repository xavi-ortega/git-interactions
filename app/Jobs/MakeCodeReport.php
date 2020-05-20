<?php

namespace App\Jobs;

use App\Report;
use App\Repository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\GithubRepositoryContributors;

class MakeCodeReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle()
    {
        // RETRIEVE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        $raw = json_decode(Storage::disk('raw')->get($rawPath));

        $pointer = collect($rawPointer);
        $repositoryContributors = new GithubRepositoryContributors($raw);

        // NEW CODE REPORT

        $this->report->code()->create([
            'branches' => 10,
            'branches_without_activity' => 5,
            'prc_new_code' => 35,
            'prc_rewrite_others_code' => 37.3,
            'prc_rewrite_own_code' => 27.7,
            'files' => [
                [
                    'name' => 'README.md',
                    'total' => 30
                ],
                [
                    'name' => 'main.ts',
                    'total' => 27
                ]
            ]
        ]);

        // SET BACKUP PATHS
        $this->repository->pointer = storage_path("app/raw/{$pointerPath}");
        $this->repository->raw = storage_path("app/raw/{$rawPath}");
        $this->repository->save();
    }
}
