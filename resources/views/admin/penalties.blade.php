@extends('layouts.admin')

@section('title', 'EquipTrack - Penalties')

@push('css')
@include('partials.penalties.styles')
@endpush

@section('content')
    @include('partials.penalties.page', ['party' => 'admin'])
@endsection

@push('scripts')
@include('partials.penalties.script', [
    'party' => 'admin',
    'urls' => [
        'data' => route('admin.penalties.data'),
        'store' => route('admin.penalties.store'),
        'update' => route('admin.penalties.update'),
        'settle' => route('admin.penalties.settle'),
        'waive' => route('admin.penalties.waive'),
    ],
])
@endpush
