@extends('admin.core.admin')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item">Pedidos</li>
                        <li class="breadcrumb-item active">Gerenciamento</li>
                    </ol>
                    <h4 class="page-title">Gerenciamento de pedidos</h4>
                </div>
                <div
                    class="orders-toolbar d-flex align-items-center justify-content-end flex-wrap gap-2"
                    id="orders-realtime-controls"
                    data-tenant-id="{{ $tenantId ?? '' }}"
                    data-snapshot-url="{{ $ordersSnapshotUrl }}"
                >
                    <span id="orders-connection" class="orders-connection" data-state="reconnecting" role="status" aria-live="polite">
                        <span class="orders-connection-dot" aria-hidden="true"></span>
                        <span id="orders-connection-label">Reconectando</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-light" id="orders-sound-toggle" aria-pressed="false">
                        <i class="mdi mdi-bell-off-outline me-1" aria-hidden="true"></i>
                        <span>Ativar sons</span>
                    </button>
                    <div class="btn-group" role="group" aria-label="Modo de visualização dos pedidos">
                        <button type="button" class="btn btn-primary" id="show-kanban" aria-pressed="true">
                            <i class="mdi mdi-view-column-outline me-1"></i> Kanban
                        </button>
                        <button type="button" class="btn btn-light" id="show-list" aria-pressed="false">
                            <i class="mdi mdi-view-list-outline me-1"></i> Lista
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .content-page,
        .content-page > .content,
        .content-page > .content > .container-fluid,
        .content-page .card,
        .content-page .card-body,
        #orders-kanban {
            min-width: 0;
            max-width: 100%;
        }

        .orders-toolbar {
            flex: 1 1 auto;
        }

        .orders-new-order-alert {
            border-width: 2px;
            font-weight: 600;
            animation: orders-new-order-pulse 1.1s ease-in-out 4;
        }

        @keyframes orders-new-order-pulse {
            50% {
                box-shadow: 0 0 0 .35rem rgba(240, 173, 78, .3);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .orders-new-order-alert {
                animation: none;
            }
        }

        .orders-connection {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            color: #6c757d;
            font-size: .82rem;
            white-space: nowrap;
        }

        .orders-connection-dot {
            width: .55rem;
            height: .55rem;
            border-radius: 50%;
            background: #adb5bd;
        }

        .orders-connection[data-state="connected"] .orders-connection-dot {
            background: #22a06b;
        }

        .orders-connection[data-state="reconnecting"] .orders-connection-dot,
        .orders-connection[data-state="syncing"] .orders-connection-dot {
            background: #f0ad4e;
        }

        .orders-connection[data-state="disconnected"] .orders-connection-dot {
            background: #dc3545;
        }

        .orders-board {
            display: flex;
            gap: 1rem;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: auto;
            padding: .25rem .25rem 1rem;
            align-items: flex-start;
            overscroll-behavior-x: contain;
        }

        .orders-column {
            flex: 0 0 min(300px, 100%);
            min-width: 0;
            max-height: calc(100vh - 230px);
            overflow-y: auto;
            background: var(--bs-tertiary-bg, #f3f5f7);
            border-radius: .5rem;
            padding: .75rem;
        }

        .orders-column[data-status="pending"] {
            --stage-color: #f0ad4e;
            --stage-bg: #fff8e6;
        }

        .orders-column[data-status="confirmed"] {
            --stage-color: #17a2b8;
            --stage-bg: #e8f8fb;
        }

        .orders-column[data-status="preparing"] {
            --stage-color: #536de6;
            --stage-bg: #eef0ff;
        }

        .orders-column[data-status="ready"] {
            --stage-color: #22a06b;
            --stage-bg: #e9f8f0;
        }

        .orders-column[data-status="out_for_delivery"] {
            --stage-color: #8a63d2;
            --stage-bg: #f2edfc;
        }

        .orders-column[data-status="delivered"] {
            --stage-color: #495057;
            --stage-bg: #eceff1;
        }

        .orders-column[data-status="canceled"] {
            --stage-color: #dc3545;
            --stage-bg: #fff0f1;
        }

        .orders-column-heading {
            position: sticky;
            top: -.75rem;
            z-index: 1;
            background: var(--stage-bg, #f3f5f7);
            border-top: 3px solid var(--stage-color, #6c757d);
            padding: .5rem .25rem .75rem;
        }

        .order-card {
            border: 0;
            margin-bottom: .75rem;
        }

        .order-card:last-child {
            margin-bottom: 0;
        }

        .order-card-action-pending {
            opacity: .7;
            pointer-events: none;
        }

        .order-item-details {
            white-space: pre-wrap;
            font-size: .75rem;
        }

        .bg-soft-purple {
            color: var(--ct-primary-text);
            background: var(--ct-primary-bg-subtle);
        }

        .text-purple {
            color: var(--ct-primary-text) !important;
        }

        .bg-soft-dark {
            color: var(--ct-secondary-text);
            background: var(--ct-secondary-bg-subtle);
        }

        @media (max-width: 575.98px) {
            .page-title-box {
                align-items: flex-start !important;
                gap: .75rem;
                flex-direction: column;
            }

            .orders-toolbar {
                justify-content: flex-start !important;
            }

            .orders-column {
                max-height: 70vh;
            }
        }
    </style>

    <div id="orders-feedback" class="d-none" role="alert" aria-live="assertive"></div>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif
    @if ($errors->has('status'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('status') }}</div>
    @endif

    @include('admin.partials.administration-orders-content')

    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div>
                        <a href="https://www.whi.dev.br/" target="_blank" rel="noopener noreferrer" style="color:#94a0ad;">
                            <script>document.write(new Date().getFullYear())</script>
                            © WHI - Web de Alta Inspiração
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-none d-md-flex gap-4 align-items-center justify-content-md-end footer-links">
                        <a href="https://www.whi.dev.br/" target="_blank" rel="noopener noreferrer" class="text-muted">Sobre a WHI</a>
                        <a href="https://wa.me/5571992768360" target="_blank" rel="noopener noreferrer" class="text-muted">Fale conosco</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
@endsection

@section('scripts')
    @vite('resources/js/admin/orders-realtime.js')
@endsection
