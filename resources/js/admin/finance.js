import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const financeRoot = document.getElementById('finance-live');

if (financeRoot) {
    const filterPeriod = document.getElementById('finance-period');
    const customDates = document.querySelectorAll('.finance-custom-date');
    const startDate = document.getElementById('finance-start-date');
    const endDate = document.getElementById('finance-end-date');
    const syncStatus = document.getElementById('finance-sync-status');
    const errorElement = document.getElementById('finance-live-error');
    const currency = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
    const number = new Intl.NumberFormat('pt-BR');
    const seenEventIds = new Set();
    let salesChart = null;
    let deliveryChart = null;
    let methodsChart = null;
    let echo = null;
    let socketConnected = false;
    let pollingTimer = null;
    let requestVersion = 0;

    const updateCustomDates = () => {
        const isCustom = filterPeriod.value === 'custom';
        customDates.forEach((element) => element.classList.toggle('d-none', !isCustom));
        startDate.required = isCustom;
        endDate.required = isCustom;
    };

    const updateEmptyStates = (data) => {
        const hasSales = data.sales_chart.values.some((value) => Number(value) > 0);
        const hasDeliveryFees = data.delivery_chart.values.some((value) => Number(value) > 0);
        const hasMethods = data.payment_methods.length > 0;

        document.getElementById('finance-sales-chart').classList.toggle('d-none', !hasSales);
        document.getElementById('finance-sales-empty').classList.toggle('d-none', hasSales);
        document.getElementById('finance-delivery-chart').classList.toggle('d-none', !hasDeliveryFees);
        document.getElementById('finance-delivery-empty').classList.toggle('d-none', hasDeliveryFees);
        document.getElementById('finance-methods-chart').classList.toggle('d-none', !hasMethods);
        document.getElementById('finance-methods-empty').classList.toggle('d-none', hasMethods);
    };

    const initializeCharts = (data) => {
        if (typeof window.ApexCharts === 'undefined') {
            console.error('ApexCharts não foi carregado para o módulo financeiro.');
            ['finance-sales-chart', 'finance-delivery-chart', 'finance-methods-chart'].forEach((id) => {
                document.getElementById(id).classList.add('d-none');
            });
            document.getElementById('finance-sales-empty').textContent = 'Não foi possível carregar os gráficos.';
            document.getElementById('finance-sales-empty').classList.remove('d-none');
            return;
        }

        salesChart = new window.ApexCharts(document.getElementById('finance-sales-chart'), {
            chart: { type: 'area', height: 320, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
            series: [{ name: 'Faturamento', data: data.sales_chart.values }],
            colors: ['#536de6'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.28, opacityTo: 0.03, stops: [0, 100] } },
            grid: { borderColor: '#eef0f2', strokeDashArray: 4 },
            xaxis: { categories: data.sales_chart.labels, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: (value) => currency.format(value || 0) } },
            tooltip: { y: { formatter: (value) => currency.format(value || 0) } },
            noData: { text: 'Sem faturamento no período' },
            responsive: [{ breakpoint: 576, options: { chart: { height: 260 } } }],
        });

        deliveryChart = new window.ApexCharts(document.getElementById('finance-delivery-chart'), {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Taxas de entrega', data: data.delivery_chart.values }],
            colors: ['#39afd1'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#eef0f2', strokeDashArray: 4 },
            xaxis: { categories: data.delivery_chart.labels, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: (value) => currency.format(value || 0) } },
            tooltip: { y: { formatter: (value) => currency.format(value || 0) } },
            noData: { text: 'Sem taxas de entrega no período' },
            responsive: [{ breakpoint: 576, options: { chart: { height: 230 } } }],
        });

        methodsChart = new window.ApexCharts(document.getElementById('finance-methods-chart'), {
            chart: { type: 'donut', height: 300, fontFamily: 'inherit' },
            series: data.payment_methods.map((item) => Number(item.amount)),
            labels: data.payment_methods.map((item) => item.method),
            colors: ['#536de6', '#39afd1', '#f9bc0b', '#fa5c7c', '#6c757d', '#47ad77'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom' },
            tooltip: { y: { formatter: (value) => currency.format(value || 0) } },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Recebido',
                                formatter: (state) => currency.format(state.globals.seriesTotals.reduce((sum, value) => sum + value, 0)),
                            },
                        },
                    },
                },
            },
            noData: { text: 'Sem recebimentos no período' },
            responsive: [{ breakpoint: 576, options: { chart: { height: 260 } } }],
        });

        salesChart.render();
        deliveryChart.render();
        methodsChart.render();
        updateEmptyStates(data);
    };

    const updateCharts = (data) => {
        if (!salesChart || !deliveryChart || !methodsChart) {
            return;
        }

        salesChart.updateOptions({ xaxis: { categories: data.sales_chart.labels } }, false, true);
        salesChart.updateSeries([{ name: 'Faturamento', data: data.sales_chart.values }], true);
        deliveryChart.updateOptions({ xaxis: { categories: data.delivery_chart.labels } }, false, true);
        deliveryChart.updateSeries([{ name: 'Taxas de entrega', data: data.delivery_chart.values }], true);
        methodsChart.updateOptions({ labels: data.payment_methods.map((item) => item.method) }, false, true);
        methodsChart.updateSeries(data.payment_methods.map((item) => Number(item.amount)), true);
        updateEmptyStates(data);
    };

    const updateMetrics = (metrics, orderSummary) => {
        document.querySelectorAll('[data-finance-metric]').forEach((element) => {
            const key = element.dataset.financeMetric;
            element.textContent = element.dataset.money === 'true'
                ? `R$ ${Number(metrics[key] || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                : number.format(Number(metrics[key] || 0));
        });

        const summaryValues = {
            product_subtotal: orderSummary.product_subtotal,
            discounts: orderSummary.discounts,
            delivery_fees: orderSummary.delivery_fees,
            order_total: orderSummary.order_total,
            received_total: metrics.received_total,
            refunded_total: metrics.refunded_total,
        };
        document.querySelectorAll('[data-finance-summary]').forEach((element) => {
            element.textContent = currency.format(Number(summaryValues[element.dataset.financeSummary] || 0));
        });
    };

    const refresh = async () => {
        const version = ++requestVersion;
        const url = new URL(financeRoot.dataset.snapshotUrl, window.location.origin);
        const formData = new FormData(document.getElementById('finance-filters'));
        formData.forEach((value, key) => {
            if (value !== '') {
                url.searchParams.set(key, value);
            }
        });
        const currentPage = new URL(window.location.href).searchParams.get('page');
        if (currentPage) {
            url.searchParams.set('page', currentPage);
        }

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Não foi possível atualizar o financeiro (HTTP ${response.status}).`);
            }

            const data = await response.json();
            if (version !== requestVersion) {
                return;
            }

            updateMetrics(data.metrics, data.order_summary);
            updateCharts(data);

            const editingLedger = document.querySelector('#finance-orders-content details[open], #finance-orders-content form :focus');
            if (!editingLedger) {
                document.getElementById('finance-orders-content').innerHTML = data.orders_html;
            }

            errorElement.textContent = '';
            errorElement.classList.add('d-none');
            syncStatus.textContent = `Atualizado às ${new Date(data.synced_at).toLocaleTimeString('pt-BR')}`;
        } catch (error) {
            console.error('Falha ao atualizar o painel financeiro.', error);
            if (version === requestVersion) {
                errorElement.textContent = 'Não foi possível atualizar os dados agora. Tente novamente em instantes.';
                errorElement.classList.remove('d-none');
                syncStatus.textContent = 'Atualização automática indisponível';
            }
        }
    };

    const updatePollingInterval = () => {
        window.clearInterval(pollingTimer);
        pollingTimer = window.setInterval(refresh, socketConnected ? 300000 : 60000);
    };

    const configureRealtime = () => {
        const key = import.meta.env.VITE_REVERB_APP_KEY;
        const host = import.meta.env.VITE_REVERB_HOST;
        const tenantId = financeRoot.dataset.tenantId;

        if (!key || !host || !tenantId) {
            syncStatus.textContent = 'Atualização periódica ativa';
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
                    ? 'Atualizações em tempo real ativas'
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

                    refresh();
                })
                .error((error) => {
                    console.error('Falha ao assinar o canal financeiro de pedidos.', error);
                    socketConnected = false;
                    syncStatus.textContent = 'Realtime indisponível; consulta periódica ativa';
                    updatePollingInterval();
                });
        } catch (error) {
            console.error('Não foi possível iniciar o Realtime financeiro.', error);
            syncStatus.textContent = 'Realtime indisponível; consulta periódica ativa';
            updatePollingInterval();
        }
    };

    filterPeriod.addEventListener('change', updateCustomDates);
    updateCustomDates();
    initializeCharts(window.financeCharts);
    configureRealtime();
    updatePollingInterval();
}
