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