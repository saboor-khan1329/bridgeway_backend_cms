<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCategoryCtaFields();
        $this->addServiceButtonFields();
        $this->moveSectorFaqSections();
        $this->enforceSingleServiceCategory();

        if ($this->canChangeColumns() && Schema::hasTable('redirects') && Schema::hasColumn('redirects', 'to_url')) {
            Schema::table('redirects', function (Blueprint $table) {
                $table->string('to_url', 500)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if ($this->canChangeColumns() && Schema::hasTable('redirects') && Schema::hasColumn('redirects', 'to_url')) {
            DB::table('redirects')->whereNull('to_url')->update(['to_url' => '/']);

            Schema::table('redirects', function (Blueprint $table) {
                $table->string('to_url', 500)->nullable(false)->change();
            });
        }

        if (Schema::hasTable('services_category')) {
            Schema::table('services_category', function (Blueprint $table) {
                $table->dropUnique('services_category_service_unique');
            });
        }

        if (Schema::hasTable('service_sector_faqs')) {
            DB::table('service_sector_faqs')->where('section_key', 'section_4')->update(['section_key' => 'legacy_section_3']);
            DB::table('service_sector_faqs')->where('section_key', 'section_3')->update(['section_key' => 'section_2']);
            DB::table('service_sector_faqs')->where('section_key', 'legacy_section_3')->update(['section_key' => 'section_3']);
        }

        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                if (! Schema::hasColumn('services', 'section_2_sectors_faqs')) {
                    $table->json('section_2_sectors_faqs')->nullable();
                }
            });

            DB::table('services')
                ->select(['id', 'section_3_sectors_faqs', 'section_4_sectors_faqs'])
                ->orderBy('id')
                ->chunkById(200, function ($services) {
                    foreach ($services as $service) {
                        DB::table('services')
                            ->where('id', $service->id)
                            ->update([
                                'section_2_sectors_faqs' => $service->section_3_sectors_faqs,
                                'section_3_sectors_faqs' => $service->section_4_sectors_faqs,
                            ]);
                    }
                });

            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn([
                    'section_2_button_name',
                    'section_2_button_url',
                    'section_3_button_name',
                    'section_3_button_url',
                    'section_5_button_name',
                    'section_5_button_url',
                    'section_6_button_name',
                    'section_6_button_url',
                    'section_4_sectors_faqs',
                ]);
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn([
                    'section_cta_heading',
                    'section_cta_description',
                    'section_cta_button_name',
                    'section_cta_button_url',
                ]);
            });
        }
    }

    protected function addCategoryCtaFields(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'section_cta_heading')) {
                $table->string('section_cta_heading')->nullable()->after('banner_description');
            }
            if (! Schema::hasColumn('categories', 'section_cta_description')) {
                $table->text('section_cta_description')->nullable()->after('section_cta_heading');
            }
            if (! Schema::hasColumn('categories', 'section_cta_button_name')) {
                $table->string('section_cta_button_name')->nullable()->after('section_cta_description');
            }
            if (! Schema::hasColumn('categories', 'section_cta_button_url')) {
                $table->string('section_cta_button_url', 500)->nullable()->after('section_cta_button_name');
            }
        });
    }

    protected function addServiceButtonFields(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            foreach ([2, 3, 5, 6] as $section) {
                $name = "section_{$section}_button_name";
                $url = "section_{$section}_button_url";

                if (! Schema::hasColumn('services', $name)) {
                    $table->string($name)->nullable();
                }
                if (! Schema::hasColumn('services', $url)) {
                    $table->string($url, 500)->nullable();
                }
            }

            if (! Schema::hasColumn('services', 'section_4_sectors_faqs')) {
                $table->json('section_4_sectors_faqs')->nullable();
            }
        });
    }

    protected function moveSectorFaqSections(): void
    {
        if (Schema::hasTable('services')
            && Schema::hasColumn('services', 'section_2_sectors_faqs')
            && Schema::hasColumn('services', 'section_3_sectors_faqs')
            && Schema::hasColumn('services', 'section_4_sectors_faqs')) {
            DB::table('services')
                ->select(['id', 'section_2_sectors_faqs', 'section_3_sectors_faqs', 'section_4_sectors_faqs'])
                ->orderBy('id')
                ->chunkById(200, function ($services) {
                    foreach ($services as $service) {
                        DB::table('services')
                            ->where('id', $service->id)
                            ->update([
                                'section_3_sectors_faqs' => $service->section_2_sectors_faqs ?: $service->section_3_sectors_faqs,
                                'section_4_sectors_faqs' => $service->section_4_sectors_faqs ?: $service->section_3_sectors_faqs,
                            ]);
                    }
                });

            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('section_2_sectors_faqs');
            });
        }

        if (Schema::hasTable('service_sector_faqs')) {
            DB::table('service_sector_faqs')->where('section_key', 'section_3')->update(['section_key' => 'legacy_section_4']);
            DB::table('service_sector_faqs')->where('section_key', 'section_2')->update(['section_key' => 'section_3']);
            DB::table('service_sector_faqs')->where('section_key', 'legacy_section_4')->update(['section_key' => 'section_4']);
        }
    }

    protected function enforceSingleServiceCategory(): void
    {
        if (! Schema::hasTable('services_category')) {
            return;
        }

        DB::table('services_category')
            ->select('service_id')
            ->groupBy('service_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('service_id')
            ->pluck('service_id')
            ->each(function ($serviceId) {
                $keepCategoryId = DB::table('services_category')
                    ->where('service_id', $serviceId)
                    ->min('category_id');

                DB::table('services_category')
                    ->where('service_id', $serviceId)
                    ->where('category_id', '!=', $keepCategoryId)
                    ->delete();
            });

        Schema::table('services_category', function (Blueprint $table) {
            $table->unique('service_id', 'services_category_service_unique');
        });
    }

    protected function canChangeColumns(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'sqlite';
    }
};
