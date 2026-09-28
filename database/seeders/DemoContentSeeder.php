<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $serviceRoot = Category::factory()->service()->create([
            'name' => 'Security Services',
            'category_type' => 'service',
        ]);
        $guardingCategory = Category::factory()->service()->create([
            'name' => 'Guarding',
            'category_type' => 'service',
            'parent_id' => $serviceRoot->id,
        ]);

        $locationRoot = Category::factory()->location()->create([
            'name' => 'Service Areas',
        ]);
        $cityCategory = Category::factory()->location()->create([
            'name' => 'Major Cities',
            'parent_id' => $locationRoot->id,
        ]);

        $blogRoot = Category::factory()->blog()->create([
            'name' => 'Insights',
        ]);

        $faqs = Faq::factory()->count(5)->create();

        $services = Service::factory()->count(3)->create();
        $services[1]->update(['parent_id' => $services[0]->id]);
        $services[2]->update(['parent_id' => $services[0]->id]);

        foreach ($services as $service) {
            $service->categories()->syncWithoutDetaching([$guardingCategory->id]);
            $service->faqs()->sync($faqs->pluck('id')->take(3)->all());
        }

        $locations = Location::factory()->count(2)->create();
        $locations[1]->update(['parent_id' => $locations[0]->id]);

        foreach ($locations as $location) {
            $location->faqs()->sync($faqs->pluck('id')->take(2)->all());
        }

        $blogs = Blog::factory()->count(3)->create();
        foreach ($blogs as $blog) {
            $blog->categories()->syncWithoutDetaching([$blogRoot->id]);
            $blog->faqs()->sync($faqs->pluck('id')->take(2)->all());
        }

        $homePage = Page::factory()->create([
            'page_title' => 'Home',
            'page_type' => null,
        ]);
        $aboutPage = Page::factory()->create([
            'page_title' => 'About Us',
            'page_type' => null,
        ]);

        $homePage->services()->sync($services->pluck('id')->take(2)->all());
        $homePage->locations()->sync($locations->pluck('id')->all());
        $homePage->blogs()->sync($blogs->pluck('id')->take(2)->all());
        $aboutPage->faqs()->sync($faqs->pluck('id')->take(2)->all());
    }
}
