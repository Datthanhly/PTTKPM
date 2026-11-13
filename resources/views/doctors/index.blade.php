@extends('layouts.app')
@section('content')
<h1>Đội ngũ bác sĩ</h1><form method="get" class="filters"><x-branch-filter :branches="$branches"/><label>Chuyên khoa<select name="specialty_id"><option value="">Tất cả</option>@foreach($specialties as $s)<option value="{{ $s->id }}" @selected(request('specialty_id')==$s->id)>{{ $s->name }}</option>@endforeach</select></label><button>Tra cứu</button></form>
<div class="grid three">@forelse($doctors as $d)<article class="panel"><h2>{{ $d->user->name }}</h2><p>{{ $d->specialties->pluck('name')->join(', ') ?: $d->specialty->name }}</p><p>{{ $d->qualification }}</p><a class="button" href="{{ route('doctors.show',$d) }}">Thông tin và lịch làm</a></article>@empty<p>Chưa có bác sĩ phù hợp.</p>@endforelse</div>{{ $doctors->links() }}
@endsection
