<?php

namespace App\Jobs;

use App\Report;
use DateInterval;
use Carbon\Carbon;
use App\Repository;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Helpers\GithubRepositoryActions;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\Constants\ReportProgressType;
use App\Services\GithubRepositoryBranchService;

const IGNORED_FILES = [
    'package.json', 'package-lock.json'
];

class MakeCodeReport implements ShouldQueue
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
    public function handle(GithubRepositoryBranchService $branchService)
    {
        // START PROGRESS
        $progress = $this->report->progress();

        $progress->update([
            'type' => ReportProgressType::FETCHING_CODE,
            'progress' => 0
        ]);

        // RETRIEVE BACKUP
        $pointerPath = "{$this->repository->id}/pointer.json";
        $rawPath = "{$this->repository->id}/raw.json";

        $rawPointer = json_decode(Storage::disk('raw')->get($pointerPath));
        $raw = json_decode(Storage::disk('raw')->get($rawPath));

        $pointer = collect($rawPointer);
        $repositoryActions = new GithubRepositoryActions($raw);

        $repositoryBranches = $branchService->getRepositoryBranches($this->repository->name, $this->repository->owner, $this->totalBranches);

        $repositoryBranches->get()->each([$repositoryActions, 'registerBranch']);

        $actions = $repositoryActions->get();

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
                                'new' => $fileName,
                                'date' => Carbon::make($commit->date)->diffForHumans()
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

        $oneMonth = Carbon::make('1 month ago');

        $this->report->code()->create([
            'branches' => $actions->get('branches')->map(function ($branch) use ($oneMonth) {
                $branch->active = Carbon::make($branch->lastActivity) > $oneMonth;
                return $branch;
            }),
            'prc_new_code' => round($code->new / $code->lines * 100, 2),
            'prc_rewrite_others_code' => round($code->rewriteOthers / $code->lines * 100, 2),
            'prc_rewrite_own_code' => round($code->rewriteOwn / $code->lines * 100, 2),
            'top_changed_files' => $files->sort(function ($a, $b) {
                return $b->patches->count() - $a->patches->count();
            })->take(10)->map(function ($file) {
                $file->contributors = $file->patches->pluck('owner.email')->unique();
                return $file;
            })
        ]);

        // UPDATE BACKUP
        $pointer->put('branch', $repositoryBranches->getEndCursor());

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
