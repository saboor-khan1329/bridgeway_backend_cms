<?php

namespace App\Jobs;

use App\Models\ExcelImport;
use App\Services\ExcelManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExcelImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $importId)
    {
        $this->afterCommit();
    }

    public function handle(ExcelManagementService $service): void
    {
        $import = ExcelImport::query()->findOrFail($this->importId);
        $service->runImport($import);
    }
}
