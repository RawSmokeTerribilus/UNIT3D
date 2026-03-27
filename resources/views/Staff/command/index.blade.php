@extends('layout.with-main')

@section('title')
    <title>Commands - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Commands - {{ __('staff.staff-dashboard') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">Commands</li>
@endsection

@section('page', 'page__staff-command--index')

@section('main')
    @if (session('info'))
        <div
            style="
                background: linear-gradient(135deg, #2d3436 0%, #636e72 100%);
                border-left: 4px solid #00b894;
                padding: 1rem;
                margin-bottom: 2rem;
                border-radius: 0.5rem;
                color: #fff;
                font-family: monospace;
                font-size: 0.875rem;
                white-space: pre-wrap;
                overflow-x: auto;
            "
        >
            {{ session('info') }}
        </div>
    @endif

    {{-- EMERGENCY SECTION --}}
    <div
        style="
            background: #e74c3c;
            color: white;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
            border-left: 4px solid #c0392b;
        "
    >
        <strong>🚨 EMERGENCY ESCAPE HATCH:</strong> If you're stuck in maintenance mode, visit:
        <br />
        <code style="background: rgba(0,0,0,0.3); padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
            /dashboard/commands/emergency-disable-maintenance
        </code>
        <br />
        <small>This endpoint is ALWAYS accessible and will forcefully disable maintenance mode.</small>
    </div>

    <div
        style="
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        "
    >
        {{-- Maintenance & Site Control Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🛡️ Maintenance & Site Control</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/maintenance-enable') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Enable maintenance mode (site accessible only with your IP)"
                        >
                            Enable maintenance
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/maintenance-disable') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Disable maintenance mode and enable public access"
                        >
                            Disable maintenance
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Caching & Performance Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">⚡ Caching & Performance</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear application cache">
                            Clear cache
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-view-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear compiled views cache">
                            Clear views
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-route-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear compiled routes cache">
                            Clear routes
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-config-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear configuration cache">
                            Clear config
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-all-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear ALL cache at once">
                            Clear all cache
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/set-all-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Rebuild and set all cache">
                            Set all cache
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/flush-queue') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Clear Redis queue (CRITICAL after token changes)"
                            style="background-color: #e74c3c; color: white;"
                        >
                            🔴 Flush Redis queue
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/optimize-clear') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Clear optimization cache">
                            Clear optimize
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Critical Data Operations Panel --}}
        <section class="panelV2" style="grid-column: span 1">
            <h2 class="panel__heading" style="background: #e74c3c; color: white; padding: 0.5rem;">
                🔴 CRITICAL Data Operations
            </h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/update-email-blacklist') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Update email domain blacklist from remote source"
                            style="background-color: #e74c3c; color: white;"
                        >
                            Update email blacklist
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/telegram-webhook') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Register Telegram bot webhook with API"
                            style="background-color: #3498db; color: white;"
                        >
                            Register Telegram
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/meilisearch-fix') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Flush and repair Meilisearch indices"
                            style="background-color: #f39c12; color: white;"
                        >
                            Flush Meilisearch
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/scout-reindex') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Reindex all torrents in Meilisearch"
                            style="background-color: #f39c12; color: white;"
                        >
                            Reindex Meilisearch
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clean-failed-logins') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Delete ALL failed login attempts (DB only, logs preserved)"
                            style="background-color: #95a5a6;"
                        >
                            Clean failed logins
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Peer & Torrent Management Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🌱 Peer & Torrent Management</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/flush-old-peers') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Auto-flush peers inactive > 2 hours">
                            Flush old peers
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/reset-user-flushes') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Reset daily peer flush quota for all users"
                        >
                            Reset user flushes
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/sync-peers') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Sync peer data and consistency">
                            Sync peers
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/sync-torrents-meilisearch') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Sync torrents to Meilisearch">
                            Sync torrents
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- User & Cleanup Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">👥 User & Cleanup</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/ban-disposable-users') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Ban users with disposable email addresses"
                        >
                            Ban disposable users
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/deactivate-warnings') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Deactivate expired user warnings">
                            Deactivate warnings
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/generate-telegram-tokens') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Generate Telegram verification tokens">
                            Gen. Telegram tokens
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Testing & Utilities Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🔧 Testing & Utilities</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/test-email') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Send test email">
                            Test email
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/storage-link') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Create public storage symlink">
                            Storage link
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <style>
        .panel__heading {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            color: white;
            padding: 0.75rem;
            border-radius: 0.25rem 0.25rem 0 0;
        }

        .form__button {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            border: 1px solid #bdc3c7;
            background-color: #ecf0f1;
            color: #2c3e50;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form__button:hover {
            background-color: #d5dbdb;
            border-color: #34495e;
            transform: translateY(-1px);
        }

        .form__button:active {
            transform: translateY(0);
        }
    </style>
@endsection
