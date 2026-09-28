@props([
    'label',
    'value',
    'tone' => null,
])

<div {{ $attributes->class(['col-md-6', 'col-xl-3', 'mb-3']) }}>
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
            <div class="text-muted small">{{ $label }}</div>
            <div class="h3 mb-0 {{ $tone ? 'text-' . $tone : '' }}">{{ $value }}</div>
        </div>
    </div>
</div>
