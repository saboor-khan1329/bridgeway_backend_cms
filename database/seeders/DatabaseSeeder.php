<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            WebsiteContentSeeder::class,
            SiteSettingSeeder::class,
            NavigationSeeder::class,
        ]);

        // StaticPageSeeder, StaticPageContentSeeder, PageSectionsSeeder and
        // TestimonialSeeder are gone: they seeded the previous site's demo
        // content — accreditation pages, vetting applications, sample sections
        // on locations — none of which this frontend renders, and three of
        // whose slugs collided with real Bridgeway pages.
    }
}
