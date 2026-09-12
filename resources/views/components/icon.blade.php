@props(['name'])

<svg {{ $attributes->merge(['class' => 'app-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/> @break
        @case('students') <circle cx="12" cy="8" r="3"/><path d="M5 20c.7-3.5 3.1-5.2 7-5.2s6.3 1.7 7 5.2"/> @break
        @case('wallet') <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H19a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 8h15v5H4z"/><circle cx="16.5" cy="13.5" r=".7"/> @break
        @case('inventory') <path d="M4 4h16v16H4zM4 9h16M9 4v16"/><path d="M14 13h3m-3 3h3"/> @break
        @case('academic') <path d="m3 10 9-5 9 5-9 5z"/><path d="M7 12.2V16c2.5 1.5 7.5 1.5 10 0v-3.8M21 10v5"/> @break
        @case('teacher') <circle cx="12" cy="7" r="3"/><path d="M5 21v-2a7 7 0 0 1 14 0v2M18 4l2 2"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 20c.5-3.3 2.5-5 6-5s5.5 1.7 6 5M16 5.5a3 3 0 0 1 0 5M18 15c2 .5 3 2 3 4"/> @break
        @case('report') <path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.1 2.1-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-3v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-2.1-2.1.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H5.2v-3h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.1-2.1.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h3v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.1 2.1-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v3h-.2a1.7 1.7 0 0 0-1.6 1z"/> @break
        @case('search') <circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/> @break
        @case('calendar') <rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/> @break
        @case('sort') <path d="M8 4v15m0 0-3-3m3 3 3-3M16 20V5m0 0-3 3m3-3 3 3"/> @break
        @case('trash') <path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3M6 7l1 13h10l1-13"/> @break
        @case('edit') <path d="m4 16.5-.8 4.3 4.3-.8L19 8.5l-3.5-3.5zM14.5 5l3.5 3.5"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('profile') <circle cx="12" cy="8" r="3.5"/><path d="M5 21c.6-4.1 3-6.2 7-6.2s6.4 2.1 7 6.2"/> @break
        @default <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
