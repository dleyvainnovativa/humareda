@php($nav = [
    ['route' => 'dashboard',           'label' => 'Panel',          'icon' => 'fa-gauge-high'],
    ['route' => 'reservations.index',  'label' => 'Reservaciones',  'icon' => 'fa-calendar-check'],
    ['route' => 'conversations.index', 'label' => 'Conversaciones', 'icon' => 'fa-comments'],
    ['route' => 'knowledge.index',     'label' => 'Conocimiento',   'icon' => 'fa-book-open'],
    ['route' => 'settings.edit',       'label' => 'Ajustes',        'icon' => 'fa-sliders'],
])

<aside class="hp-sidebar" id="hp-sidebar">
    <div class="hp-brand">
        <span class="hp-brand-mark"><i class="fa-solid fa-fire"></i></span>
        Humareda Prime
    </div>

    <nav class="hp-nav">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}"
               class="{{ request()->routeIs(\Illuminate\Support\Str::before($item['route'], '.').'*') || request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fa-solid {{ $item['icon'] }}"></i> {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="mt-auto px-2 pt-3" style="font-size:.75rem;opacity:.5;">
        v1.0 · Producción
    </div>
</aside>
<div class="hp-sidebar-backdrop" id="hp-sidebar-backdrop" hidden></div>
