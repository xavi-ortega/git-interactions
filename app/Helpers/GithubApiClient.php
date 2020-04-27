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
     * @param array $params
     * @return Object
     */
    public function getRepositoryInfo($params)
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
}
