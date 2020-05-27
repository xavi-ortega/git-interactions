<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryActions
{
    private $collection;

    public function __construct(object $actions = null)
    {
        if ($actions === null || empty((array) $actions)) {
            $collection = collect([
                'issues' => collect(),
                'pullRequests' => collect(),
                'branches' => collect(),
                'contributors' => collect(),
                'commits' => collect()
            ]);
        } else {
            $collection = collect([
                'issues' => collect(isset($actions->issues) ? $actions->issues : []),
                'pullRequests' => collect(isset($actions->pullRequests) ? $actions->pullRequests : []),
                'branches' => collect(isset($actions->branches) ? $actions->branches : []),
                'contributors' => collect(isset($actions->contributors) ? $actions->contributors : []),
                'commits' => collect(isset($actions->commits) ? $actions->commits : [])
            ]);
        }

        $this->collection = $collection;
    }

    public function get(string $type = ''): Collection
    {
        if (empty($type)) {
            return $this->collection;
        } else {
            return $this->collection->get($type);
        }
    }

    public function getContributorsCommitedTo(string $pullRequestId): Collection
    {
        return $this->collection->filter(function ($contributor) use ($pullRequestId) {
            return $contributor->commits->some(function ($commit) use ($pullRequestId) {
                return $commit->pullRequest === $pullRequestId;
            });
        })->keys();
    }

    public function getContributorsReviewedTo(string $pullRequestId): Collection
    {
        return $this->collection->filter(function ($contributor) use ($pullRequestId) {
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            return $pullRequest !== null && $pullRequest->reviewedByHim;
        })->keys();
    }

    public function registerIssue(object $issue)
    {
        $this->collection->get('issues')->put($issue->id, $issue);
    }

    public function registerPullRequest(object $pullRequest)
    {
        $this->collection->get('pullRequests')->put($pullRequest->id, $pullRequest);
    }

    public function registerBranch(Object $branch)
    {
        $this->collection->get('branches')->put($branch->name, $branch);
    }

    public function registerCommit(object $commit)
    {
        $this->collection->get('commits')->put($commit->id, $commit);
    }

    public function registerContributor(object $contributor)
    {
        $this->collection->get('contributors')->put($contributor->email, $contributor);
    }
}
