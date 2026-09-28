<?php

namespace App\Console\Commands;

use App\Services\MailDispatchService;
use Illuminate\Console\Command;

class ProcessMailDispatches extends Command
{
    protected $signature = 'mail-dispatches:process {--limit= : Maximum dispatch records to enqueue or process}';

    protected $description = 'Queue or process pending durable mail dispatch records.';

    public function handle(MailDispatchService $service): int
    {
        $limit = (int) ($this->option('limit') ?: config('mail_outbox.batch_size', 25));
        $count = $service->dispatchDue(max(1, $limit));

        $mode = (bool) config('mail_outbox.queue.enabled', true) ? 'queued' : 'processed';
        $this->info("Mail dispatches {$mode}: {$count}");

        return self::SUCCESS;
    }
}
