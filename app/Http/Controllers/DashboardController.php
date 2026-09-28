<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\ContentPage;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $stats = collect([
            ['label' => 'Amazon Services', 'value' => ContentPage::where('template', 'amazon-service')->count(), 'icon' => 'fab fa-amazon', 'tone' => 'warning', 'route' => 'admin.amazon-services.index'],
            ['label' => 'Service Pages', 'value' => Service::whereHas('contentBlocks', fn ($query) => $query->whereNotNull('section_key'))->count(), 'icon' => 'fas fa-code', 'tone' => 'info', 'route' => 'admin.development-services.index'],
            ['label' => 'Content Pages', 'value' => ContentPage::where('is_cms_managed', true)->whereNotIn('slug', ['service', 'service-two'])->count(), 'icon' => 'fas fa-file-alt', 'tone' => 'primary', 'route' => 'admin.content-pages.index'],
            ['label' => 'Blogs', 'value' => Blog::count(), 'icon' => 'fas fa-blog', 'tone' => 'success', 'route' => 'admin.blogs.index'],
            ['label' => 'Forms & Leads', 'value' => Inquiry::count(), 'icon' => 'fas fa-clipboard-list', 'tone' => 'primary', 'route' => 'admin.forms.index'],
            ['label' => 'New Inquiries', 'value' => Inquiry::new()->count(), 'icon' => 'fas fa-inbox', 'tone' => 'danger', 'route' => 'admin.inquiries.index'],
            ['label' => 'Users', 'value' => User::count(), 'icon' => 'fas fa-users', 'tone' => 'secondary', 'route' => 'admin.users.index', 'root_only' => true],
        ])->filter(fn (array $card) => empty($card['root_only']) || $user?->isRootAdmin())->values()->all();

        return view('dashboard.dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentInquiries' => Inquiry::query()->latest()->limit(8)->get(),
        ]);
    }
}
