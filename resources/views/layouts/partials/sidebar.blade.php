@php($nav = [
    ['route' => 'dashboard',           'label' => 'Panel',          'icon' => 'fa-gauge-high'],
    ['route' => 'conversations.index', 'label' => 'Conversaciones', 'icon' => 'fa-comments'],
    ['route' => 'knowledge.index',     'label' => 'Conocimiento',   'icon' => 'fa-book-open'],
    // Enabled in later tiers:
    ['route' => null, 'label' => 'Reservaciones', 'icon' => 'fa-calendar-check'],
    ['route' => null, 'label' => 'Ajustes',       'icon' => 'fa-sliders'],
])

<aside class="hp-sidebar" id="hp-sidebar">
    <div class="hp-brand">
        <span class="hp-brand-mark"><i class="fa-solid fa-fire"></i></span>
        Humareda Prime
    </div>

    <nav class="hp-nav">
        @foreach ($nav as $item)
            @if ($item['route'])
                <a href="{{ route($item['route']) }}"
                   class="{{ request()->routeIs(\Illuminate\Support\Str::before($item['route'], '.').'*') || request()->routeIs($item['route']) ? 'active' : '' }}">
                    <i class="fa-solid {{ $item['icon'] }}"></i> {{ $item['label'] }}
                </a>
            @else
                <a href="#" aria-disabled="true" style="opacity:.45;cursor:default;"
                   title="Disponible en una etapa posterior">
                    <i class="fa-solid {{ $item['icon'] }}"></i> {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="mt-auto px-2 pt-3" style="font-size:.75rem;opacity:.5;">
        v0.8 · Base de conocimiento
    </div>
</aside>
<div class="hp-sidebar-backdrop" id="hp-sidebar-backdrop" hidden></div>
