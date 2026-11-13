@extends('layouts.app')
@section('title', 'Lịch hẹn trong ca')
@push('styles')<link rel="stylesheet" href="{{ asset('calendar.css') }}">@endpush
@push('styles')<link rel="stylesheet" href="{{ asset('shift-transfers.css') }}">@endpush
@section('content')
<div class="page-head"><h1>Lịch hẹn trong ca</h1><a class="button ghost" href="{{ route('staff.appointments', ['date' => $schedule->work_date->format('Y-m-d'), 'doctor_id' => $schedule->doctor_id, 'branch_id' => $schedule->branch_id]) }}">← Về lịch tuần</a></div>
<section class="panel">@include('staff.partials.shift-appointments')</section>
@endsection
