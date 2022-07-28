<?php

namespace App\Jobs\Generation\Source;

use App\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Download implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected Generation $generation)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {


        CopyFromCloud::dispatch($this->generation);

    }
}
