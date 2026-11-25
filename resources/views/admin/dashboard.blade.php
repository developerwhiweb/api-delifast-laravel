@extends('admin.core.admin')

@section('content')
    {{-- HEADER --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="mdi mdi-view-dashboard-outline me-2 text-primary"></i>
                {{ __('dashboard.title_dashboard') }}
            </h1>
            <p class="text-muted small mb-0">
                Gerencie todos os módulos do seu site
            </p>
        </div>

    </div>

    <style>
        .announcement-badge {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin-bottom: 24px;
            border: 1px solid transparent;
            border-radius: 12px;
            transition: .2s ease;
        }

        .announcement-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(0, 0, 0, .07);
        }

        .announcement-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 18px;
        }

        .announcement-content {
            flex: 1;
            min-width: 0;
        }

        .announcement-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 2px;
            opacity: .75;
        }

        .announcement-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
        }

        .announcement-text {
            font-size: 13px;
            line-height: 1.4;
            margin-top: 2px;
        }

        .announcement-text p {
            margin: 0;
        }

        .announcement-action {
            flex-shrink: 0;
        }

        .announcement-button {
            border: 0;
            font-weight: 600;
            padding: 7px 12px;
            transition: .2s ease;
        }

        .announcement-button:hover {
            transform: translateX(2px);
        }

        /* Geral */
        .announcement-general {
            background: #eef5ff;
            border-color: #a8ccff;
            color: #021127;
        }

        .announcement-general .announcement-icon {
            background: #cfe2ff;
        }

        .announcement-general .announcement-button {
            background: #0d6efd;
            color: #fff;
        }

        /* Manutenção */
        .announcement-maintenance {
            background: #fff8e6;
            border-color: #ffe69c;
            color: #664d03;
        }

        .announcement-maintenance .announcement-icon {
            background: #ffe69c;
        }

        .announcement-maintenance .announcement-button {
            background: #ffc107;
            color: #212529;
        }

        /* Atualização */
        .announcement-update {
            background: #eefbf3;
            border-color: #badbcc;
            color: #0f5132;
        }

        .announcement-update .announcement-icon {
            background: #badbcc;
        }

        .announcement-update .announcement-button {
            background: #198754;
            color: #fff;
        }

        /* Promoção */
        .announcement-promotion {
            background: #eef5ff;
            border-color: #a8ccff;
            color: #021127;
        }

        .announcement-promotion .announcement-icon {
            background: #e9d5ff;
        }

        .announcement-promotion .announcement-button {
            background: #005af9;
            color: #fff;
        }

        /* Aviso */
        .announcement-warning {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #881337;
        }

        .announcement-warning .announcement-icon {
            background: #fecdd3;
        }

        .announcement-warning .announcement-button {
            background: #dc3545;
            color: #fff;
        }
        .announcement-wrapper {
        position: relative;
        margin-bottom: 24px;
        }

        .announcement-badge {
        position: relative;
        padding-right: 48px;
        transition: opacity .25s ease, transform .25s ease;
        }

        .announcement-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, .06);
        color: inherit;
        cursor: pointer;
        transition: .2s ease;
        }

        .announcement-toggle:hover {
        background: rgba(0, 0, 0, .12);
        }

        .announcement-toggle i {
        transition: transform .25s ease;
        }

        .announcement-open {
        display: none;
        align-items: center;
        gap: 7px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 6px 11px;
        background: #fff;
        color: #495057;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        transition: .2s ease;
        }

        .announcement-open:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        }

        .announcement-wrapper.is-hidden .announcement-badge {
        display: none;
        }

        .announcement-wrapper.is-hidden .announcement-open {
        display: inline-flex;
        }

        @media (max-width: 576px) {
        .announcement-toggle {
            top: 12px;
            right: 10px;
            transform: none;
        }            
        .announcement-badge {
                align-items: flex-start;
                padding: 12px;
            }

            .announcement-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
                font-size: 15px;
            }

            .announcement-title {
                font-size: 13px;
            }

            .announcement-text {
                font-size: 12px;
            }

            .announcement-action {
                display: none;
            }
        }
    </style> 

    @if(!empty($announcement))
        @php
            $announcementConfig = [
                'general' => [
                    'icon' => 'bi-megaphone-fill',
                    'class' => 'announcement-general',
                    'label' => 'Informação',
                ],
                'maintenance' => [
                    'icon' => 'bi-tools',
                    'class' => 'announcement-maintenance',
                    'label' => 'Manutenção',
                ],
                'update' => [
                    'icon' => 'bi-arrow-up-circle-fill',
                    'class' => 'announcement-update',
                    'label' => 'Atualização',
                ],
                'promotion' => [
                    'icon' => 'bi-stars',
                    'class' => 'announcement-promotion',
                    'label' => 'Novidade',
                ],
                'warning' => [
                    'icon' => 'bi-exclamation-triangle-fill',
                    'class' => 'announcement-warning',
                    'label' => 'Aviso',
                ],
            ];

            $announcementType = $announcementConfig[$announcement->type] ?? $announcementConfig['general'];
        @endphp

        <div class="announcement-wrapper col-12" id="announcementWrapper">
            <div class="announcement-badge {{ $announcementType['class'] }}">
                <div class="announcement-icon">
                    <i class="bi {{ $announcementType['icon'] }}"></i>
                </div>

                <div class="announcement-content">
                    <div class="announcement-label">
                        {{ $announcementType['label'] }}
                    </div>

                    @if(!empty($announcement->title))
                        <div class="announcement-title">
                            {{ $announcement->title }}
                        </div>
                    @endif

                    @if(!empty($announcement->text))
                        <div class="announcement-text">
                            {!! $announcement->text !!}
                        </div>
                    @endif
                </div>

                @if(!empty($announcement->link))
                    <div class="announcement-action">
                        <a
                            href="{{ $announcement->link }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-sm announcement-button"
                        >
                            Saiba mais
                            <i class="bi bi-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                @endif

                <button
                    type="button"
                    class="announcement-toggle"
                    id="announcementToggle"
                    aria-label="Ocultar anúncio"
                    title="Ocultar anúncio"
                >
                    <i class="bi bi-chevron-up"></i>
                </button>
            </div>

            <button
                type="button"
                class="announcement-open"
                id="announcementOpen"
                aria-label="Exibir anúncio"
                title="Exibir anúncio"
            >
                <i class="bi {{ $announcementType['icon'] }}"></i>
                <span>{{ $announcementType['label'] }}</span>
            </button>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.getElementById('announcementWrapper');
            const toggle = document.getElementById('announcementToggle');
            const open = document.getElementById('announcementOpen');

            if (!wrapper || !toggle || !open) {
                return;
            }

            toggle.addEventListener('click', function () {
                wrapper.classList.add('is-hidden');
            });

            open.addEventListener('click', function () {
                wrapper.classList.remove('is-hidden');
            });
        });
    </script>


   {{-- ============================================================
        HOME
    ============================================================ --}}


    {{-- ============================================================
        FOOTER
    ============================================================ --}}
    <footer class="footer d-none d-lg-flex">
        <div class="container-fluid">
            <div class="row">

                <div class="col-md-6">
                    <div>
                        <a
                            href="https://www.whi.dev.br/"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="color:#94a0ad;"
                        >
                            <script>
                                document.write(new Date().getFullYear())
                            </script>
                            © WHI - Web de Alta Inspiração
                        </a>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="d-none d-md-flex gap-4 align-items-center justify-content-md-end footer-links">

                        <a
                            href="https://www.whi.dev.br/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted"
                        >
                            Sobre a WHI
                        </a>

                        <a
                            href="https://wa.me/5571992768360"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted"
                        >
                            Fale conosco
                        </a>

                    </div>
                </div>

            </div>
        </div>
    </footer>

    @include('admin.loadPage.loading')

@endsection
