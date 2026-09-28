@props(['title', 'breadcrumbs' => []])

<div class="page-content">
    <x-layouts.breadcrumbs :title="$title" :items="$breadcrumbs" />

    <div class="container-fluid mt-3">
        <div class="row">
            {{ $slot }}
        </div>
    </div>
</div>
