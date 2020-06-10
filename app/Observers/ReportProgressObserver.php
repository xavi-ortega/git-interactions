<?php

namespace App\Observers;

use App\Events\ReportProgressUpdated;
use App\Events\ReportQueueUpdated;
use App\ReportProgress;

class ReportProgressObserver
{
    /**
     * Handle the report progress "created" event.
     *
     * @param  \App\ReportProgress  $reportProgress
     * @return void
     */
    public function created(ReportProgress $reportProgress)
    {
        event(new ReportProgressUpdated($reportProgress));

        $queue = ReportProgress::with('report')->get();

        event(new ReportQueueUpdated($queue));
    }

    /**
     * Handle the report progress "updated" event.
     *
     * @param  \App\ReportProgress  $reportProgress
     * @return void
     */
    public function updated(ReportProgress $reportProgress)
    {
        if ($reportProgress->isDirty('type')) {
            $queue = ReportProgress::with('report')->get();

            event(new ReportQueueUpdated($queue));
        }

        event(new ReportProgressUpdated($reportProgress));
    }

    /**
     * Handle the report progress "deleted" event.
     *
     * @param  \App\ReportProgress  $reportProgress
     * @return void
     */
    public function deleted(ReportProgress $reportProgress)
    {
        $queue = ReportProgress::with('report')->get();

        event(new ReportQueueUpdated($queue));
    }

    /**
     * Handle the report progress "restored" event.
     *
     * @param  \App\ReportProgress  $reportProgress
     * @return void
     */
    public function restored(ReportProgress $reportProgress)
    {
        //
    }

    /**
     * Handle the report progress "force deleted" event.
     *
     * @param  \App\ReportProgress  $reportProgress
     * @return void
     */
    public function forceDeleted(ReportProgress $reportProgress)
    {
        //
    }
}
