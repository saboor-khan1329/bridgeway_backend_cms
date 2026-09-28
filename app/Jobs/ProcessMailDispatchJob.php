<?php

namespace App\Jobs;

use App\Models\MailDispatch;
use App\Services\MailDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMailDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $dispatchId)
    {
        $this->onQueue((string) config('mail_outbox.queue.name', 'mail-outbox'));
        $this->afterCommit();
    }

    public function handle(MailDispatchService $service): void
    {
        $dispatch = MailDispatch::query()->find($this->dispatchId);

        if ($dispatch) {
            $service->processOne($dispatch);
        }
    }
}
