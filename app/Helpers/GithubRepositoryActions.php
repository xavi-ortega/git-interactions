<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

const ACTION_OPEN = 'open';
const ACTION_CLOSE = 'close';
const ACTION_MERGE = 'merge';
const ACTION_ASSIGN = 'assign';
const ACTION_SUGGESTED_REVIEWER = 'suggested-reviewer';
const ACTION_REVIEW = 'review';
const ACTION_COMMIT_PR = 'commit-pr';
const ACTION_COMMIT_BRANCH = 'commit-branch';

class GithubRepositoryActions
{
    private $collection;

    public function __construct(array $actions = [])
    {
        if (count($actions) <= 0) {
            $actions = [
                'issues' => collect(),
                'pullRequests' => collect(),
                'branches' => collect(),
                'contributors' => collect(),
                'commits' => collect()
            ];
        }

        $this->collection = collect($actions);
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
