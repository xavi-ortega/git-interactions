<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GithubRepositoryIssues
{
    private $collection;
    private $endCursor;

    public function __construct(array $issues = [])
    {
        $this->collection = collect(
            $this->formatIssues($issues)
        );
    }

    public function add(array $issues)
    {
        $this->collection = $this->collection->merge(
            $this->formatIssues($issues)
        );
    }

    public function setEndCursor($endCursor)
    {
        $this->endCursor = $endCursor;
    }

    public function get(): Collection
    {
        return $this->collection;
    }

    public function getEndCursor()
    {
        return $this->endCursor;
    }

    private function formatIssues(array $rawIssues)
    {
        return array_map(function ($issue) {
            $closedBy = array_map(function ($closedEvent) {
                return isset($closedEvent->actor) ? $closedEvent->actor->login : null;
            }, $issue->closedEvent->nodes);

            return (object) [
                'id' => $issue->id,
                'author' => isset($issue->author) ? $issue->author->login : null,
                'closed' => $issue->closed,
                'createdAt' => $issue->createdAt,
                'closedAt' => $issue->closedAt,
                'closedBy' => count($closedBy) > 0 ? $closedBy[0] : null,
                'assignees' => array_map(function ($assignee) {
                    return $assignee->login;
                },  $issue->assignees->nodes),
            ];
        }, $rawIssues);
    }
}
