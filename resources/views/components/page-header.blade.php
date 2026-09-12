@props(['title', 'subtitle' => null, 'action' => null, 'actionUrl' => null, 'titleId' => null])

<header class="page-header">
    <div>
        <h1 @if ($titleId) id="{{ $titleId }}" @endif>{{ $title }}</h1>
        @if ($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    @if ($action && $actionUrl)
        <a class="primary-button" href="{{ $actionUrl }}">{{ $action }}</a>
    @endif
</header>
