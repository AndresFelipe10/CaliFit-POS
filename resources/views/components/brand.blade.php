@props(['size' => 'sm', 'light' => false, 'inverted' => false, 'showSubtitle' => false])

@php
    $isLg = $size === 'lg';
    $isLight = (bool) ($light || $inverted);
    $imgSize = $isLg ? '44px' : '36px';
    $textSizeClass = $isLg ? 'fs-3' : 'fs-5';
@endphp

<div {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-2']) }}>
    <img src="{{ asset('apple-touch-icon.png') }}" 
         alt="CaliFit" 
         class="rounded-circle shadow-sm" 
         style="width: {{ $imgSize }}; height: {{ $imgSize }}; object-fit: cover; flex-shrink: 0;">
    <div class="d-flex flex-column lh-1 text-start">
        <span class="{{ $textSizeClass }} fw-bold" style="letter-spacing: -0.02em;">
            <span style="color: #10B981;">Cali</span><span class="{{ $isLight ? 'text-white' : 'text-dark' }}">Fit</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0 align-middle" style="font-size: 0.55em; font-weight: 700;">POS</span>
        </span>
        @if ($isLg || $showSubtitle)
            <span class="small {{ $isLight ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.65rem; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 600; margin-top: 3px;">
                SaborCaliFit • Bochalema
            </span>
        @endif
    </div>
</div>
