@props(['appointment','status','label','disabled'=>false,'hint'=>null])
<form method="post" action="{{ route('staff.appointments.transition', $appointment) }}" data-appointment-status>@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}"><button type="submit" class="button tiny ghost" @disabled($disabled) @if($hint) title="{{ $hint }}" @endif>{{ $label }}</button></form>
