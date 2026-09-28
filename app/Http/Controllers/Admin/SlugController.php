<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slug;

class SlugController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(Slug::with('sluggable')->latest(), [
            'title' => 'Slugs',
            'columns' => [
                'id' => 'ID',
                'slug' => 'Slug',
                'sluggable_type' => 'Type',
                'sluggable_id' => 'Owner ID',
                'created_at' => 'Created',
            ],
            'search' => ['id', 'slug', 'sluggable_type'],
        ]);
    }
}
