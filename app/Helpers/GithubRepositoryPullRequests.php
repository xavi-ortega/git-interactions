<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryPullRequests
{
    private $collection;

    public function __construct(array $pullRequests = [])
    {
        $this->collection = collect(
            $this->formatPullRequests($pullRequests)
        );
    }

    public function add(array $pullRequests)
    {
        $this->collection = $this->collection->merge(
            $this->formatPullRequests($pullRequests)
        );
    }

    public function get(): Collection
    {
        return $this->collection;
    }

    private function formatPullRequests(array $rawPullRequests)
    {
        return array_map(function ($pullRequest) {
            $closedBy = array_map(function ($closedEvent) {
                return $closedEvent->actor->login;
            }, $pullRequest->closedEvent->nodes);

            return (object) [
                'author' => isset($pullRequest->author) ? $pullRequest->author->login : null,
                'closed' => $pullRequest->closed,
                'merged' => $pullRequest->merged,
                'createdAt' => $pullRequest->createdAt,
                'closedAt' => $pullRequest->closedAt,
                'closedBy' => count($closedBy) > 0 ? $closedBy[0] : null,
                'mergedAt' => $pullRequest->mergedAt,
                'mergedBy' => isset($pullRequest->mergedBy) ? $pullRequest->mergedBy->login : null,
                'assignees' => array_map(function ($assignee) {
                    return $assignee->login;
                },  $pullRequest->assignees->nodes),
                'reviews' => array_map(function ($review) {
                    $reaction = count($review->reactions->nodes) ? $review->reactions->nodes[0]->content : null;

                    return (object) [
                        'author' => $review->author->login,
                        'reaction' => $reaction
                    ];
                }, $pullRequest->reviews->nodes),
                'suggestedReviewers' => array_map(function ($suggestedReviewer) {
                    return $suggestedReviewer->reviewer->login;
                }, $pullRequest->suggestedReviewers),
                'totalCommits' => $pullRequest->commits->totalCount
            ];
        }, $rawPullRequests);
    }
}
