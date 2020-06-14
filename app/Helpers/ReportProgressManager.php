<?php

namespace App\Helpers;

use App\ReportProgress;
use App\Helpers\Constants\ReportProgressType;

class ReportProgressManager
{
    private $progress;

    public function focusOn(ReportProgress $progress)
    {
        $this->progress = $progress;
    }

    public function setStep(int $type)
    {
        if ($this->progress->type !== $type) {
            $this->progress->update([
                'type' => $type,
                'progress' => 0,
            ]);
        }
    }

    public function setProgress(int $progress)
    {
        if ($this->progress->progress !== $progress) {
            $this->progress->update([
                'progress' => $progress
            ]);
        }
    }
}
