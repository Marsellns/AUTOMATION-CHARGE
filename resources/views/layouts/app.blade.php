<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ str_replace('SIMASTER', 'SIMAWAR', trim($__env->yieldContent('title', 'SIMAWAR'))) }}</title>

    {{-- Early Theme Initialization (Anti-FOUC) --}}
    <script>
        (function() {
            const savedTheme = localStorage.getItem('simawar_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css" rel="stylesheet">
    <link href="{{ asset('css/simaster.css') }}" rel="stylesheet">
    @stack('styles')
    <style>
        .simaster-period-year::-webkit-inner-spin-button,
        .simaster-period-year::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .simaster-period-year {
            -moz-appearance: textfield;
        }

        .simaster-filter-btn {
            background-color: var(--bs-body-bg, #fff);
            border: 1px solid var(--bs-border-color, #dee2e6);
            color: var(--bs-body-color, #212529);
            font-weight: 500;
            padding: 0.38rem 0.75rem;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }

        .simaster-filter-btn:hover,
        .simaster-filter-btn:focus,
        .simaster-filter-btn[aria-expanded="true"] {
            border-color: #3b82f6;
            background-color: var(--bs-tertiary-bg, #f8f9fa);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .simaster-filter-icon {
            width: 18px;
            height: 18px;
            object-fit: contain;
            flex-shrink: 0;
            display: inline-block;
            vertical-align: middle;
        }

        .simaster-filter-badge {
            font-size: 0.76rem;
            font-weight: 600;
            padding: 0.22rem 0.6rem;
            border-radius: 6px;
            background-color: rgba(59, 130, 246, 0.1) !important;
            color: #2563eb !important;
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
            letter-spacing: 0.01em;
        }

        [data-bs-theme="dark"] .simaster-filter-badge {
            background-color: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.35) !important;
        }

        .simaster-filter-menu {
            z-index: 1060;
            border-radius: 12px;
            box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.16), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid var(--bs-border-color, #dee2e6);
            background-color: var(--bs-body-bg, #fff);
        }

        .simaster-month-btn {
            font-size: 0.78rem;
            font-weight: 500;
            padding: 0.28rem 0.2rem;
            border-radius: 6px;
            transition: all 0.12s ease;
        }

        .simaster-month-btn.active {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .simaster-year-btn {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 6px;
        }

        .simaster-nop-section {
            border-top: 1px solid var(--bs-border-color, #dee2e6);
        }
    </style>
    </head>
<body>
    <div class="app-layout" id="appLayout">
        {{-- Left Sidebar --}}
        <aside class="app-sidebar" id="appSidebar">
            {{-- Sidebar Brand Header --}}
            <div class="sidebar-header">
                <a class="sidebar-brand" href="{{ url('/dashboard') }}">
                    <img class="sidebar-logo" src="{{ asset('images/telkomsel-logo.png') }}" alt="Telkomsel">
                    <div class="brand-title">SIMAWAR</div>
                    <div class="brand-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M4.318 2.687C5.234 2.271 6.536 2 8 2s2.766.27 3.682.687C12.644 3.125 13 3.627 13 4c0 .374-.356.875-1.318 1.313C10.766 5.729 9.464 6 8 6s-2.766-.27-3.682-.687C3.356 4.875 3 4.373 3 4c0-.373.356-.875 1.318-1.313ZM13 5.698V7c0 .374-.356.875-1.318 1.313C10.766 8.729 9.464 9 8 9s-2.766-.27-3.682-.687C3.356 7.875 3 7.374 3 7V5.698c.271.202.58.378.904.525C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777A4.92 4.92 0 0 0 13 5.698ZM14 4c0-1.007-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1s-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4v9c0 1.007.875 1.755 1.904 2.223C4.978 15.711 6.427 16 8 16s3.022-.289 4.096-.777C13.125 14.755 14 14.007 14 13V4Zm-1 4.698V10c0 .374-.356.875-1.318 1.313C10.766 11.729 9.464 12 8 12s-2.766-.27-3.682-.687C3.356 10.875 3 10.374 3 10V8.698c.271.202.58.378.904.525C4.978 9.711 6.427 10 8 10s3.022-.289 4.096-.777A4.92 4.92 0 0 0 13 8.698Zm0 3V13c0 .374-.356.875-1.318 1.313C10.766 14.729 9.464 15 8 15s-2.766-.27-3.682-.687C3.356 13.875 3 13.374 3 13v-1.302c.271.202.58.378.904.525C4.978 12.711 6.427 13 8 13s3.022-.289 4.096-.777c.324-.147.633-.323.904-.525Z"/></svg>
                    </div>
                </a>
                <button type="button" class="btn-close d-lg-none" id="sidebarCloseBtn" aria-label="Tutup sidebar"></button>
            </div>

            {{-- Sidebar Navigation --}}
            <div class="sidebar-body">
                <div class="sidebar-section-title">Navigasi Utama</div>
                <ul class="sidebar-nav">
                    {{-- Dashboard --}}
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            <span class="sidebar-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/></svg>
                            </span>
                            <span class="sidebar-label">Dashboard</span>
                        </a>
                    </li>

                    {{-- Profit & Loss --}}
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('pnl.*') ? 'active' : '' }}" href="{{ route('pnl.index') }}">
                            <span class="sidebar-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 0h1v15h15v1H0V0Zm10 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.707l-4.146 4.147a.5.5 0 0 1-.708 0L7 6.707l-4.146 4.147a.5.5 0 0 1-.708-.708l4.5-4.5a.5.5 0 0 1 .708 0L9.5 7.793l3.646-3.647H10.5a.5.5 0 0 1-.5-.5Z"/></svg>
                            </span>
                            <span class="sidebar-label">Profit &amp; Loss</span>
                        </a>
                    </li>

                    {{-- Infrastruktur --}}
                    @php($isInfrastruktur = request()->routeIs('infrastruktur.*'))
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ $isInfrastruktur ? 'active' : '' }}" href="{{ route('infrastruktur.index') }}">
                            <span class="sidebar-group-title">
                                <span class="sidebar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="m8.211 2.047.011-.004a.5.5 0 0 0-.444 0l-.011.004-.003.001-.008.003L7.74 2.06l-.048.02A6 6 0 0 0 6.64 2.6l-.16.11A8 8 0 0 0 4.8 4.2c-.6.6-1.1 1.4-1.4 2.3-.3.8-.4 1.8-.4 2.8 0 1 .1 2 .4 2.8.3.9.8 1.7 1.4 2.3.6.6 1.3 1.1 2.2 1.4.8.3 1.8.4 2.8.4 1 0 2-.1 2.8-.4.9-.3 1.6-.8 2.2-1.4.6-.6 1.1-1.4 1.4-2.3.3-.8.4-1.8.4-2.8 0-1-.1-2-.4-2.8-.3-.9-.8-1.7-1.4-2.3a8 8 0 0 0-1.68-1.49l-.16-.11a6 6 0 0 0-1.052-.52l-.048-.02-.016-.008-.008-.003-.003-.001-.001-.001zM8 3c.8 0 1.6.1 2.4.4.7.2 1.3.6 1.8 1.1.5.5.9 1.1 1.1 1.8.3.8.4 1.6.4 2.4s-.1 1.6-.4 2.4c-.2.7-.6 1.3-1.1 1.8-.5.5-1.1.9-1.8 1.1-.8.3-1.6.4-2.4.4s-1.6-.1-2.4-.4c-.7-.2-1.3-.6-1.8-1.1-.5-.5-.9-1.1-1.1-1.8-.3-.8-.4-1.6-.4-2.4s.1-1.6.4-2.4c.2-.7.6-1.3 1.1-1.8.5-.5 1.1-.9 1.8-1.1.8-.3 1.6-.4 2.4-.4z"/></svg>
                                </span>
                                <span class="sidebar-label">Infrastruktur</span>
                            </span>
                            <button class="sidebar-chevron sidebar-group-toggle border-0 bg-transparent text-reset p-0" data-bs-toggle="collapse" data-bs-target="#collapseInfrastruktur" type="button" aria-expanded="{{ $isInfrastruktur ? 'true' : 'false' }}" aria-controls="collapseInfrastruktur">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
                            </button>
                        </a>
                        <div class="collapse {{ $isInfrastruktur ? 'show' : '' }}" id="collapseInfrastruktur">
                            @php($sewaOwnershipScope = strtolower(trim((string) (request('ownership_scope') ?: (request('filter_field') === 'ownership' ? request('filter_value') : '')))))
                            <ul class="sidebar-submenu">
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.sewa-lahan.*') && !in_array($sewaOwnershipScope, ['telkomsel', 'tp'], true) ? 'active' : '' }}" href="{{ route('infrastruktur.sewa-lahan.index') }}">Sewa Lahan</a></li>
                                <li><a class="sidebar-subitem-link {{ $sewaOwnershipScope === 'telkomsel' ? 'active' : '' }}" href="{{ route('concept.site-telkomsel') }}">Site Telkomsel</a></li>
                                <li><a class="sidebar-subitem-link {{ $sewaOwnershipScope === 'tp' ? 'active' : '' }}" href="{{ route('concept.site-tp') }}">Site TP</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.combat.*') ? 'active' : '' }}" href="{{ route('infrastruktur.combat.index') }}">Combat</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.recurring-ipas.*') ? 'active' : '' }}" href="{{ route('infrastruktur.recurring-ipas.index') }}">Recurring (ANT &amp; Ipas)</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.recurring-tagihan-ipas.*') ? 'active' : '' }}" href="{{ route('infrastruktur.recurring-tagihan-ipas.index') }}">Recurring (Tagihan Ipas)</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.jaknet.*') ? 'active' : '' }}" href="{{ route('infrastruktur.jaknet.index') }}">Sewa Lahan (Jaknet &amp; Dapot)</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.site-unlock.*') ? 'active' : '' }}" href="{{ route('infrastruktur.site-unlock.index') }}">Data Site Unlock</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.bapss.*') ? 'active' : '' }}" href="{{ route('infrastruktur.bapss.index') }}">BAPSS</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('infrastruktur.upload-file.*') ? 'active' : '' }}" href="{{ route('infrastruktur.upload-file.index') }}">Upload File PDF</a></li>
                            </ul>
                        </div>
                    </li>

                    {{-- Electricity --}}
                    @php($isElectricity = request()->routeIs('electricity.*'))
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link sidebar-group-toggle {{ $isElectricity ? 'active' : '' }}" data-bs-toggle="collapse" href="#collapseElectricity" role="button" aria-expanded="{{ $isElectricity ? 'true' : 'false' }}" aria-controls="collapseElectricity">
                            <span class="sidebar-group-title">
                                <span class="sidebar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"/></svg>
                                </span>
                                <span class="sidebar-label">Electricity</span>
                            </span>
                            <span class="sidebar-chevron">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
                            </span>
                        </a>
                        <div class="collapse {{ $isElectricity ? 'show' : '' }}" id="collapseElectricity">
                            <ul class="sidebar-submenu">
                                <li class="sidebar-subgroup-header">Centralized</li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.centralized.listrik-pln.*') ? 'active' : '' }}" href="{{ route('electricity.centralized.listrik-pln.index') }}">Listrik PLN</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.centralized.payment.*') ? 'active' : '' }}" href="{{ route('electricity.centralized.payment.index') }}">Payment</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.centralized.anomali.*') ? 'active' : '' }}" href="{{ route('electricity.centralized.anomali.index') }}">Anomali Tagihan</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.centralized.bongkar-rampung-mandiri.*') ? 'active' : '' }}" href="{{ route('electricity.centralized.bongkar-rampung-mandiri.index') }}">Bongkar Rampung</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.centralized.listrik-all.*') ? 'active' : '' }}" href="{{ route('electricity.centralized.listrik-all.index') }}">Listrik All</a></li>

                                <li class="sidebar-subgroup-header mt-2">Inbuilding</li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.inbuilding.listrik-inbuilding.*') ? 'active' : '' }}" href="{{ route('electricity.inbuilding.listrik-inbuilding.index') }}">Listrik Inbuilding</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.inbuilding.input-tagihan-ibc.*') ? 'active' : '' }}" href="{{ route('electricity.inbuilding.input-tagihan-ibc.index') }}">Input Data Tagihan IBC</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.inbuilding.payment.*') ? 'active' : '' }}" href="{{ route('electricity.inbuilding.payment.index') }}">Payment IBC</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.inbuilding.anomali.*') ? 'active' : '' }}" href="{{ route('electricity.inbuilding.anomali.index') }}">Anomali Tagihan</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('electricity.inbuilding.inbuilding-all.*') ? 'active' : '' }}" href="{{ route('electricity.inbuilding.inbuilding-all.index') }}">Inbuilding All</a></li>
                            </ul>
                        </div>
                    </li>

                    {{-- PO Monitoring --}}
                    @php($isPo = request()->routeIs('po-hq.*') || request()->routeIs('po-varcost.*') || request()->routeIs('presales.*'))
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link sidebar-group-toggle {{ $isPo ? 'active' : '' }}" data-bs-toggle="collapse" href="#collapsePo" role="button" aria-expanded="{{ $isPo ? 'true' : 'false' }}" aria-controls="collapsePo">
                            <span class="sidebar-group-title">
                                <span class="sidebar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 1.5 13 5h-3.5a.5.5 0 0 1-.5-.5V1.5zM4 6.5A.5.5 0 0 1 4.5 6h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5z"/></svg>
                                </span>
                                <span class="sidebar-label">PO Monitoring</span>
                            </span>
                            <span class="sidebar-chevron">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
                            </span>
                        </a>
                        <div class="collapse {{ $isPo ? 'show' : '' }}" id="collapsePo">
                            <ul class="sidebar-submenu">
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('po-varcost.*') ? 'active' : '' }}" href="{{ route('po-varcost.index') }}">PO Varcost</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('po-hq.*') ? 'active' : '' }}" href="{{ route('po-hq.index') }}">PO HQ</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('presales.*') ? 'active' : '' }}" href="{{ route('presales.index') }}">Presales</a></li>
                            </ul>
                        </div>
                    </li>

                    {{-- Data Potensi --}}
                    @php($isDataPotensi = request()->routeIs('data-potensi.*'))
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link sidebar-group-toggle {{ $isDataPotensi ? 'active' : '' }}" data-bs-toggle="collapse" href="#collapseDataPotensi" role="button" aria-expanded="{{ $isDataPotensi ? 'true' : 'false' }}" aria-controls="collapseDataPotensi">
                            <span class="sidebar-group-title">
                                <span class="sidebar-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1a5 5 0 0 0-5 5v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a6 6 0 1 1 12 0v6a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h1V6a5 5 0 0 0-5-5z"/><path d="M1 14.5a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 0 1h-13a.5.5 0 0 1-.5-.5z"/></svg>
                                </span>
                                <span class="sidebar-label">Data Potensi</span>
                            </span>
                            <span class="sidebar-chevron">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
                            </span>
                        </a>
                        <div class="collapse {{ $isDataPotensi ? 'show' : '' }}" id="collapseDataPotensi">
                            <ul class="sidebar-submenu">
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('data-potensi.site-owner.*') ? 'active' : '' }}" href="{{ route('data-potensi.site-owner.index') }}">Site Owner</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('data-potensi.data-site.*') ? 'active' : '' }}" href="{{ route('data-potensi.data-site.index') }}">Data Site (All Resource)</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('data-potensi.asset-tower.*') ? 'active' : '' }}" href="{{ route('data-potensi.asset-tower.index') }}">Data Asset Tower</a></li>
                                <li><a class="sidebar-subitem-link {{ request()->routeIs('data-potensi.search-all-resource.*') ? 'active' : '' }}" href="{{ route('data-potensi.search-all-resource.index') }}">Search All Resource</a></li>
                            </ul>
                        </div>
                    </li>

                    {{-- Equipment Relocation --}}
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('equipment-relocation.*') ? 'active' : '' }}" href="{{ route('equipment-relocation.index') }}">
                            <span class="sidebar-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M3.05 3.05a7 7 0 0 0 0 9.9.5.5 0 0 1-.707.707 8 8 0 0 1 0-11.314.5.5 0 0 1 .707.707zm2.122 2.122a4 4 0 0 0 0 5.656.5.5 0 1 1-.708.708 5 5 0 0 1 0-7.072.5.5 0 0 1 .708.708zm5.656-.708a.5.5 0 0 1 .708 0 5 5 0 0 1 0 7.072.5.5 0 1 1-.708-.708 4 4 0 0 0 0-5.656.5.5 0 0 1 0-.708zm2.122-2.12a.5.5 0 0 1 .707 0 8 8 0 0 1 0 11.313.5.5 0 0 1-.707-.707 7 7 0 0 0 0-9.9.5.5 0 0 1 0-.707zM10 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                            </span>
                            <span class="sidebar-label">Equipment Relocation</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        {{-- Mobile Backdrop --}}
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        {{-- Main Content Wrapper --}}
        <div class="app-wrapper">
            {{-- App Header (Topbar) --}}
            <header class="app-header">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle navigasi" title="Toggle Navigasi Samping">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2">
                    {{-- Theme Switcher Button (Light / Dark) --}}
                    <button type="button" class="btn btn-sm btn-outline-secondary theme-toggle-btn d-flex align-items-center gap-2" id="btnThemeToggle" title="Ganti Mode Tampilan (Light / Dark)">
                        <span id="themeIconSun" class="d-none">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/></svg>
                        </span>
                        <span id="themeIconMoon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/></svg>
                        </span>
                        <span id="themeLabel" class="small fw-semibold">Dark</span>
                    </button>

                    @auth
                        @php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
                        <div class="dropdown">
                            <button type="button" class="notification-btn position-relative" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="Buka notifikasi" title="Notifikasi">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8 16a2 2 0 0 0 1.995-1.85A1 1 0 0 0 10 14H6a1 1 0 0 0 .005.15A2 2 0 0 0 8 16Zm.104-14.995A1 1 0 0 0 6.9 1.9v.105a5.002 5.002 0 0 0-3.4 4.74v2.31L2.7 11.2A1 1 0 0 0 3.5 13h9a1 1 0 0 0 .8-1.6l-.8-2.145v-2.31a5.002 5.002 0 0 0-3.4-4.74V1.9a1 1 0 0 0-.996-.895Z"/>
                                </svg>
                                @if ($unreadNotificationCount > 0)
                                    <span class="notification-badge">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm border notification-menu">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                    <strong>Notifikasi</strong>
                                    @if ($unreadNotificationCount > 0)
                                        <span class="badge text-bg-warning">{{ $unreadNotificationCount }} baru</span>
                                    @endif
                                </div>
                                @forelse (auth()->user()->notifications()->latest()->get() as $notification)
                                    @php($notificationData = $notification->data)
                                    <a href="{{ route('notifications.read', $notification) }}" class="dropdown-item notification-item {{ $notification->read_at ? '' : 'notification-unread' }}">
                                        <div class="small fw-semibold">{{ $notificationData['title'] ?? 'Notifikasi' }}</div>
                                        <div class="small text-muted text-truncate">{{ $notificationData['message'] ?? '' }}</div>
                                        <div class="small text-muted mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                                    </a>
                                @empty
                                    <div class="small text-muted text-center px-3 py-4">Belum ada notifikasi.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="dropdown">
                            <button type="button" class="user-avatar-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Buka menu pengguna" title="{{ auth()->user()->name }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.494 10.65 10.245 10 8 10c-2.245 0-3.494.65-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
                                </svg>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border user-menu">
                                <li class="px-3 py-2">
                                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                    <div class="small text-body-secondary">{{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'User' }}</div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @if (auth()->user()->hasRole('admin'))
                                    <li>
                                        <a href="{{ route('admin.user-approvals.index') }}" class="dropdown-item">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" class="me-2" aria-hidden="true"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.494 10.65 10.245 10 8 10c-2.245 0-3.494.65-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/></svg>
                                            Persetujuan Akun
                                        </a>
                                    </li>
                                @endif
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" class="me-2" aria-hidden="true"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-5a1.5 1.5 0 0 1-1.5-1.5v-7A1.5 1.5 0 0 1 4.5 3h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h5a.5.5 0 0 1 .5.5Zm1.146-4.146a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708-.708L12.293 11H7.5a.5.5 0 0 1 0-1h4.793l-.853-.854a.5.5 0 0 1 0-.708Z"/></svg>
                                            Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endauth
                </div>
            </header>

            {{-- Main Content --}}
            <main class="container-fluid px-4 py-4 app-main">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>

    {{-- Unified month/year/NOP picker with funnel logo trigger for all paired period and NOP filters --}}
    <script>
        $(function () {
            const filterIconSrc = "{{ asset('images/filter-icon.png') }}";
            const fallbackFunnelSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 512 512" fill="#3b82f6" class="simaster-filter-icon flex-shrink-0"><path d="M472 48H40c-22.1 0-40 17.9-40 40 0 11.8 5.2 23.1 14.3 30.7L192 268.8V448c0 17.7 14.3 32 32 32h64c17.7 0 32-14.3 32-32V268.8l177.7-150.1c9.1-7.7 14.3-18.9 14.3-30.7 0-22.1-17.9-40-40-40z"/></svg>';

            [
                ['#filter-bulan', '#filter-tahun', '#filter-nop'],
                ['#filter-month', '#filter-year', '#filter-nop'],
                ['#dashboard-month', '#dashboard-year', '#dashboard-nop'],
            ].forEach(function ([monthSelector, yearSelector, nopSelector]) {
                const $month = $(monthSelector);
                const $year = $(yearSelector);
                const $nop = nopSelector ? $(nopSelector) : $();

                if (!$month.length || !$year.length) return;
                if ($month.data('simaster-picker-initialized')) return;
                $month.data('simaster-picker-initialized', true);

                const $monthGroup = $month.closest('.d-flex');
                const $yearGroup = $year.closest('.d-flex');
                const $nopGroup = $nop.length ? $nop.closest('.d-flex') : $();
                const hasNop = $nop.length > 0;

                const pickerId = 'simaster-filter-' + Math.random().toString(36).slice(2, 9);

                // Dropdown Wrapper
                const $pickerGroup = $('<div>', {
                    class: 'dropdown simaster-filter-wrapper',
                });

                // Trigger Button dengan Icon Funnel / Corong Filter Biru
                const $pickerButton = $('<button>', {
                    id: pickerId + '-button',
                    type: 'button',
                    class: 'btn simaster-filter-btn d-inline-flex align-items-center gap-2',
                    'aria-expanded': 'false',
                });

                const $btnIcon = $('<img>', {
                    src: filterIconSrc,
                    width: 18,
                    height: 18,
                    alt: 'Filter',
                    class: 'simaster-filter-icon flex-shrink-0',
                }).on('error', function () {
                    $(this).replaceWith(fallbackFunnelSvg);
                });

                const $btnLabel = $('<span>', {
                    class: 'fw-semibold small',
                    text: 'Filter',
                });

                const $badge = $('<span>', {
                    class: 'badge simaster-filter-badge text-truncate',
                    style: 'max-width: 220px;',
                    text: 'Memuat...',
                });

                const $caret = $('<span class="small opacity-75 ms-auto">▾</span>');

                $pickerButton.append($btnIcon, $btnLabel, $badge, $caret);

                // Popover Dropdown Menu
                const $pickerMenu = $('<div>', {
                    class: 'dropdown-menu p-3 simaster-filter-menu',
                    style: hasNop ? 'min-width: 320px; max-width: 360px;' : 'min-width: 280px; max-width: 320px;',
                }).on('click', function (e) {
                    e.stopPropagation();
                });

                // Header Popover
                const $menuHeader = $('<div>', {
                    class: 'd-flex align-items-center justify-content-between pb-2 mb-2 border-bottom',
                });
                const $headerTitle = $('<div>', {
                    class: 'd-flex align-items-center gap-2',
                }).append(
                    $('<img>', {
                        src: filterIconSrc,
                        width: 16,
                        height: 16,
                        alt: 'Filter',
                        class: 'simaster-filter-icon flex-shrink-0',
                    }).on('error', function () {
                        $(this).replaceWith(fallbackFunnelSvg);
                    }),
                    $('<strong>', { class: 'small', text: hasNop ? 'Filter Periode & NOP' : 'Filter Periode' })
                );
                const $closeBtn = $('<button>', {
                    type: 'button',
                    class: 'btn-close btn-close-sm simaster-period-close',
                    'aria-label': 'Tutup',
                }).on('click', function () {
                    $pickerMenu.removeClass('show');
                    $pickerButton.attr('aria-expanded', 'false');
                });
                $menuHeader.append($headerTitle, $closeBtn);

                // Section: Tahun
                const $yearSection = $('<div>', { class: 'mb-2' });
                const $yearRow = $('<div>', { class: 'd-flex align-items-center justify-content-between' });
                $yearRow.append($('<span>', { class: 'small fw-semibold text-secondary', text: 'Tahun:' }));

                const $yearStepper = $('<div>', { class: 'd-flex align-items-center gap-1' });
                const $previousYear = $('<button>', {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm simaster-year-btn',
                    text: '‹',
                    title: 'Tahun sebelumnya',
                    'aria-label': 'Tahun sebelumnya',
                });
                const $yearText = $('<input>', {
                    class: 'form-control form-control-sm text-center fw-bold simaster-period-year',
                    type: 'number',
                    min: 2000,
                    max: 2100,
                    title: 'Ketik tahun',
                    'aria-label': 'Tahun',
                    style: 'width: 80px; padding: 2px 4px;',
                });
                const $nextYear = $('<button>', {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm simaster-year-btn',
                    text: '›',
                    title: 'Tahun berikutnya',
                    'aria-label': 'Tahun berikutnya',
                });
                $yearStepper.append($previousYear, $yearText, $nextYear);
                $yearRow.append($yearStepper);
                $yearSection.append($yearRow);

                // Section: Bulan
                const $monthSection = $('<div>', { class: 'mb-2' });
                const $monthHeader = $('<div>', { class: 'd-flex align-items-center justify-content-between mb-1' });
                $monthHeader.append($('<span>', { class: 'small fw-semibold text-secondary', text: 'Bulan:' }));

                const $allMonths = $('<label>', {
                    class: 'form-check-label small d-flex align-items-center gap-1 text-primary mb-0',
                    style: 'cursor: pointer;',
                });
                const $allMonthsInput = $('<input>', {
                    class: 'form-check-input mt-0 simaster-all-months',
                    type: 'checkbox',
                });
                $allMonths.append($allMonthsInput, $('<span>', { class: 'fw-semibold', text: 'Semua bulan' }));
                $monthHeader.append($allMonths);

                const $monthGrid = $('<div>', {
                    class: 'd-grid gap-1',
                    style: 'grid-template-columns: repeat(4, 1fr);',
                });
                const months = [
                    [1, 'Jan'], [2, 'Feb'], [3, 'Mar'], [4, 'Apr'],
                    [5, 'Mei'], [6, 'Jun'], [7, 'Jul'], [8, 'Ags'],
                    [9, 'Sep'], [10, 'Okt'], [11, 'Nov'], [12, 'Des'],
                ];
                months.forEach(function ([monthNum, monthName]) {
                    $monthGrid.append($('<button>', {
                        type: 'button',
                        class: 'btn btn-sm btn-outline-secondary simaster-month-btn',
                        text: monthName,
                        'data-month': monthNum,
                    }));
                });
                $monthSection.append($monthHeader, $monthGrid);

                // Section: NOP (jika modul memiliki NOP)
                let $nopSection = null;
                let $nopSelectInside = null;
                let $nopSearchInput = null;
                if (hasNop) {
                    $nopSection = $('<div>', { class: 'simaster-nop-section pt-2 border-top mb-2' });
                    $nopSection.append($('<div>', { class: 'small fw-semibold text-secondary mb-1', text: 'Nomor Objek Pajak (NOP):' }));
                    $nopSearchInput = $('<input>', {
                        type: 'text',
                        class: 'form-control form-control-sm mb-1 simaster-nop-search',
                        placeholder: 'Cari NOP...',
                    }).on('input', function () {
                        const q = this.value.toLowerCase().trim();
                        if ($nopSelectInside) {
                            $nopSelectInside.find('option').each(function () {
                                const txt = $(this).text().toLowerCase();
                                const val = String($(this).val()).toLowerCase();
                                const match = !q || val === '' || txt.includes(q) || val.includes(q);
                                $(this).toggle(match);
                            });
                        }
                    });

                    $nopSelectInside = $('<select>', {
                        class: 'form-select form-select-sm simaster-picker-nop',
                        style: 'width: 100%;',
                    });

                    $nopSection.append($nopSearchInput, $nopSelectInside);
                }

                // Section: Footer / Apply Button
                const $footerSection = $('<div>', { class: 'pt-2 border-top mt-2' });
                const $applyBtn = $('<button>', {
                    type: 'button',
                    class: 'btn btn-sm btn-primary w-100 d-flex align-items-center justify-content-center gap-1 text-white shadow-sm simaster-period-apply',
                    html: '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/></svg> <span>Tutup &amp; Terapkan</span>',
                }).on('click', function () {
                    $pickerMenu.removeClass('show');
                    $pickerButton.attr('aria-expanded', 'false');
                });
                $footerSection.append($applyBtn);

                $pickerMenu.append($menuHeader, $yearSection, $monthSection);
                if (hasNop && $nopSection) {
                    $pickerMenu.append($nopSection);
                }
                $pickerMenu.append($footerSection);

                $pickerGroup.append($pickerButton, $pickerMenu);

                // Letakkan picker tepat di tempat filter awal berada
                const $targetAnchor = ($yearGroup.length && $yearGroup.index() < $monthGroup.index()) ? $yearGroup : $monthGroup;
                $targetAnchor.before($pickerGroup);

                // Sembunyikan elemen filter terpisah lama
                $monthGroup.add($yearGroup).add($nopGroup).addClass('d-none');

                function selectedYear() {
                    return Number($year.val()) || new Date().getFullYear();
                }

                function isAllMonths() {
                    const value = $month.val();
                    return value === '' || value === 'all' || value === null;
                }

                function ensureYearOption(year) {
                    if (!$year.find('option').filter(function () {
                        return String($(this).val()) === String(year);
                    }).length) {
                        $year.append($('<option>').val(year).text(year));
                    }
                    $year.val(String(year));
                }

                function syncNopOptions() {
                    if (!hasNop || !$nopSelectInside) return;
                    const currentVal = $nop.val();
                    $nopSelectInside.empty();
                    let count = 0;
                    $nop.find('option').each(function () {
                        $nopSelectInside.append($('<option>').val(this.value).text(this.text));
                        count++;
                    });
                    $nopSelectInside.val(currentVal);
                    if ($nopSearchInput) {
                        $nopSearchInput.toggle(count > 8);
                    }
                }

                function updatePicker() {
                    const year = selectedYear();
                    const all = isAllMonths();
                    const month = Number($month.val());
                    $yearText.val(String(year));

                    let periodText = all
                        ? 'Semua Bulan ' + year
                        : (months[month - 1]?.[1] || 'Bulan ' + month) + ' ' + year;

                    let badgeText = periodText;
                    let fullTitle = periodText;

                    if (hasNop) {
                        syncNopOptions();
                        const nopVal = $nop.val();
                        const selectedOption = $nop.find('option:selected');
                        const nopText = selectedOption.length ? selectedOption.text().trim() : (nopVal || 'Semua NOP');
                        const isAllNop = !nopVal || nopVal === '' || nopText.toLowerCase().includes('semua');
                        const displayNop = isAllNop ? 'Semua NOP' : (nopText.startsWith('NOP') ? nopText : 'NOP ' + nopText);

                        badgeText = periodText + ' · ' + displayNop;
                        fullTitle = 'Filter: ' + periodText + ' | ' + displayNop;

                        if ($nopSelectInside) {
                            $nopSelectInside.val(nopVal);
                        }
                    } else {
                        fullTitle = 'Filter: ' + periodText;
                    }

                    $badge.text(badgeText);
                    $pickerButton.attr('title', fullTitle);

                    $allMonthsInput.prop('checked', all);

                    const availableMonthsInSelect = $month.find('option').map(function () {
                        const val = $(this).val();
                        return (val !== '' && val !== 'all') ? Number(val) : null;
                    }).get().filter(Boolean);

                    $monthGrid.find('.simaster-month-btn')
                        .removeClass('active')
                        .toggleClass('btn-primary text-white', false)
                        .each(function () {
                            const m = Number($(this).data('month'));
                            const isAvailable = !availableMonthsInSelect.length || availableMonthsInSelect.includes(m);
                            $(this).prop('disabled', !isAvailable);
                            $(this).css('opacity', isAvailable ? '1' : '0.4');
                            if (!all && m === month) {
                                $(this).addClass('active btn-primary text-white');
                            }
                        });

                    const isDisabled = $year.prop('disabled') || $month.prop('disabled') || (hasNop && $nop.prop('disabled'));
                    $pickerButton.prop('disabled', isDisabled);
                    if ($nopSelectInside) {
                        $nopSelectInside.prop('disabled', isDisabled);
                    }
                }

                $pickerButton.on('click', function () {
                    $pickerMenu.toggleClass('show');
                    $(this).attr('aria-expanded', $pickerMenu.hasClass('show'));
                    updatePicker();
                });
                $previousYear.on('click', function () {
                    ensureYearOption(selectedYear() - 1);
                    $year.trigger('change');
                    updatePicker();
                });
                $nextYear.on('click', function () {
                    ensureYearOption(selectedYear() + 1);
                    $year.trigger('change');
                    updatePicker();
                });
                $yearText.on('change', function () {
                    const year = Number(this.value);
                    if (!Number.isInteger(year) || year < 2000 || year > 2100) {
                        updatePicker();
                        return;
                    }
                    ensureYearOption(year);
                    $year.trigger('change');
                    updatePicker();
                }).on('keydown', function (event) {
                    if (event.key === 'Enter') {
                        $(this).trigger('change');
                    }
                });
                $allMonthsInput.on('change', function () {
                    $month.val($month.find('option[value="all"]').length ? 'all' : '');
                    $month.trigger('change');
                    updatePicker();
                });
                $monthGrid.on('click', '.simaster-month-btn', function () {
                    if ($(this).prop('disabled')) return;
                    $month.val(String($(this).data('month'))).trigger('change');
                    updatePicker();
                });

                if (hasNop && $nopSelectInside) {
                    $nopSelectInside.on('change', function () {
                        $nop.val(this.value).trigger('change');
                        updatePicker();
                    });
                }

                $month.add($year).add($nop).on('change simaster:period-sync', updatePicker);
                new MutationObserver(updatePicker).observe($month[0], { childList: true });
                new MutationObserver(updatePicker).observe($year[0], { childList: true });
                if (hasNop && $nop[0]) {
                    new MutationObserver(function () {
                        syncNopOptions();
                        updatePicker();
                    }).observe($nop[0], { childList: true, subtree: true, attributes: true });
                }

                $(document).on('click', function (event) {
                    if (!$(event.target).closest($pickerGroup).length) {
                        $pickerMenu.removeClass('show');
                        $pickerButton.attr('aria-expanded', 'false');
                    }
                });

                updatePicker();
            });

            const $dashboardPeriod = $('#period-select');
            if ($dashboardPeriod.length) {
                const months = [
                    [1, 'Jan'], [2, 'Feb'], [3, 'Mar'], [4, 'Apr'],
                    [5, 'Mei'], [6, 'Jun'], [7, 'Jul'], [8, 'Ags'],
                    [9, 'Sep'], [10, 'Okt'], [11, 'Nov'], [12, 'Des'],
                ];
                const $button = $('<button>', {
                    type: 'button',
                    class: 'btn btn-outline-secondary btn-sm text-start',
                    style: 'min-width: 200px;',
                    'aria-expanded': 'false',
                });
                const $menu = $('<div>', {
                    class: 'dropdown-menu p-3 shadow',
                    style: 'min-width: 260px;',
                });
                const $yearInput = $('<input>', {
                    type: 'number', min: 2000, max: 2100,
                    class: 'form-control form-control-sm text-center',
                    style: 'width: 90px;',
                    title: 'Ketik tahun',
                    'aria-label': 'Tahun dashboard',
                });
                const $months = $('<div>', {
                    class: 'd-grid gap-1 mt-2',
                    style: 'grid-template-columns: repeat(3, 1fr);',
                });
                const $all = $('<label>', { class: 'form-check small mt-2' }).append(
                    $('<input>', { type: 'checkbox', class: 'form-check-input' }),
                    $('<span>', { class: 'form-check-label', text: ' Semua bulan' })
                );
                const $yearRow = $('<div>', {
                    class: 'd-flex align-items-center justify-content-between',
                });
                const $prev = $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: '‹' });
                const $next = $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: '›' });
                $yearRow.append($prev, $yearInput, $next);
                months.forEach(function ([month, name]) {
                    $months.append($('<button>', {
                        type: 'button',
                        class: 'btn btn-sm btn-outline-secondary',
                        text: name,
                        'data-month': month,
                    }));
                });
                $menu.append($yearRow, $all, $months);
                $dashboardPeriod.after($('<div>', { class: 'dropdown d-inline-block' }).append($button, $menu));
                $dashboardPeriod.addClass('d-none');

                function dashboardYear() {
                    const value = String($dashboardPeriod.val() || '').split('-')[0];
                    return Number(value) || new Date().getFullYear();
                }
                function dashboardMonth() {
                    return Number(String($dashboardPeriod.val() || '').split('-')[1]) || 0;
                }
                function syncDashboardPicker() {
                    const year = dashboardYear();
                    const month = dashboardMonth();
                    $yearInput.val(year);
                    $button.text(month === 0 ? 'Semua bulan ' + year : (months[month - 1]?.[1] || 'Pilih bulan') + ' ' + year);
                    $all.find('input').prop('checked', month === 0);
                    $months.children().toggleClass('btn-primary text-white', false)
                        .filter('[data-month="' + month + '"]').addClass('btn-primary text-white');
                }
                function setDashboardPeriod(year, month) {
                    const value = year + '-' + month;
                    if (!$dashboardPeriod.find('option[value="' + value + '"]').length) {
                        $dashboardPeriod.append($('<option>').val(value).text(month ? months[month - 1][1] + ' ' + year : 'Semua bulan ' + year));
                    }
                    $dashboardPeriod.val(value).trigger('change');
                    syncDashboardPicker();
                }
                $button.on('click', function () {
                    $menu.toggleClass('show');
                    $button.attr('aria-expanded', $menu.hasClass('show'));
                    syncDashboardPicker();
                });
                $prev.on('click', function () { setDashboardPeriod(dashboardYear() - 1, dashboardMonth()); });
                $next.on('click', function () { setDashboardPeriod(dashboardYear() + 1, dashboardMonth()); });
                $yearInput.on('change', function () {
                    const year = Number(this.value);
                    if (year >= 2000 && year <= 2100) setDashboardPeriod(year, dashboardMonth());
                });
                $all.on('change', 'input', function () { setDashboardPeriod(dashboardYear(), 0); });
                $months.on('click', 'button', function () {
                    setDashboardPeriod(dashboardYear(), Number($(this).data('month')));
                });
                $dashboardPeriod.on('change', syncDashboardPicker);
                new MutationObserver(syncDashboardPicker).observe($dashboardPeriod[0], { childList: true });
                $(document).on('click', function (event) {
                    if (!$(event.target).closest($button.parent()).length) {
                        $menu.removeClass('show');
                        $button.attr('aria-expanded', 'false');
                    }
                });
                syncDashboardPicker();
            }
        });
    </script>

    {{-- Global Theme Toggle Logic --}}
    <script>
        function applyThemeUI(theme) {
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('simawar_theme', theme);

            const isDark = (theme === 'dark');
            if (isDark) {
                $('#themeIconSun').removeClass('d-none');
                $('#themeIconMoon').addClass('d-none');
                $('#themeLabel').text('Light');
            } else {
                $('#themeIconSun').addClass('d-none');
                $('#themeIconMoon').removeClass('d-none');
                $('#themeLabel').text('Dark');
            }

            // Dispatch global event for charts to adapt colors
            window.dispatchEvent(new CustomEvent('simaster:theme-changed', { detail: { theme: theme } }));
        }

        $(function () {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            applyThemeUI(currentTheme);

            $('#btnThemeToggle').on('click', function () {
                const active = document.documentElement.getAttribute('data-bs-theme') || 'light';
                const next = (active === 'dark') ? 'light' : 'dark';
                applyThemeUI(next);
            });
        });
    </script>

    {{-- Sidebar Toggle & Persistence Logic --}}
    <script>
        $(function () {
            const $layout = $('#appLayout');
            const $sidebarToggleBtn = $('#sidebarToggleBtn');
            const $sidebarCloseBtn = $('#sidebarCloseBtn');
            const $sidebarBackdrop = $('#sidebarBackdrop');

            // Restore desktop collapsed state from localStorage
            const savedSidebarState = localStorage.getItem('simaster_sidebar_collapsed');
            if (savedSidebarState === 'true' && window.innerWidth >= 992) {
                $layout.addClass('sidebar-collapsed');
            }

            function toggleSidebar() {
                if (window.innerWidth < 992) {
                    $layout.toggleClass('sidebar-open');
                } else {
                    $layout.toggleClass('sidebar-collapsed');
                    localStorage.setItem('simaster_sidebar_collapsed', $layout.hasClass('sidebar-collapsed'));
                    setTimeout(function () {
                        window.dispatchEvent(new Event('resize'));
                    }, 300);
                }
            }

            function closeMobileSidebar() {
                $layout.removeClass('sidebar-open');
            }

            $sidebarToggleBtn.on('click', toggleSidebar);
            $sidebarCloseBtn.on('click', closeMobileSidebar);
            $sidebarBackdrop.on('click', closeMobileSidebar);

            // Close mobile sidebar upon clicking navigation links
            $(document).on('click', '.sidebar-nav-link:not(.sidebar-group-toggle), .sidebar-subitem-link', function () {
                if (window.innerWidth < 992) {
                    closeMobileSidebar();
                }
            });

            $(window).on('resize', function () {
                if (window.innerWidth >= 992) {
                    closeMobileSidebar();
                }
            });
        });
    </script>

    {{-- Shared compact contextual filter. Existing controls are moved, not
         cloned, so every page keeps its current IDs and event handlers. --}}
    <script>
        $(function () {
            document.querySelectorAll('[data-simaster-filter-panel]').forEach(function (source, index) {
                if (source.dataset.simasterFilterReady === 'true') return;
                source.dataset.simasterFilterReady = 'true';

                source.classList.add('simaster-filter-source');

                const placeInHeader = function (control) {
                    const heading = document.querySelector('.app-main h1');
                    const headingRow = heading ? heading.closest('.d-flex') : null;
                    if (headingRow) {
                        headingRow.classList.add('flex-wrap', 'gap-2');
                        const headingOwner = Array.from(headingRow.children).find(child => child === heading || child.contains(heading));
                        const actionNodes = Array.from(headingRow.children).filter(child => child !== headingOwner && child !== source);
                        let actionArea = actionNodes.length === 1 && actionNodes[0].classList.contains('d-flex')
                            ? actionNodes[0]
                            : null;

                        if (!actionArea) {
                            actionArea = document.createElement('div');
                            actionArea.className = 'd-flex align-items-center gap-2 flex-wrap';
                            actionNodes.forEach(node => actionArea.appendChild(node));
                            headingRow.appendChild(actionArea);
                        }
                        actionArea.appendChild(control);
                        return actionArea;
                    }

                    const launcher = document.createElement('div');
                    launcher.className = 'd-flex justify-content-end mb-3';
                    source.parentNode.insertBefore(launcher, source);
                    launcher.appendChild(control);
                    return launcher;
                };

                const moveExportActions = function (actionArea) {
                    source.querySelectorAll('a#btn-export, a#download-excel').forEach(link => actionArea.prepend(link));
                };

                const periodPicker = source.querySelector('.simaster-filter-wrapper');
                const periodMenu = periodPicker?.querySelector('.simaster-filter-menu');

                // Header setiap modul selalu memakai satu tombol Filter biru.
                // Kontrol periode/NOP yang sudah ada hanya ditampilkan setelah
                // tombol tersebut diklik, sehingga event dan struktur lama tetap utuh.
                const popupId = `simaster-filter-popup-${index + 1}`;
                const trigger = document.createElement('button');
                trigger.type = 'button';
                trigger.className = 'simaster-context-filter-trigger';
                trigger.setAttribute('aria-controls', popupId);
                trigger.setAttribute('aria-expanded', 'false');
                trigger.textContent = 'Filter';
                const actionArea = placeInHeader(trigger);
                moveExportActions(actionArea);

                const overlay = document.createElement('div');
                overlay.className = 'simaster-filter-overlay';
                overlay.setAttribute('aria-hidden', 'true');
                overlay.innerHTML = `
                    <aside class="simaster-filter-drawer" id="${popupId}" role="dialog" aria-modal="true" aria-label="Filter">
                        <div class="simaster-filter-drawer-header">
                            <button type="button" class="btn-close simaster-filter-close ms-auto" aria-label="Tutup filter"></button>
                        </div>
                        <div class="simaster-filter-drawer-body"></div>
                        ${periodMenu ? '' : `<div class="simaster-filter-drawer-footer">
                            <button type="button" class="btn btn-primary w-100 simaster-filter-apply">✓ Tutup &amp; Terapkan</button>
                        </div>`}
                    </aside>`;
                const popupBody = overlay.querySelector('.simaster-filter-drawer-body');

                if (periodPicker && periodMenu) {
                    source.querySelectorAll('.badge.text-bg-light').forEach(badge => {
                        const group = badge.closest('.d-flex');
                        if (group) group.classList.add('d-none');
                    });
                    source.querySelectorAll('.text-body-secondary.small').forEach(text => {
                        if (!text.closest('label')) text.classList.add('simaster-filter-muted-copy');
                    });

                    popupBody.appendChild(periodPicker);
                    const footer = periodMenu.querySelector('.pt-2.border-top.mt-2');
                    periodMenu.classList.add('simaster-filter-context-menu');
                    if (footer) {
                        periodMenu.insertBefore(source, footer);
                    } else {
                        periodMenu.appendChild(source);
                    }
                } else {
                    // Modul yang tidak memakai periode/NOP tetap menyimpan
                    // kontrol aslinya di pop-up kecil yang sama.
                    popupBody.appendChild(source);
                }
                document.body.appendChild(overlay);

                const closeButton = overlay.querySelector('.simaster-filter-close');
                const positionPopup = function () {
                    const rect = trigger.getBoundingClientRect();
                    const popup = overlay.querySelector('.simaster-filter-drawer');
                    const width = Math.min(390, window.innerWidth - 24);
                    popup.style.width = `${width}px`;
                    popup.style.left = `${Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12))}px`;
                    popup.style.top = `${Math.min(rect.bottom + 8, window.innerHeight - 84)}px`;
                };
                const openPopup = function () {
                    if (periodMenu) periodMenu.classList.add('show');
                    positionPopup();
                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                    trigger.setAttribute('aria-expanded', 'true');
                    window.setTimeout(() => closeButton.focus(), 0);
                };
                const closePopup = function () {
                    if (periodMenu) periodMenu.classList.remove('show');
                    overlay.classList.remove('is-open');
                    overlay.setAttribute('aria-hidden', 'true');
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                };

                trigger.addEventListener('click', openPopup);
                closeButton.addEventListener('click', closePopup);
                overlay.querySelector('.simaster-filter-apply')?.addEventListener('click', closePopup);
                periodMenu?.querySelector('.simaster-period-close')?.addEventListener('click', closePopup);
                periodMenu?.querySelector('.simaster-period-apply')?.addEventListener('click', closePopup);
                overlay.addEventListener('click', event => {
                    if (event.target === overlay) closePopup();
                });
                window.addEventListener('resize', positionPopup);
                window.addEventListener('scroll', positionPopup, true);
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && overlay.classList.contains('is-open')) closePopup();
                });
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
