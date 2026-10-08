@props(['route', 'params' => []])
@if (\Illuminate\Support\Facades\Route::has($route))
    <a href="{{ route($route, $params) }}">{{ $slot }}</a>
@else
    <strong>{{ $slot }}</strong>
@endif
