{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $collection->title }}</title>
    @if ($collection->description)
        <subtitle>{{ $collection->description }}</subtitle>
    @endif
    <id>{{ route('collections.show', $collection) }}</id>
    <link rel="alternate" type="text/html" href="{{ route('collections.show', $collection) }}"/>
    <link rel="self" type="application/atom+xml" href="{{ route('collections.feed', $collection) }}"/>
    <updated>{{ ($collection->articles->max('pivot.updated_at') ?? $collection->updated_at)->toAtomString() }}</updated>
    <author><name>{{ $collection->user->name }}</name></author>
    @foreach ($collection->articles as $article)
        <entry>
            <title>{{ $article->title ?? $article->url }}</title>
            <id>{{ route('collections.show', $collection) }}#article-{{ $article->id }}</id>
            <link rel="alternate" type="text/html" href="{{ $article->url }}"/>
            <updated>{{ $article->pivot->updated_at->toAtomString() }}</updated>
            <published>{{ $article->pivot->created_at->toAtomString() }}</published>
            @if ($article->byline)
                <author><name>{{ $article->byline }}</name></author>
            @endif
            <content type="html">{{ view('collections.feed-entry', ['article' => $article])->render() }}</content>
        </entry>
    @endforeach
</feed>
