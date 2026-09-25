@props(['property', 'layout' => 'grid', 'dense' => false])

@php
    $p = $property;
    // Call the accessor directly — PropertyFromSheet::__isset() doesn't report
    // magic accessors, so `??` would short-circuit before __get runs.
    $photos = $p->high_quality_photos_array;
    if (!is_array($photos)) {
        $photos = [];
    }
    $img = $photos[0] ?? $p->first_photo_url ?? null;

    $location = (string) ($p->location ?? '');
    $area = $location !== '' ? trim(explode(',', $location)[0]) : '';

    $price = $p->price ?? null;
    if (is_numeric($price)) {
        $priceLabel = '£' . number_format((float) $price, 0);
    } elseif (is_string($price) && $price !== '') {
        $priceLabel = $price;
    } else {
        $priceLabel = 'Price on request';
    }

    $billsRaw = strtolower(trim((string) ($p->bills_included ?? '')));
    $billsIncluded = in_array($billsRaw, ['yes', 'true', '1', 'included'], true);

    $photoCount = (int) ($p->photo_count ?? count($photos));

    $title = (string) ($p->title ?? 'Untitled listing');
    $description = (string) ($p->description ?? '');

    $href = route('properties.show', \App\Support\PublicId::for((string) ($p->id ?? 0)));

    $flag = strtolower((string) ($p->flag ?? ''));
    $isPremium = $flag === 'premium';
@endphp

@if($layout === 'list')
    <a href="{{ $href }}" class="th-card th-card-list">
        <div class="th-card-media th-card-media-list"
             @if($img) style="background-image: url('{{ e($img) }}');" @endif>
            @if($photoCount > 0)
                <span class="th-photo-count">{{ $photoCount }} photos</span>
            @endif
            @if($isPremium)
                <span class="th-badge-overlay">Premium</span>
            @endif
        </div>
        <div class="th-card-body th-card-body-list">
            <div class="th-card-head">
                <div style="min-width:0;">
                    <div class="th-area">{{ $area ?: 'London' }}</div>
                    <h3 class="th-title">{{ $title }}</h3>
                </div>
                <div class="th-price">
                    <span class="th-price-amount">{{ $priceLabel }}</span>
                    <span class="th-price-period">&nbsp;pcm</span>
                </div>
            </div>
            @if($description)
                <p class="th-snippet">{{ $description }}</p>
            @endif
            <x-th.fact-row :property="$p"/>
        </div>
    </a>
@else
    <a href="{{ $href }}" class="th-card {{ $dense ? 'is-dense' : '' }}">
        <div class="th-card-media"
             @if($img) style="background-image: url('{{ e($img) }}');" @endif>
            @if($photoCount > 0)
                <span class="th-photo-count">{{ $photoCount }} photos</span>
            @endif
            @if($billsIncluded)
                <span class="th-badge-overlay">Bills incl.</span>
            @elseif($isPremium)
                <span class="th-badge-overlay">Premium</span>
            @endif
        </div>
        <div class="th-card-body">
            <div class="th-area">{{ $area ?: 'London' }}</div>
            <div class="th-card-titlerow">
                <h3 class="th-title">{{ $title }}</h3>
                <div class="th-price">{{ $priceLabel }}<span class="th-price-period">&nbsp;pcm</span></div>
            </div>
            <x-th.fact-row :property="$p"/>
        </div>
    </a>
@endif
