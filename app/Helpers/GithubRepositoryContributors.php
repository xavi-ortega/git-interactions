<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

const ACTION_OPEN = 'open';
const ACTION_CLOSE = 'close';
const ACTION_MERGE = 'merge';
const ACTION_ASSIGN = 'assign';
const ACTION_SUGGESTED_REVIEWER = 'suggested-reviewer';
const ACTION_REVIEW = 'review';

class GithubRepositoryContributors
{
    private $collection;

    public function __construct(array $contributors = [])
    {
        $this->collection = collect($contributors);
    }

    public function get(): Collection
    {
        return $this->collection;
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

    public function getContributorsAssigned()
    {
        return $this->collection->keys()->reduce(function ($contributors, $contributorName) {
            $contributor = $this->collection->get($contributorName);

            $assignedSomewhere = $contributor->pullRequests->some(function ($_, $pullRequest) {
                return $pullRequest->assignedTo;
            });

            if ($assignedSomewhere) {
                $contributors[] = $contributorName;
            }

            return $contributors;
        });
    }

    public function getContributorsReviewed()
    {
        return $this->collection->keys()->reduce(function ($contributors, $contributorName) {
            $contributor = $this->collection->get($contributorName);

            $reviewedSomewhere = $contributor->pullRequests->some(function ($_, $pullRequest) {
                return $pullRequest->reviewedByHim;
            });

            if ($reviewedSomewhere) {
                $contributors[] = $contributorName;
            }

            return $contributors;
        });
    }

    public function getContributorsSuggestedForReview()
    {
        return $this->collection->keys()->reduce(function ($contributors, $contributorName) {
            $contributor = $this->collection->get($contributorName);

            $suggestedSomewhere = $contributor->pullRequests->some(function ($_, $pullRequest) {
                return $pullRequest->suggestedReviewerTo;
            });

            if ($suggestedSomewhere) {
                $contributors[] = $contributorName;
            }

            return $contributors;
        });
    }

    public function getContributorsAssignedTo(string $pullRequestId)
    {
        return $this->collection->keys()->reduce(function ($contributors, $contributorName) use ($pullRequestId) {
            $contributor = $this->collection->get($contributorName);
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            if ($pullRequest->assignedTo) {
                $contributors[] = $contributorName;
            }

            return $contributors;
        });
    }

    public function getContributorsReviewedTo(string $pullRequestId)
    {
        return $this->collection->keys()->reduce(function ($contributors, $contributorName) use ($pullRequestId) {
            $contributor = $this->collection->get($contributorName);
            $pullRequest = $contributor->pullRequests->get($pullRequestId);

            if ($pullRequest->reviewedByHim) {
                $contributors[] = $contributorName;
            }

            return $contributors;
        });
    }

    private function getOrCreateContributor(string $contributorName)
    {
        $contributor = $this->collection->get($contributorName);

        if ($contributor !== null) {
            return $contributor;
        }

        $contributor = (object) [
            'issues' => collect(),
            'pullRequests' => collect()
        ];

        $this->collection->put($contributorName, $contributor);

        return $contributor;
    }
}
