@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Navigation' => null]">
        <div class="col-12">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bars fa-3x text-primary mb-3"></i>
                            <h4>Header Navigation</h4>
                            <p class="text-muted mb-4">Manage logo, nav links, mega menus, and CTA button.</p>
                            <a href="{{ route('admin.navigation.show', 'header') }}" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Manage Header
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-align-justify fa-3x text-success mb-3"></i>
                            <h4>Footer Navigation</h4>
                            <p class="text-muted mb-4">Manage logo, link groups, locations, and bottom links.</p>
                            <a href="{{ route('admin.navigation.show', 'footer') }}" class="btn btn-success">
                                <i class="fas fa-edit"></i> Manage Footer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
