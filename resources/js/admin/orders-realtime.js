import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const controls = document.getElementById('orders-realtime-controls');

if (controls) {
    const connectionIndicator = document.getElementById('orders-connection');
    const connectionLabel = document.getElementById('orders-connection-label');
    const soundButton = document.getElementById('orders-sound-toggle');
    const feedback = document.getElementById('orders-feedback');
    const snapshotUrl = controls.dataset.snapshotUrl;
    const tenantId = controls.dataset.tenantId;
    const seenEventIds = new Set();
    const knownOrderIds = new Set(
        Array.from(document.querySelectorAll('[data-order-card]'), (card) => card.dataset.orderCard),
    );
    const newestEventVersions = new Map();
    const pendingOrderIds = new Set();
    let echo = null;
    let socketConnected = false;
    let snapshotAvailable = false;
    let syncing = false;
    let syncAgain = false;
    let realtimeRevision = 0;
    let pollingTimer = null;
    let audioContext = null;
    let soundEnabled = false;
    let feedbackTimer = null;
    let currentView = 'kanban';

    const connectionLabels = {
        connected: 'Conectado',
        reconnecting: 'Reconectando',
        disconnected: 'Desconectado',
        syncing: 'Sincronizando',
    };

    function setConnectionState(state) {
        connectionIndicator.dataset.state = state;
        connectionLabel.textContent = connectionLabels[state];
    }

    function showFeedback(message, type = 'success') {
        window.clearTimeout(feedbackTimer);
        feedback.textContent = message;
        feedback.className = `alert alert-${type} alert-dismissible fade show`;

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('aria-label', 'Fechar');
        closeButton.addEventListener('click', () => {
            feedback.className = 'd-none';
        });
        feedback.append(closeButton);

        feedbackTimer = window.setTimeout(() => {
            feedback.className = 'd-none';
        }, type === 'danger' ? 10000 : 4500);
    }

    function updatePollingInterval() {
        window.clearInterval(pollingTimer);
        pollingTimer = window.setInterval(requestSync, socketConnected && snapshotAvailable ? 300000 : 60000);
    }

    function updateColumnCounts() {
        document.querySelectorAll('.orders-column').forEach((column) => {
            const count = column.querySelectorAll('[data-order-card]').length;
            const countElement = column.querySelector('[data-stage-count]');
            let emptyMessage = column.querySelector('.orders-empty');

            countElement.textContent = String(count);

            if (count === 0 && !emptyMessage) {
                emptyMessage = document.createElement('p');
                emptyMessage.className = 'orders-empty text-muted text-center font-13 py-3 mb-0';
                emptyMessage.textContent = 'Nenhum pedido nesta etapa.';
                column.append(emptyMessage);
            } else if (count > 0 && emptyMessage) {
                emptyMessage.remove();
            }
        });
    }

    function currentPageSnapshotUrl() {
        const url = new URL(snapshotUrl, window.location.origin);
        const page = new URLSearchParams(window.location.search).get('page');

        if (page) {
            url.searchParams.set('page', page);
        }

        return url;
    }

    function hasOpenModal() {
        return Boolean(document.querySelector('#orders-content .modal.show'));
    }

    async function requestSync() {
        if (syncing || pendingOrderIds.size > 0 || hasOpenModal()) {
            syncAgain = true;
            return;
        }

        syncing = true;
        setConnectionState('syncing');

        try {
            do {
                syncAgain = false;
                const requestRevision = realtimeRevision;
                const response = await fetch(currentPageSnapshotUrl(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Falha ao sincronizar pedidos (HTTP ${response.status}).`);
                }

                const payload = await response.json();
                snapshotAvailable = true;
                updatePollingInterval();

                if (realtimeRevision !== requestRevision) {
                    syncAgain = true;
                    continue;
                }

                if (pendingOrderIds.size > 0) {
                    syncAgain = true;
                    break;
                }

                if (hasOpenModal()) {
                    syncAgain = true;
                    break;
                }

                const replacement = document.createElement('template');
                replacement.innerHTML = payload.html;
                const nextContent = replacement.content.querySelector('#orders-content');
                const currentContent = document.getElementById('orders-content');

                if (!nextContent || !currentContent) {
                    throw new Error('A resposta de sincronização dos pedidos está incompleta.');
                }

                currentContent.replaceWith(nextContent);
                setView(currentView);
                document.querySelectorAll('[data-order-card]').forEach((card) => {
                    knownOrderIds.add(card.dataset.orderCard);
                });
            } while (syncAgain && pendingOrderIds.size === 0 && !hasOpenModal());
        } catch (error) {
            console.error('Não foi possível sincronizar os pedidos.', error);
            snapshotAvailable = false;
            updatePollingInterval();
        } finally {
            syncing = false;
            setConnectionState(socketConnected && snapshotAvailable ? 'connected' : 'disconnected');

            if (syncAgain && pendingOrderIds.size === 0 && !hasOpenModal()) {
                window.setTimeout(requestSync, 0);
            }
        }
    }

    function setView(view) {
        currentView = view;
        const kanban = document.getElementById('orders-kanban');
        const list = document.getElementById('orders-list');
        const kanbanButton = document.getElementById('show-kanban');
        const listButton = document.getElementById('show-list');
        const showKanban = view === 'kanban';

        kanban?.classList.toggle('d-none', !showKanban);
        list?.classList.toggle('d-none', showKanban);
        kanbanButton.classList.toggle('btn-primary', showKanban);
        kanbanButton.classList.toggle('btn-light', !showKanban);
        listButton.classList.toggle('btn-primary', !showKanban);
        listButton.classList.toggle('btn-light', showKanban);
        kanbanButton.setAttribute('aria-pressed', String(showKanban));
        listButton.setAttribute('aria-pressed', String(!showKanban));
    }

    function setSoundButton() {
        soundButton.setAttribute('aria-pressed', String(soundEnabled));
        soundButton.querySelector('span').textContent = soundEnabled ? 'Desativar sons' : 'Ativar sons';
        soundButton.querySelector('i').className = soundEnabled
            ? 'mdi mdi-bell-ring-outline me-1'
            : 'mdi mdi-bell-off-outline me-1';
    }

    function playTone(frequency, startAt, duration) {
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        const start = audioContext.currentTime + startAt;

        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.15, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start(start);
        oscillator.stop(start + duration);
    }

    function playNewOrderAlert() {
        if (!soundEnabled || !audioContext || audioContext.state !== 'running') {
            return;
        }

        playTone(880, 0, 0.18);
        playTone(1174, 0.22, 0.22);
    }

    function isNewOrderEvent(event) {
        return ['created', 'new_order', 'order_created'].includes(event.event_type);
    }

    function handleRealtimeEvent(event) {
        if (!event || !event.order_id) {
            return;
        }

        const eventId = event.event_id;
        if (eventId && seenEventIds.has(eventId)) {
            return;
        }
        if (eventId) {
            seenEventIds.add(eventId);
            if (seenEventIds.size > 500) {
                seenEventIds.delete(seenEventIds.values().next().value);
            }
        }

        const orderId = String(event.order_id);
        const eventTime = event.updated_at ? Date.parse(event.updated_at) : 0;
        const previousEventTime = newestEventVersions.get(orderId) || 0;
        const card = document.querySelector(`[data-order-card="${CSS.escape(orderId)}"]`);
        const cardTime = card?.dataset.updatedAt ? Date.parse(card.dataset.updatedAt) : 0;

        if ((eventTime && eventTime < previousEventTime) || (eventTime && eventTime < cardTime)) {
            return;
        }

        if (eventTime) {
            newestEventVersions.set(orderId, eventTime);
        }
        realtimeRevision += 1;

        if (isNewOrderEvent(event) && !knownOrderIds.has(orderId)) {
            knownOrderIds.add(orderId);
            playNewOrderAlert();
        }

        requestSync();
    }

    function configureRealtime() {
        const key = import.meta.env.VITE_REVERB_APP_KEY;
        const host = import.meta.env.VITE_REVERB_HOST;

        if (!key || !host || !tenantId) {
            setConnectionState('disconnected');
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
                setConnectionState(
                    socketConnected && snapshotAvailable
                        ? 'connected'
                        : current === 'connecting'
                            ? 'reconnecting'
                            : 'disconnected',
                );
                updatePollingInterval();

                if (socketConnected) {
                    requestSync();
                }
            });

            echo.private(`tenants.${tenantId}.orders`)
                .listen('.orders.changed', handleRealtimeEvent)
                .error((error) => {
                    console.error('Falha ao assinar o canal de pedidos.', error);
                    socketConnected = false;
                    setConnectionState('disconnected');
                    updatePollingInterval();
                });
        } catch (error) {
            console.error('Não foi possível iniciar o Realtime de pedidos.', error);
            setConnectionState('disconnected');
            updatePollingInterval();
        }
    }

    async function submitStatusChange(form) {
        const orderId = form.dataset.orderId;
        const targetStatus = form.dataset.targetStatus;
        const card = document.querySelector(`[data-order-card="${CSS.escape(orderId)}"]`);
        const targetColumn = document.querySelector(`.orders-column[data-status="${CSS.escape(targetStatus)}"]`);

        if (!card || !targetColumn || pendingOrderIds.has(orderId)) {
            return;
        }

        const originalParent = card.parentElement;
        const originalNextSibling = card.nextSibling;
        const originalStatus = card.dataset.status;
        const row = document.querySelector(`[data-order-row="${CSS.escape(orderId)}"]`);
        const statusBadge = row?.querySelector('[data-order-status]');
        const originalRowStatus = row?.dataset.status;
        const originalBadgeContent = statusBadge?.innerHTML;
        const originalBadgeClass = statusBadge?.className;
        const targetLabel = targetColumn.dataset.stageLabel;
        const targetColor = targetColumn.dataset.stageColor;
        const formData = new URLSearchParams(new FormData(form));

        pendingOrderIds.add(orderId);
        card.classList.add('order-card-action-pending');
        card.dataset.status = targetStatus;
        card.querySelectorAll('.order-status-form').forEach((actionForm) => {
            actionForm.classList.add('d-none');
        });
        const emptyMessage = targetColumn.querySelector('.orders-empty');
        emptyMessage?.remove();
        targetColumn.append(card);
        if (row && statusBadge) {
            row.dataset.status = targetStatus;
            statusBadge.className = `badge bg-soft-${targetColor} text-${targetColor}`;
            statusBadge.textContent = targetLabel;
        }
        updateColumnCounts();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: formData,
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.errors?.status?.[0] || payload.message || 'Não foi possível atualizar o pedido.');
            }

            card.classList.remove('order-card-action-pending');
            showFeedback(payload.message || 'Pedido atualizado.');
        } catch (error) {
            card.dataset.status = originalStatus;
            card.classList.remove('order-card-action-pending');
            card.querySelectorAll('.order-status-form').forEach((actionForm) => {
                actionForm.classList.remove('d-none');
            });
            originalParent.insertBefore(card, originalNextSibling);
            if (row && statusBadge) {
                row.dataset.status = originalRowStatus;
                statusBadge.className = originalBadgeClass;
                statusBadge.innerHTML = originalBadgeContent;
            }
            showFeedback(error.message, 'danger');
        } finally {
            pendingOrderIds.delete(orderId);
            updateColumnCounts();
            requestSync();
        }
    }

    document.getElementById('show-kanban').addEventListener('click', () => setView('kanban'));
    document.getElementById('show-list').addEventListener('click', () => setView('list'));
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.order-status-form');

        if (!form) {
            return;
        }

        event.preventDefault();
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            return;
        }

        submitStatusChange(form);
    });
    document.addEventListener('hidden.bs.modal', () => {
        if (syncAgain) {
            requestSync();
        }
    });

    soundButton.addEventListener('click', async () => {
        if (soundEnabled) {
            soundEnabled = false;
            setSoundButton();
            return;
        }

        const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextConstructor) {
            showFeedback('Este navegador não oferece suporte a notificações sonoras.', 'warning');
            return;
        }

        try {
            audioContext = audioContext || new AudioContextConstructor();
            await audioContext.resume();
            soundEnabled = true;
            setSoundButton();
            playTone(880, 0, 0.1);
        } catch (error) {
            console.error('Não foi possível ativar as notificações sonoras.', error);
            showFeedback('Não foi possível ativar o som neste navegador.', 'danger');
        }
    });

    setSoundButton();
    setConnectionState('syncing');
    configureRealtime();
    requestSync();
    updatePollingInterval();
}
