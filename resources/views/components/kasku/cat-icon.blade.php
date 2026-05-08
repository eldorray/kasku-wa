@props(['category'])

<div class="kasku-cat-icon" style="background: {{ $category->bg }}; color: {{ $category->color }}">
    {{ $category->emoji }}
</div>
