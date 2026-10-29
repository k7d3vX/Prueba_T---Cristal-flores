@props(['title', 'cents', 'help' => null])
<article>
    <header>{{ $title }}</header>
    <h3 style="margin:0"><x-money :cents="$cents" /></h3>
    @if ($help)<small>{{ $help }}</small>@endif
</article>
