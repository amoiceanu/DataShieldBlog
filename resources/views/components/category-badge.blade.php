@props(['category'])
<a class="category-badge" href="{{ route('articles.index', ['categorie' => $category->slug]) }}">{{ $category->name }}</a>
