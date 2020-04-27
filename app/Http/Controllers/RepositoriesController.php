<?php

namespace App\Http\Controllers;

use App\Repository;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\GithubApiClient;

class RepositoriesController extends Controller
{
    public function report(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'owner' => 'required'
        ]);

        $github = new GithubApiClient();

        $repository = Repository::where('name', $request->name)->where('owner', $request->owner)->first();

        if ($repository === null) {
            $repositoryInfo = $github->getRepositoryInfo([
                'name' => $request->name,
                'owner' => $request->owner
            ]);

            $repository = Repository::create([
                'name' => $repositoryInfo->name,
                'slug' => Str::slug($repositoryInfo->name),
                'url' => $repositoryInfo->url,
                'description' => $repositoryInfo->description,
                'owner' => $repositoryInfo->owner->login
            ]);
        }

        return response()->json($repository);
    }
}
