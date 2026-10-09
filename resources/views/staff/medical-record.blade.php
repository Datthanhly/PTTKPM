@extends('layouts.app')
@section('title', 'Phiếu khám bệnh')
@section('content')
<div class="page-head"><div><span class="eyebrow">HỒ SƠ KHÁM</span><h1>{{ $appointment->patient->full_name }}</h1><p>{{ $appointment->appointment_code }} · {{ $appointment->appointment_date->format('d/m/Y') }} {{ substr($appointment->start_time,0,5) }}</p></div></div>
<form class="form-card wide-card" method="post" action="{{ route('medical-records.update',$appointment) }}">@csrf @method('PUT')
<div class="form-grid"><label>Triệu chứng<textarea name="symptoms" rows="4">{{ old('symptoms',$appointment->medicalRecord?->symptoms) }}</textarea></label><label>Chẩn đoán *<textarea name="diagnosis" rows="4" required>{{ old('diagnosis',$appointment->medicalRecord?->diagnosis) }}</textarea></label><label>Kết luận<textarea name="conclusion" rows="4">{{ old('conclusion',$appointment->medicalRecord?->conclusion) }}</textarea></label><label>Đơn thuốc / hướng dẫn<textarea name="prescription" rows="4">{{ old('prescription',$appointment->medicalRecord?->prescription) }}</textarea></label></div>
<label>Ghi chú chuyên môn<textarea name="notes" rows="3">{{ old('notes',$appointment->medicalRecord?->notes) }}</textarea></label>
<fieldset><legend>Dịch vụ chỉ định</legend><div class="checks">@foreach($services as $service)<label class="check"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids',$appointment->medicalRecord?->visitServices->pluck('service_id')->all() ?? [])))> {{ $service->name }} — {{ number_format($service->price) }}đ</label>@endforeach</div></fieldset>
<button class="button wide">Lưu và hoàn tất ca khám</button></form>
@endsection
