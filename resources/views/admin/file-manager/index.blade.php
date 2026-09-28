@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['File Manager' => null]">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('admin.file-manager._shell', [
                        'mode' => 'page',
                        'selectMode' => false,
                        'imagesOnly' => false,
                    ])
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
