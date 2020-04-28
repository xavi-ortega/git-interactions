<?php

namespace App\Helpers;

use GraphQL\{Client, Query, Variable};

/**
 * GithubApiClient
 *
 * Wrapper around GraphQL Client to make requests to Github API
 */
class GithubApiClient
{
    /**
     * client
     *
     * @var GraphQL\Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client(
            'https://api.github.com/graphql',
            [
                'Authorization' => 'Bearer ' . env('GITHUB_ACCESS_TOKEN')
            ]

        );
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

        return $this->run($query, $params)->getData()->repository;
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
                    (new Query('issues'))->setSelectionSet([
                        'totalCount',
                    ]),
                    (new Query('pullRequests'))->setSelectionSet([
                        'totalCount',
                    ]),
                ]
            );

        return $this->run($query, $params)->getData()->repository;
    }

    /**
     * Get repository issues
     *
     * cost: 1
     *
     * @param array $params
     * @return Object
     */
    public function getRepositoryIssues(array $params)
    {
        $query = <<<QUERY
        query (\$owner: String!, \$name: String!, \$first: Int!) {
            repository(owner: \$owner, name: \$name) {
                issues(first: \$first) {
                    nodes {
                        author {
                            login
                        }
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
                        author {
                            login
                        }
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
     * Runs GraphQL query
     *
     * @param GraphQL\Query $query
     * @param array $params
     * @return GraphQL\Results
     */
    private function run($query, $params = [])
    {
        return $this->client->runQuery($query, false, $params);
    }

    /**
     * Runs raw GraphQL query
     *
     * @param [type] $query
     * @return void
     */
    private function runRaw($query, $params = [])
    {
        return $this->client->runRawQuery($query, false, $params);
    }
}
