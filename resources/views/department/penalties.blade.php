@extends('layouts.department')

@section('title', 'EquipTrack - Penalties')

@push('css')
@include('partials.penalties.styles')
@endpush

@section('content')
    @include('partials.penalties.page', ['party' => 'dept'])
@endsection

@push('scripts')
@include('partials.penalties.script', [
    'party' => 'dept',
    'urls' => [
        'data' => route('department.penalties.data'),
        'store' => route('department.penalties.store'),
        'update' => route('department.penalties.update'),
        'settle' => route('department.penalties.settle'),
        'waive' => route('department.penalties.waive'),
        'approveProof' => route('department.penalties.proof.approve'),
        'rejectProof' => route('department.penalties.proof.reject'),
    ],
])
@endpush
