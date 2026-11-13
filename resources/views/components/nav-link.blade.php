@props(['href', 'active' => false])
<a {{ $attributes->class(['nav-link', 'is-active' => $active])->merge(['href' => $href]) }} @if($active) aria-current="page" @endif>{{ $slot }}</a>
