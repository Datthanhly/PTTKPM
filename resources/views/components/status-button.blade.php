@props(['appointment','status','label'])
<form method="post" action="{{ route('staff.appointments.transition', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}"><button class="button tiny ghost">{{ $label }}</button></form>
