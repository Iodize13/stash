@if ($article->pivot->note)
    <p>{{ $article->pivot->note }}</p>
@endif
@foreach ($article->highlights as $highlight)
    <blockquote>{{ $highlight->exact }}</blockquote>
    @if ($highlight->note)
        <p>{{ $highlight->note }}</p>
    @endif
@endforeach
<p><a href="{{ $article->url }}">Read the original</a></p>
