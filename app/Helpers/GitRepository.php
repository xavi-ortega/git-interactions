<?php

namespace App\Helpers;

use Cz\Git\GitRepository as BaseGitRepository;

class GitRepository extends BaseGitRepository
{
    public function logPatches(string $outputFile)
    {
        return $this->begin()->run('git log -p --reverse -U0 > ' . $outputFile);
    }
}
