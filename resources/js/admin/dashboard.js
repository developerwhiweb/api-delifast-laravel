import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const dashboard = document.getElementById('dashboard-live');

if (dashboard) {
    const analyticsUrl = dashboard.dataset.analyticsUrl;
    const tenantId = dashboard.dataset.tenantId;
    const periodSelect = document.getElementById('dashboard-period');
    const errorMessage = document.getElementById('dashboard-error');
    const loadingIndicator = document.getElementById('dashboard-loading');
    const syncStatus = document.getElementById('dashboard-sync-status');
    const seenEventIds = new Set();
    let chart = null;
    let echo = null;
    let socketConnected = false;
    let pollingTimer = null;
    let refreshTimer = null;
    let requestVersion = 0;

    const currency = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('d-none');
    }

    function clearError() {
        errorMessage.textContent = '';
        errorMessage.classList.add('d-none');
    }

    function formatPercent(value) {
        const sign = value > 0 ? '+' : '';
        return `${sign}${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(value)}%`;
    }

    function updateMetrics(metrics) {
        document.querySelectorAll('[data-dashboard-metric]').forEach((element) => {
            const key = element.dataset.dashboardMetric;
            element.textContent = key === 'revenue' || key === 'average_ticket'
                ? currency.format(metrics[key] || 0)
                : new Intl.NumberFormat('pt-BR').format(metrics[key] || 0);
        });

        const comparison = document.getElementById('dashboard-revenue-comparison');
        if (metrics.revenue_change === null) {
            comparison.textContent = 'Sem base de comparação com o dia anterior';
            comparison.className = 'text-muted small';
        } else {
            comparison.textContent = `${formatPercent(metrics.revenue_change)} em relação ao dia anterior`;
            comparison.className = `small ${metrics.revenue_change >= 0 ? 'text-success' : 'text-danger'}`;
        }
    }

    function updateChart(data) {
        if (!chart) {
            return;
        }

        const hasSales = data.values.some((value) => value > 0);
        document.getElementById('dashboard-sales-chart').classList.toggle('d-none', !hasSales);
        document.getElementById('dashboard-sales-empty').classList.toggle('d-none', hasSales);
        chart.updateOptions({
            xaxis: { categories: data.labels },
        }, false, true);
        chart.updateSeries([{ name: 'Faturamento', data: hasSales ? data.values : [] }], true);
    }

    function renderDashboard(data) {
        updateMetrics(data.metrics);
        updateChart(data.chart);
        document.getElementById('dashboard-products').innerHTML = data.productsHtml;
        document.getElementById('dashboard-recent-orders').innerHTML = data.ordersHtml;
        clearError();
    }

    async function refreshDashboard(showLoading) {
        const version = ++requestVersion;
        const url = new URL(analyticsUrl, window.location.origin);
        url.searchParams.set('period', periodSelect.value);

        if (showLoading) {
            loadingIndicator.classList.remove('d-none');
            dashboard.setAttribute('aria-busy', 'true');
        }

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Não foi possível atualizar o painel (HTTP ${response.status}).`);
            }

            const data = await response.json();
            if (version === requestVersion) {
                renderDashboard(data);
            }
        } catch (error) {
            console.error('Falha ao atualizar o dashboard.', error);
            if (version === requestVersion) {
                showError('Não foi possível atualizar os dados agora. Tente novamente em instantes.');
            }
        } finally {
            if (version === requestVersion) {
                loadingIndicator.classList.add('d-none');
                dashboard.setAttribute('aria-busy', 'false');
            }
        }
    }

    function updatePollingInterval() {
        window.clearInterval(pollingTimer);
        pollingTimer = window.setInterval(
            () => refreshDashboard(false),
            socketConnected ? 300000 : 60000,
        );
    }

    function configureRealtime() {
        const key = import.meta.env.VITE_REVERB_APP_KEY;
        const host = import.meta.env.VITE_REVERB_HOST;

        if (!key || !host || !tenantId) {
            syncStatus.textContent = 'Atualização automática por consulta periódica';
            updatePollingInterval();
            return;
        }

        try {
            const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
            const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 8080));

            echo = new Echo({
                broadcaster: 'reverb',
                key,
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                },
            });

            echo.connector.pusher.connection.bind('state_change', ({ current }) => {
                socketConnected = current === 'connected';
                syncStatus.textContent = socketConnected
                    ? 'Atualização em tempo real ativa'
                    : 'Reconectando ao Realtime; consulta periódica ativa';
                updatePollingInterval();
            });

            echo.private(`tenants.${tenantId}.orders`)
                .listen('.orders.changed', (event) => {
                    if (event.event_id && seenEventIds.has(event.event_id)) {
                        return;
                    }
                    if (event.event_id) {
                        seenEventIds.add(event.event_id);
                        if (seenEventIds.size > 500) {
                            seenEventIds.delete(seenEventIds.values().next().value);
                        }
                    }

                    window.clearTimeout(refreshTimer);
                    refreshTimer = window.setTimeout(() => refreshDashboard(false), 250);
                })
                .error((error) => {
                    console.error('Falha ao assinar o canal de pedidos no dashboard.', error);
                    socketConnected = false;
                    syncStatus.textContent = 'Realtime indisponível; consulta periódica ativa';
                    updatePollingInterval();
                });
        } catch (error) {
            console.error('Não foi possível iniciar o Realtime no dashboard.', error);
            syncStatus.textContent = 'Realtime indisponível; consulta periódica ativa';
            updatePollingInterval();
        }
    }

    const chartElement = document.getElementById('dashboard-sales-chart');
    const initialChartValues = JSON.parse(dashboard.dataset.chartValues);
    if (typeof window.ApexCharts === 'undefined') {
        console.error('ApexCharts não foi carregado para o dashboard.');
        showError('O gráfico não pôde ser carregado. Recarregue a página para tentar novamente.');
    } else {
        chart = new window.ApexCharts(chartElement, {
            chart: {
                type: 'area',
                height: 310,
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'inherit',
            },
            series: [{
                name: 'Faturamento',
                data: initialChartValues.some((value) => value > 0) ? initialChartValues : [],
            }],
            colors: ['#536de6'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: {
                    opacityFrom: 0.28,
                    opacityTo: 0.03,
                    stops: [0, 100],
                },
            },
            grid: { borderColor: '#eef0f2', strokeDashArray: 4 },
            xaxis: {
                categories: JSON.parse(dashboard.dataset.chartLabels),
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    formatter: (value) => currency.format(value || 0),
                },
            },
            tooltip: {
                y: { formatter: (value) => currency.format(value || 0) },
            },
            noData: { text: 'Sem vendas neste período' },
            responsive: [{
                breakpoint: 576,
                options: {
                    chart: { height: 260 },
                    yaxis: { labels: { formatter: (value) => currency.format(value || 0) } },
                },
            }],
        });
        const hasInitialSales = initialChartValues.some((value) => value > 0);
        chartElement.classList.toggle('d-none', !hasInitialSales);
        document.getElementById('dashboard-sales-empty').classList.toggle('d-none', hasInitialSales);
        chart.render();
    }

    periodSelect.addEventListener('change', () => refreshDashboard(true));
    configureRealtime();
    updatePollingInterval();
}
