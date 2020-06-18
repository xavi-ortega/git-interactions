<?php

namespace App\Services;

use Exception;
use App\Helpers\GithubApiClient;
use Illuminate\Support\Facades\Log;
use App\Helpers\ReportProgressManager;
use App\Helpers\GithubRepositoryPullRequests;

const MAX_PULL_REQUESTS = 90;

class GithubRepositoryPullRequestsService
{

    private $github;

    public function __construct()
    {
        $this->github = new GithubApiClient();
    }

    public function getRepositoryPullRequests(string $name, string $owner, int $total): GithubRepositoryPullRequests
    {
        $repositoryPullRequests = new GithubRepositoryPullRequests();

        $manager = resolve(ReportProgressManager::class);

        $pages = floor($total / MAX_PULL_REQUESTS + 1);
        $lastPageCount = $total % MAX_PULL_REQUESTS;

        $after = null;

        for ($i = 1; $i < $pages; $i++) {
            try {
                $paginatedPullRequests = $this->github->getRepositoryPullRequestsPaginated([
                    'name' => $name,
                    'owner' => $owner,
                    'first' => MAX_PULL_REQUESTS,
                    'after' => $after
                ]);

                $repositoryPullRequests->add(
                    $paginatedPullRequests->nodes
                );

                $after = $paginatedPullRequests->pageInfo->endCursor;
            } catch (Exception $e) {
            }

            $progress = $this->map($i, 1, $pages, 1, 90);
            $manager->setProgress($progress);

            Log::debug($i . ' of ' . $pages . ' pages of pullRequests');
        }

        try {
            $paginatedPullRequests = $this->github->getRepositoryPullRequestsPaginated([
                'name' => $name,
                'owner' => $owner,
                'first' => $lastPageCount,
                'after' => $after
            ]);

            $repositoryPullRequests->add(
                $paginatedPullRequests->nodes
            );

            $repositoryPullRequests->setEndCursor($paginatedPullRequests->pageInfo->endCursor);
        } catch (Exception $e) {
        }

        Log::debug($i . ' of ' . $pages . ' pages of pullRequests');

        $manager->setProgress(90);

        return $repositoryPullRequests;
    }

    private function map($x, $in_min, $in_max, $out_min, $out_max)
    {
        return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
