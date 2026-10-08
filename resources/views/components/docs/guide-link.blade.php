@props(['key'])
<a href="{{ \App\Filament\Pages\Documentation\IntegrationGuide::getUrl(['guide' => $key]) }}">{{ $slot }}</a>
