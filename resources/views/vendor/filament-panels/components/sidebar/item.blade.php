{{-- Filament 5.9 sidebar item: pass child items recursively for nested report portfolios. --}}
@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\View\ComponentAttributeBag;

    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $childrenId = $childItems
        ? 'simaster-sidebar-children-'.substr(sha1(($url ?? '').'|'.$slot->toHtml()), 0, 12)
        : null;
@endphp

<li
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
            'simaster-sidebar-has-children' => (bool) $childItems,
        ])
    }}
    @if ($childItems)
        x-data="{ childrenOpen: @js($active || $activeChildItems) }"
    @endif
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        @if ($active)
            aria-current="page"
        @endif
        x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @if ($sidebarCollapsible && (! $subNavigation))
            x-bind:aria-label="$store.sidebar.isOpen ? null : @js(trim(strip_tags($slot->toHtml())))"
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn"
    >
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            {{
                \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new ComponentAttributeBag([
                    'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                ]))->class(['fi-sidebar-item-icon']), size: IconSize::Large)
            }}
        @endif

        @if ((blank($icon) && $grouped) || $subGrouped)
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border"
            >
                @if (! $first)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-first"
                    ></div>
                @endif

                @if (! $last)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-last"
                    ></div>
                @endif

                <div class="fi-sidebar-item-grouped-border-part"></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif
    </a>

    @if ($childItems)
        <button
            type="button"
            aria-label="Tampilkan atau sembunyikan submodul {{ trim(strip_tags($slot->toHtml())) }}"
            aria-controls="{{ $childrenId }}"
            x-bind:aria-expanded="childrenOpen"
            x-on:click="if (! $store.sidebar.isOpen) { $store.sidebar.open(); childrenOpen = true } else { childrenOpen = ! childrenOpen }"
            class="simaster-sidebar-disclosure-btn"
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
            @endif
        >
            <span class="simaster-sidebar-disclosure" x-bind:class="{ 'simaster-sidebar-disclosure-open': childrenOpen }">
                {{ \Filament\Support\generate_icon_html('heroicon-m-chevron-down', size: IconSize::Small) }}
            </span>
        </button>
    @endif

    @if ($childItems)
        <ul
            id="{{ $childrenId }}"
            class="fi-sidebar-sub-group-items"
            x-show="childrenOpen"
            x-cloak
            @if ($subGrouped)
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-bind:style="$store.sidebar.isOpen ? 'padding-inline-start: 1rem' : ''"
                @else
                    style="padding-inline-start: 1rem"
                @endif
            @endif
        >
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :child-items="$childItem->getChildItems()"
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
