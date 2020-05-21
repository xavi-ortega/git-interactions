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

    public function __construct(array $contributors = [])
    {
        $this->collection = collect($contributors);
    }

    public function registerIssueAction(string $contributorName, string $action, Object $issueInfo)
    {
        $contributor = $this->getOrCreateContributor($contributorName);

        $issue = $contributor->issues->get($issueInfo->id);

        if ($issue !== null) {
            switch ($action) {
                case ACTION_OPEN:
                    $issue->openedByHim = true;
                    break;

                case ACTION_CLOSE:
                    $issue->closedByHim = true;
                    $issue->timeToClose = $issueInfo->timeToClose;
                    break;
            }
        } else {
            $issue = (object) [
                'id' => $issueInfo->id,
                'openedByHim' => false,
                'closedByHim' => false,
                'timeToClose' => 0
            ];

            switch ($action) {
                case ACTION_OPEN:
                    $issue->openedByHim = true;
                    break;

                case ACTION_CLOSE:
                    $issue->closedByHim = true;
                    $issue->timeToClose = $issueInfo->timeToClose;
                    break;
            }
        }

        $contributor->issues->put($issueInfo->id, $issue);

        $this->collection->put($contributorName, $contributor);
    }

    public function registerPullRequestAction(string $contributorName, string $action, object $pullRequestInfo)
    {
        $contributor = $this->getOrCreateContributor($contributorName);

        $pullRequest = $contributor->pullRequests->get($pullRequestInfo->id);

        if ($pullRequest !== null) {
            switch ($action) {
                case ACTION_OPEN:
                    $pullRequest->openedByHim = true;
                    break;

                case ACTION_CLOSE:
                    $pullRequest->closedByHim = true;
                    $pullRequest->timeToClose = $pullRequestInfo->timeToClose;
                    break;

                case ACTION_MERGE:
                    $pullRequest->mergedByHim = true;
                    $pullRequest->timeToMerge = $pullRequestInfo->timeToMerge;
                    break;

                case ACTION_ASSIGN:
                    $pullRequest->assignedTo = true;
                    break;

                case ACTION_SUGGESTED_REVIEWER:
                    $pullRequest->suggestedReviewerTo = true;
                    break;

                case ACTION_REVIEW:
                    $pullRequest->reviewedByHim = true;
                    break;
            }
        } else {
            $pullRequest = (object) [
                'id' => $pullRequestInfo->id,
                'openedByHim'  => false,
                'closedByHim' => false,
                'mergedByHim' => false,
                'reviewedByHim' => false,
                'assignedTo' => false,
                'suggestedReviewerTo' => false,
                'timeToClose' => 0,
                'timeToMerge' => 0,
            ];

            switch ($action) {
                case ACTION_OPEN:
                    $pullRequest->openedByHim = true;
                    break;

                case ACTION_CLOSE:
                    $pullRequest->closedByHim = true;
                    $pullRequest->timeToClose = $pullRequestInfo->timeToClose;
                    break;

                case ACTION_MERGE:
                    $pullRequest->mergedByHim = true;
                    $pullRequest->timeToMerge = $pullRequestInfo->timeToMerge;
                    break;
                case ACTION_ASSIGN:
                    $pullRequest->assignedTo = true;
                    break;

                case ACTION_SUGGESTED_REVIEWER:
                    $pullRequest->suggestedReviewerTo = true;
                    break;

                case ACTION_REVIEW:
                    $pullRequest->reviewedByHim = true;
                    break;
            }
        }

        $contributor->pullRequests->put($pullRequestInfo->id, $pullRequest);

        $this->collection->put($contributorName, $contributor);
    }

    public function registerBranchAction(Object $branch)
    {
        $contributor = $this->getOrCreateContributor($contributorName);

        $contributor->commits->put($branch->name, $branch);

        $this->collection->put($contributorName, $contributor);
    }


    public function registerCommitAction(string $contributorName, string $action, Object $commitInfo)
    {
        $contributor = $this->getOrCreateContributor($contributorName);

        $commit = $contributor->commits->get($commitInfo->id);

        if ($commit !== null) {
            switch ($action) {
                case ACTION_COMMIT_PR:
                    $commit->pullRequest = $commitInfo->pullRequest;
                    break;

                case ACTION_COMMIT_BRANCH:
                    $commit->pullRequest = $commitInfo->branch;
                    break;
            }
        } else {
            $commit = (object) [
                'id' => $commitInfo->id,
                'pullRequest' => 0,
                'changedFiles' => $commitInfo->changedFiles,
                'changedLines' => $commitInfo->changedLines,
                'linesPerFile' => round($commitInfo->changedLines / $commitInfo->changedFiles),
            ];

            switch ($action) {
                case ACTION_COMMIT_PR:
                    $commit->pullRequest = $commitInfo->pullRequest;
                    break;

                case ACTION_COMMIT_BRANCH:
                    $commit->pullRequest = $commitInfo->branch;
                    break;
            }
        }

        $contributor->commits->put($commitInfo->id, $commit);

        $this->collection->put($contributorName, $contributor);
    }

    public function get(): Collection
    {
        return $this->collection->except('endingCursors');
    }

    public function getContributorsAssignedTo(string $pullRequestId): Collection
    {
        return $this->collection->except('endingCursors')->filter(function ($contributor) use ($pullRequestId) {
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            return $pullRequest !== null && $pullRequest->assignedTo;
        })->keys();
    }

    public function getContributorsCommitedTo(string $pullRequestId): Collection
    {
        return $this->collection->except('endingCursors')->filter(function ($contributor) use ($pullRequestId) {
            return $contributor->commits->some(function ($commit) use ($pullRequestId) {
                return $commit->pullRequest === $pullRequestId;
            });
        })->keys();
    }

    public function getContributorsSuggestedForReviewTo(string $pullRequestId): Collection
    {
        return $this->collection->except('endingCursors')->filter(function ($contributor) use ($pullRequestId) {
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            return $pullRequest !== null && $pullRequest->suggestedReviewerTo;
        })->keys();
    }

    public function getContributorsReviewedTo(string $pullRequestId): Collection
    {
        return $this->collection->except('endingCursors')->filter(function ($contributor) use ($pullRequestId) {
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            return $pullRequest !== null && $pullRequest->reviewedByHim;
        })->keys();
    }

    private function getOrCreateContributor(string $contributorName)
    {
        $contributor = $this->collection->get($contributorName);

        if ($contributor !== null) {
            return $contributor;
        }

        $contributor = (object) [
            'issues' => collect(),
            'pullRequests' => collect(),
            'commits' => collect(),
            'branches' => collect()
        ];

        $this->collection->put($contributorName, $contributor);

        return $contributor;
    }
}
