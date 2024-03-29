<?php

namespace App\Helpers;

use App\Exceptions\RepositoryNotFoundException;
use GraphQL\{Client, Query, Variable};
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

/**
 * GithubApiClient
 *
 * Wrapper around GraphQL Client to make requests to Github API
 */
class GithubApiClient
{
    /**
     * graphql client for queries
     *
     * @var GraphQL\Client
     */
    private $client;

    /**
     * number of tries of every failed request
     *
     * @var int
     */
    private $tries;





    public function __construct()
    {
        $this->client = new Client(
            'https://api.github.com/graphql',
            [
                'Authorization' => 'Bearer ' . env('GITHUB_ACCESS_TOKEN'),
                'Accept' => ['application/vnd.github.merge-info-preview+json', 'application/vnd.github.starfox-preview+json'],
            ]

        );

        $this->tries = 5;
    }

    /**
     * Gets Github Api rate limit
     *
     * cost: 0
     *
     * @return object
     */
    public function getRateLimit(): object
    {
        $query = (new Query('rateLimit'))->setSelectionSet([
            'limit',
            'cost',
            'remaining',
            'resetAt'
        ]);

        return $this->run($query)->getData();
    }

    /**
     * Get repository info
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryInfo(array $params): object
    {
        $query = (new Query('repository'))
            ->setVariables(
                [
                    new Variable('name', 'String', true),
                    new Variable('owner', 'String', true)
                ]
            )
            ->setArguments(['name' => '$name', 'owner' => '$owner'])
            ->setSelectionSet(
                [
                    'name',
                    (new Query('owner'))->setSelectionSet([
                        'url',
                        'login'
                    ]),
                    'description',
                    'url'
                ]
            );
        $repositoryInfo = $this->run($query, $params);

        if ($repositoryInfo === null) {
            throw new RepositoryNotFoundException();
        }

        return $repositoryInfo->getData()->repository;
    }

    /**
     * Get repository totals
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryMetrics(array $params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!){
            repository(owner: \$owner, name: \$name) {
                issues (first: 1) {
                    totalCount
                },
                pullRequests( first: 1) {
                    totalCount
                },
                branches: refs(first: 1, refPrefix: "refs/heads/") {
                    totalCount
                }
            }
        }
        QUERY;
        return $this->runRaw($query, $params)->getData()->repository;
    }

    /**
     * Get repository issues
     *
     * cost: 1
     *
     * @param array $params
     * @return array
     */
    public function getRepositoryIssues(array $params): array
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!) {
            repository(owner: \$owner, name: \$name) {
                issues(first: \$first) {
                    nodes {
                        id,
                        author {
                            login
                        },
                        closed,
                        createdAt,
                        closedAt,
                        assignees (first: 10) {
                            nodes {
                                login
                            }
                        },
                        closedEvent: timelineItems(last: 1, itemTypes: CLOSED_EVENT) {
                            nodes {
                                ... on ClosedEvent {
                                    actor {
                                        login
                                    }
                                }
                            }
                        }
                    }
                },
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->issues->nodes;
    }

    /**
     * Get repository issues with pagination
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryIssuesPaginated($params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!, \$after: String = null) {
            repository(owner: \$owner, name: \$name) {
                issues(first: \$first, after: \$after) {
                    pageInfo {
                        endCursor
                    },
                    nodes {
                        id,
                        author {
                            login
                        },
                        closed,
                        createdAt,
                        closedAt,
                        assignees (first: 10) {
                            nodes {
                                login
                            }
                        },
                        closedEvent: timelineItems(last: 1, itemTypes: CLOSED_EVENT) {
                            nodes {
                                ... on ClosedEvent {
                                    actor {
                                        login
                                    }
                                }
                            }
                        }
                    }
                },
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->issues;
    }

    /**
     * Gets repository pull requests
     *
     * cost: 1
     *
     * @param array $params
     * @return array
     */
    public function getRepositoryPullRequests(array $params): array
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!) {
            repository(owner: \$owner, name: \$name) {
                pullRequests (first: \$first) {
                    nodes {
                        id,
                        author {
                            login
                        },
                        closed,
                        merged,
                        createdAt,
                        closedAt,
                        mergedAt,
                        mergedBy {
                            login
                        },

                        closedEvent: timelineItems(last: 1, itemTypes: CLOSED_EVENT) {
                            nodes {
                                ... on ClosedEvent {
                                    actor {
                                        login
                                    }
                                }
                            }
                        },

                        assignees (first: 10) {
                            nodes {
                                login
                            }
                        },

                        commits(first: 100) {
                            totalCount,
                            nodes {
                                commit {
                                    author {
                                        user {
                                            login,
                                            email
                                        }
                                    }
                                }
                            }
                        },

                        suggestedReviewers {
                            reviewer {
                                login
                            }
                        },

                        reviews (first: 10) {
                            nodes {
                                author {
                                    login
                                }
                            }
                        }
                    }

                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->pullRequests->nodes;
    }

    /**
     * Gets repository pull requests paginated
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryPullRequestsPaginated(array $params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!, \$after: String = null) {
            repository(owner: \$owner, name: \$name) {
                pullRequests (first: \$first, after: \$after) {
                    pageInfo {
                        endCursor
                    },
                    nodes {
                        id,
                        author {
                            login
                        },
                        closed,
                        merged,
                        createdAt,
                        closedAt,
                        mergedAt,
                        mergedBy {
                            login
                        },

                        closedEvent: timelineItems(last: 1, itemTypes: CLOSED_EVENT) {
                            nodes {
                                ... on ClosedEvent {
                                    actor {
                                        login
                                    }
                                }
                            }
                        },

                        assignees (first: 10) {
                            nodes {
                                login
                            }
                        },

                        commits(first: 100) {
                            totalCount,
                            nodes {
                                commit {
                                    author {
                                        user {
                                            login
                                        }
                                    }
                                }
                            }
                        },

                        suggestedReviewers {
                            reviewer {
                                login
                            }
                        },

                        reviews (first: 10) {
                            nodes {
                                author {
                                    login
                                }
                            }
                        }
                    }

                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->pullRequests;
    }

    /**
     * Gets repository branches
     *
     * cost: 1
     *
     * @param array $params
     * @return array
     */
    public function getRepositoryBranches(array $params): array
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!) {
            repository(owner: \$owner, name: \$name) {
                branches: refs(first: \$first, refPrefix: "refs/heads/") {
                    nodes {
                        name,
                        commits: target {
                            ... on Commit {
                                history (first: 1) {
                                    totalCount,
                                    nodes {
                                      committedDate
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->branches->nodes;
    }

    /**
     * Gets repository branches paginated
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryBranchesPaginated(array $params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!, \$after: String = null) {
            repository(owner: \$owner, name: \$name) {
                branches: refs(first: \$first, refPrefix: "refs/heads/", after: \$after) {
                    pageInfo {
                        endCursor
                    },
                    nodes {
                        name,
                        commits: target {
                            ... on Commit {
                                history (first: 1) {
                                    totalCount,
                                    nodes {
                                      committedDate
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->branches;
    }

    /**
     * Gets repository commits by branch
     *
     * cost: 1
     *
     * @param array $params
     * @return array
     */
    public function getRepositoryCommitsByBranch(array $params): array
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$branch: String!, \$first: Int!) {
            repository(owner: \$owner, name: \$name) {
                branch: ref(qualifiedName: \$branch) {
                    commits: target {
                        ... on Commit {
                            history(first: \$first) {
                                nodes {
                                    oid,
                                    url,
                                    changedFilesIfAvailable,
                                    additions,
                                    deletions,
                                    committedDate,
                                    author {
                                        name
                                        user {
                                            login
                                        }
                                    },
                                    associatedPullRequests(first: 5) {
                                        nodes {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->branch->commits->history->nodes;
    }

    /**
     * Gets repository commits by branch paginated
     *
     * cost: 1
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryCommitsByBranchPaginated(array $params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$branch: String!, \$first: Int!, \$after: String = null) {
            repository(owner: \$owner, name: \$name) {
                branch: ref(qualifiedName: \$branch) {
                    commits: target {
                        ... on Commit {
                            history(first: \$first, after: \$after) {
                                pageInfo {
                                    endCursor
                                },
                                nodes {
                                    oid,
                                    url,
                                    changedFilesIfAvailable ,
                                    additions,
                                    deletions,
                                    committedDate,
                                    author {
                                        name
                                        user {
                                            login
                                        }
                                    },
                                    associatedPullRequests(first: 5) {
                                        nodes {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->branch->commits->history;
    }

    /**
     * Gets repository commit by id and returns author info
     *
     * @param array $params
     * @return object
     */
    public function getRepositoryCommitById(array $params): object
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$oid: GitObjectID!){
            repository(owner: \$owner, name: \$name) {
                commit: object(oid: \$oid) {
                    ... on Commit {
                        author {
                            name,
                            email,
                            user {
                                email,
                                login
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->runRaw($query, $params)->getData()->repository->commit;
    }

    /**
     * Runs GraphQL query
     *
     * @param GraphQL\Query $query
     * @param array $params
     * @return GraphQL\Results
     */
    private function run($query, $params = [])
    {
        try {
            return $this->client->runQuery($query, false, $params);
        } catch (\Exception $e) {
            Log::error('error graphql');
            Log::error($query);
            Log::error($params);
        }
    }

    /**
     * Runs raw GraphQL query
     *
     * @param [type] $query
     * @return void
     */
    private function runRaw($query, $params = [])
    {
        $tries = 0;

        while ($tries < $this->tries) {
            try {
                return $this->client->runRawQuery($query, false, $params);
            } catch (\Exception $e) {
                $tries++;

                Log::error('error graphql retry ' . $tries);
                sleep(1);
            }
        }

        Log::error('error graphql');
        Log::error($query);
        Log::error($params);

        return null;
    }
}
