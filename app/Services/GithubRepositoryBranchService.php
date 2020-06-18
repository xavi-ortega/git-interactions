<?php

namespace App\Services;

use Exception;
use App\Helpers\GithubApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Helpers\ReportProgressManager;
use App\Helpers\GithubRepositoryBranches;

const MAX_BRANCHES = 100;
const MAX_COMMITS = 100;

class GithubRepositoryBranchService
{
    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepositoryBranches(string $name, string $owner, int $total): GithubRepositoryBranches
    {
        $repositoryBranches = new GithubRepositoryBranches();

        $manager = resolve(ReportProgressManager::class);

        $pages = floor($total / MAX_BRANCHES + 1);
        $lastPageCount = $total % MAX_BRANCHES;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            try {
                $paginatedBranches = $this->github->getRepositoryBranchesPaginated([
                    'name' => $name,
                    'owner' => $owner,
                    'first' => MAX_BRANCHES,
                    'after' => $after
                ]);

                $repositoryBranches->add(
                    $paginatedBranches->nodes
                );

                $after = $paginatedBranches->pageInfo->endCursor;
            } catch (Exception $e) {
            }

            $progress = $this->map($i, 1, $pages, 1, 50);
            $manager->setProgress($progress);

            Log::debug($i . ' of ' . $pages . ' pages of branches');
        }
        try {
            $paginatedBranches = $this->github->getRepositoryBranchesPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ]);

            $repositoryBranches->add(
                $paginatedBranches->nodes
            );

            $repositoryBranches->setEndCursor($paginatedBranches->pageInfo->endCursor);
        } catch (Exception $e) {
        }

        Log::debug($i . ' of ' . $pages . ' pages of branches');

        return $repositoryBranches;
    }

    private function map($x, $in_min, $in_max, $out_min, $out_max)
    {
        return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
