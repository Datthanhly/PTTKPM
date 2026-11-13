@extends('layouts.app')
@section('content')
<h1>{{ $doctor->user->name }}</h1><p>{{ $doctor->qualification }} · {{ $doctor->specialties->pluck('name')->join(', ') ?: $doctor->specialty->name }}</p><p>{{ $doctor->bio }}</p><p>Mã bác sĩ: {{ $doctor->doctor_code }} · Phòng {{ $doctor->room }}</p>
@if(!auth()->check() || auth()->user()->hasAnyRole(['patient', 'receptionist']))
    <a class="button" href="{{ route('appointments.create',['doctor_id'=>$doctor->id]) }}">Chọn giờ khám</a>
@else
    <a class="button" href="{{ route('dashboard') }}">Xem thông báo</a>
@endif
<section class="panel table-wrap"><h2>Ca làm sắp tới</h2><table><thead><tr><th>Ngày</th><th>Chi nhánh</th><th>Khung giờ</th><th>Phút / lượt</th></tr></thead><tbody>@foreach($doctor->schedules as $s)<tr><td>{{ $s->work_date->format('d/m/Y') }}</td><td>{{ $s->branch->name }}</td><td>{{ $s->start_time }}–{{ $s->end_time }}</td><td>{{ $s->slot_minutes }}</td></tr>@endforeach</tbody></table></section>
@endsection
