<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        if (! Schema::hasColumn('inquiries', 'type_of_service_required')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->string('type_of_service_required')->nullable()->after('message');
            });
        }

        if (Schema::hasColumn('inquiries', 'services')) {
            DB::table('inquiries')
                ->select(['id', 'services'])
                ->orderBy('id')
                ->chunkById(100, function ($inquiries): void {
                    foreach ($inquiries as $inquiry) {
                        $services = json_decode((string) $inquiry->services, true);
                        $value = is_array($services)
                            ? collect($services)->map(fn ($service) => trim((string) $service))->filter()->implode(', ')
                            : null;

                        if ($value !== '') {
                            DB::table('inquiries')->where('id', $inquiry->id)->update([
                                'type_of_service_required' => mb_substr($value, 0, 191),
                            ]);
                        }
                    }
                });

            Schema::table('inquiries', function (Blueprint $table) {
                $table->dropColumn('services');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        if (! Schema::hasColumn('inquiries', 'services')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->json('services')->nullable()->after('message');
            });
        }

        if (Schema::hasColumn('inquiries', 'type_of_service_required')) {
            DB::table('inquiries')
                ->select(['id', 'type_of_service_required'])
                ->whereNotNull('type_of_service_required')
                ->orderBy('id')
                ->chunkById(100, function ($inquiries): void {
                    foreach ($inquiries as $inquiry) {
                        DB::table('inquiries')->where('id', $inquiry->id)->update([
                            'services' => json_encode([(string) $inquiry->type_of_service_required]),
                        ]);
                    }
                });

            Schema::table('inquiries', function (Blueprint $table) {
                $table->dropColumn('type_of_service_required');
            });
        }
    }
};
