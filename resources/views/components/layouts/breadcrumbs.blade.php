@props(['title', 'items' => []])

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">{{ $title }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    @foreach($items as $label => $url)
                        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                            @if($url)
                                <a href="{{ $url }}">{{ $label }}</a>
                            @else
                                {{ $label }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</div>
