@extends('layouts.standalone')

@section('title', 'Bill image')

@push('head')
    <style>
        body { margin: 0; padding: 20px; background: #f0f0f0; }
        .image-container { text-align: center; }
        .image-container img {
            max-width: 100%;
            max-height: 500px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .no-image {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 18px;
        }
    </style>
@endpush

@section('content')
    <div class="image-container">
        @if(!empty($images))
            @foreach($images as $image)
                @if(!empty($image))
                    <img src="{{ asset('uploads/' . $image) }}" alt="Claim Image">
                @endif
            @endforeach
        @else
            <div class="no-image">No images available for this claim</div>
        @endif
    </div>
@endsection
