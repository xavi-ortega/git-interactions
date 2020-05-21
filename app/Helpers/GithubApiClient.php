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
     * guzzle client for http requests
     *
     * @var GuzzleHttp\Client
     */
    private $guzzleClient;


    public function __construct()
    {
        $this->client = new Client(
            'https://api.github.com/graphql',
            [
                'Authorization' => 'Bearer ' . env('GITHUB_ACCESS_TOKEN'),
                'Accept' => ['application/vnd.github.merge-info-preview+json', 'application/vnd.github.starfox-preview+json'],
            ]

        );

        $this->guzzleClient = new GuzzleClient([
            'base_uri' => 'https://api.github.com',
            'headers' => [
                'Authorization' => 'Bearer ' . env('GITHUB_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
    }

    /**
     * Gets Github Api rate limit
     *
     * cost: 0
     *
     * @return Object
     */
    public function getRateLimit()
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
     * @return Object
     */
    public function getRepositoryInfo(array $params)
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
     * @return Object
     */
    public function getRepositoryMetrics(array $params)
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
    public function getRepositoryIssues(array $params)
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
     * @return Object
     */
    public function getRepositoryIssuesPaginated($params)
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
    public function getRepositoryPullRequests(array $params)
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
                        },

                        reviews (first: 10) {
                            nodes {
                                author {
                                    login
                                }
                            }
                        },

                        suggestedReviewers {
                            reviewer {
                                login
                            }
                        },

                        commits {
                            totalCount
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
     * @return Object
     */
    public function getRepositoryPullRequestsPaginated(array $params)
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
                        },

                        reviews (first: 10) {
                            nodes {
                                author {
                                    login
                                }
                            }
                        },

                        suggestedReviewers {
                            reviewer {
                                login
                            }
                        },

                        commits {
                            totalCount
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
    public function getRepositoryBranches(array $params)
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
                                      pushedDate
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
     * @return Object
     */
    public function getRepositoryBranchesPaginated(array $params)
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
                                      pushedDate
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
    public function getRepositoryCommitsByBranch(array $params)
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
                                    changedFiles,
                                    additions,
                                    deletions,
                                    committedDate,
                                    pushedDate,
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
     * @return Object
     */
    public function getRepositoryCommitsByBranchPaginated(array $params)
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
                                    changedFiles,
                                    additions,
                                    deletions,
                                    committedDate,
                                    pushedDate,
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
        try {
            return $this->client->runRawQuery($query, false, $params);
        } catch (\Exception $e) {
            Log::error('error graphql retry 1');
            sleep(1);

            try {
                return $this->client->runRawQuery($query, false, $params);
            } catch (\Exception $e) {
                Log::error('error graphql retry 2');
                sleep(1);

                try {
                    return $this->client->runRawQuery($query, false, $params);
                } catch (\Exception $e) {
                    Log::error('error graphql');
                    Log::error($query);
                    Log::error($params);
                }
            }
        }
    }
}
