@props(['action', 'method' => 'POST', 'backRoute', 'showfooter' => '1'])

<div class="card">
    <div class="card-header text-right">
        <a href="{{ $backRoute }}" class="btn btn-warning btn-sm">
            <i class="fa-solid fa-arrow-rotate-left"></i> Back
        </a>
    </div>

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if(strtoupper($method) === 'PUT')
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="row">
                {{ $slot }}
            </div>
        </div>
    @if(($showfooter) === '1')
        <div class="card-footer text-right">
            <button class="btn btn-dark mr-1" type="reset"><i class="fa-solid fa-arrows-rotate"></i> Reset</button>
            <a href="{{ $backRoute }}" class="btn btn-warning">Back</a>
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-floppy-disk"></i> {{ strtoupper($method) === 'PUT' ? 'Update' : 'Save' }}
            </button>
        </div>
        @endif
    </form>
</div>
