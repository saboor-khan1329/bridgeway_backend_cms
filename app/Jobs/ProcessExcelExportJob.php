<?php

namespace App\Jobs;

use App\Models\ExcelExport;
use App\Services\ExcelManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExcelExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $exportId)
    {
        $this->afterCommit();
    }

    public function handle(ExcelManagementService $service): void
    {
        $export = ExcelExport::query()->findOrFail($this->exportId);
        $service->runExport($export);
    }
}
