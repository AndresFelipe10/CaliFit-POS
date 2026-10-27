@extends('layouts.app')

@section('title', 'Asistente de Consultas IA — SaborCaliFit')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-stars me-2" style="color: #10B981;"></i>Asistente de Consultas en Lenguaje Natural
        </h3>
        <p class="text-muted small mb-0">
            Inteligencia operativa y consultas analíticas para <strong>SABORCALIFIT | BOCHALEMA</strong>.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-semibold">
            <i class="bi bi-shield-check me-1"></i> Solo Lectura
        </span>
        @if(!$enabled)
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 rounded-pill px-3 py-2 fw-semibold">
                <i class="bi bi-power me-1"></i> Desactivado en .env
            </span>
        @endif
    </div>
</div>

@if(!$enabled)
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">Módulo de IA Desactivado</h5>
                <p class="mb-2 text-muted">
                    El asistente se encuentra desactivado mediante la variable <code>AI_MODULE_ENABLED=false</code> en la configuración.
                    Tal como establece la arquitectura del proyecto, <strong>el núcleo de CaliFit Sistema POS opera al 100% de manera independiente</strong>.
                </p>
                <small class="text-secondary">
                    Para activarlo, define <code>AI_MODULE_ENABLED=true</code> y proporciona tu <code>GEMINI_API_KEY</code> en el archivo <code>.env</code>.
                </small>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Contenedor del Chat --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white d-flex flex-column" style="min-height: 520px; height: 620px;">
            <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                <span class="small fw-semibold text-muted text-uppercase" style="letter-spacing: 0.5px;">Panel de Conversación</span>
                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" id="clearChatBtn">
                    <i class="bi bi-trash me-1"></i> Limpiar Conversación
                </button>
            </div>

            <div class="card-body px-4 overflow-y-auto flex-grow-1" id="chatMessages" style="scroll-behavior: smooth;">
                {{-- Mensaje Inicial de Bienvenida --}}
                <div class="d-flex gap-3 mb-4">
                    <div class="text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 40px; height: 40px; background-color: #10B981;">
                        <i class="bi bi-stars fs-5"></i>
                    </div>
                    <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                        <p class="mb-1 text-dark fw-semibold">
                            ¡Hola! Soy tu asistente analítico de <strong>SaborCaliFit Bochalema</strong>.
                        </p>
                        <p class="mb-0 small text-muted">
                            Puedes preguntarme sobre existencias críticas de insumos en cocina (g/ml), suplementos deportivos por agotarse, balance de ventas por canal hoy, comandas abiertas o resumen de cuadre de caja.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Formulario de Entrada --}}
            <div class="card-footer bg-white border-0 p-3">
                <form id="queryForm" class="d-flex gap-2">
                    @csrf
                    <input type="text" id="userInput" class="form-control rounded-pill px-4 py-2 border-secondary border-opacity-25"
                           placeholder="Escribe tu consulta aquí (ej: ¿Cuáles insumos tienen bajo stock?)..."
                           autocomplete="off" maxlength="500">
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold d-flex align-items-center gap-2 shadow-sm" id="sendBtn" style="background-color: #10B981;">
                        <span>Preguntar</span>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Sugerencias Rápidas --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
            <h6 class="fw-bold mb-3 text-dark">
                <i class="bi bi-lightbulb text-warning me-1"></i> Consultas Frecuentes
            </h6>
            <div class="d-flex flex-column gap-2" id="suggestionChips">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3 suggestion-btn text-truncate" data-query="{{ $suggestion }}" title="{{ $suggestion }}">
                        <i class="bi bi-chat-left-text me-1 text-success"></i> {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Reglas y Privacidad --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <h6 class="fw-bold mb-2 text-dark">
                <i class="bi bi-shield-lock-fill text-success me-1"></i> Garantías de Seguridad
            </h6>
            <ul class="small text-muted ps-3 mb-0">
                <li class="mb-1"><strong>Cero Mutaciones:</strong> La IA no puede modificar stock, precios ni registros.</li>
                <li class="mb-1"><strong>Sin SQL Arbitrario:</strong> Las consultas se ejecutan únicamente vía whitelist estricta.</li>
                <li class="mb-1"><strong>Privacidad:</strong> No se transmiten contraseñas ni hashes a proveedores externos.</li>
                <li><strong>Alta Disponibilidad:</strong> Si la IA se desactiva, el POS continúa operando al 100%.</li>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chatMessages = document.getElementById('chatMessages');
    const queryForm = document.getElementById('queryForm');
    const userInput = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    const clearChatBtn = document.getElementById('clearChatBtn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');

    function appendUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex justify-content-end mb-3';
        msgDiv.innerHTML = `
            <div class="text-white p-3 rounded-4 shadow-sm" style="max-width: 80%; background-color: #10B981;">
                <p class="mb-0 small">${escapeHtml(text)}</p>
            </div>
        `;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function appendBotLoading() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'd-flex gap-3 mb-3 bot-loading';
        loadingDiv.innerHTML = `
            <div class="text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background-color: #10B981;">
                <i class="bi bi-stars"></i>
            </div>
            <div class="bg-light p-3 rounded-4 shadow-sm d-flex align-items-center gap-2" style="max-width: 80%;">
                <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                <span class="small text-muted">Consultando datos del sistema...</span>
            </div>
        `;
        chatMessages.appendChild(loadingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return loadingDiv;
    }

    const sourceMap = {
        'sales_by_channel': '📊 Fuente: Ventas por Canal en Tiempo Real',
        'daily_cash_summary': '💵 Fuente: Cuadre y Arqueo de Caja',
        'open_kitchen_orders': '🍳 Fuente: Comandas Activas de Cocina',
        'low_stock_ingredients': '📦 Fuente: Inventario de Cocina en Tiempo Real',
        'list_customers': '👥 Fuente: Directorio de Clientes Activos',
        'count_customers': '👥 Fuente: Directorio de Clientes Registrados',
    };

    const keyTranslations = {
        'period': 'Período',
        'sales_count': 'Ventas / Órdenes',
        'orders_count': 'Ventas / Órdenes',
        'total': 'Total Facturado',
        'total_revenue': 'Ingresos Totales',
        'average_ticket': 'Ticket Promedio',
        'total_active_customers': 'Clientes Activos Registrados',
        'total_collected': 'Total Recaudado',
        'cash': 'Efectivo',
        'digital_transfers': 'Transferencias Digitales',
        'cards': 'Datáfono / Tarjetas',
        'other': 'Otros Medios',
        'product_name': 'Producto / Insumo',
        'name': 'Nombre',
        'sku': 'Código SKU',
        'type': 'Tipo',
        'category': 'Categoría',
        'stock': 'Existencias',
        'min_stock': 'Stock Mínimo',
        'deficit': 'Déficit',
        'unit': 'Unidad Base',
        'sale_price': 'Precio de Venta',
        'price': 'Precio',
        'document': 'Documento / NIT',
        'document_number': 'Documento / NIT',
        'phone': 'Teléfono',
        'address': 'Dirección',
        'email': 'Correo Electrónico',
        'status': 'Estado',
        'order_number': 'Folio Comanda',
        'order_id': 'Pedido #',
        'table': 'Mesa / Salón',
        'waiter': 'Mesero / Responsable',
        'created_at': 'Hora / Fecha',
        'opened_at': 'Hora Apertura',
        'items_count': 'Cantidad de Platos',
        'purchases_count': 'Compras Realizadas',
        'credit_balance': 'Saldo en Cartera',
        'rank': 'Puesto',
        'units_sold': 'Unidades Vendidas',
        'kitchen_ingredients_count': 'Insumos de Cocina',
        'supplements_count': 'Suplementos Deportivos',
        'menu_dishes_count': 'Platos en Carta',
        'inventory_cost_valuation': 'Valoración al Costo',
        'supplements_pvp_valuation': 'Valoración PVP Suplementos'
    };

    const valueTranslations = {
        'today': 'Hoy',
        'yesterday': 'Ayer',
        'week': 'Esta Semana',
        'month': 'Este Mes',
        'active': 'Activo',
        'inactive': 'Inactivo',
        'open': 'Abierta',
        'in_kitchen': 'En Cocina',
        'dispatched': 'En Despacho',
        'delivered': 'Entregada',
        'billed': 'En Cobro / Pre-cuenta',
        'closed': 'Cerrada / Pagada',
        'cancelled': 'Cancelada',
        'table': 'Salón / Mesa',
        'delivery': 'Domicilio',
        'takeout': 'Para Llevar',
        'retail': 'Mostrador POS',
        'insumo': 'Insumo de Cocina',
        'suplemento': 'Suplemento Deportivo',
        'plato': 'Plato Preparado',
        'agotado': 'Agotado'
    };

    function translateAiKey(key) {
        if (!key) return '';
        const normalized = String(key).toLowerCase().trim();
        if (keyTranslations[normalized]) {
            return keyTranslations[normalized];
        }
        // Si ya viene capitalizado en español desde backend
        return key.replace(/_/g, ' ');
    }

    function translateAiValue(val) {
        if (val === null || val === undefined) return '—';
        const strVal = String(val).trim();
        const lower = strVal.toLowerCase();
        if (valueTranslations[lower]) {
            return valueTranslations[lower];
        }
        return strVal;
    }

    function appendBotResponse(data) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex gap-3 mb-4';

        let contentHtml = `<p class="mb-2 text-dark">${escapeHtml(data.summary || 'Respuesta generada.')}</p>`;

        if (Array.isArray(data.data) && data.data.length > 0) {
            contentHtml += formatDataTable(data.data);
        } else if (typeof data.data === 'object' && data.data !== null && Object.keys(data.data).length > 0) {
            contentHtml += formatDataObject(data.data);
        }

        const sourceLabel = data.intent && sourceMap[data.intent] ? sourceMap[data.intent] : '';
        const badgeHtml = sourceLabel
            ? `<div class="text-end mt-2"><span class="badge bg-light text-muted border small" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>${escapeHtml(sourceLabel)}</span></div>`
            : '';

        msgDiv.innerHTML = `
            <div class="text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 38px; height: 38px; background-color: #10B981;">
                <i class="bi bi-stars"></i>
            </div>
            <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                ${contentHtml}
                ${badgeHtml}
            </div>
        `;

        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function formatDataTable(rows) {
        if (!rows.length) return '';
        const legacyEnglishKeys = ['channel', 'sales_count', 'total', 'product_name'];
        const allHeaders = Object.keys(rows[0]);
        const hasSpanishHeaders = allHeaders.some(h => ['Canal de Venta', 'Ventas / Órdenes', 'Nombre', 'Insumo', 'Suplemento', 'Producto', 'Puesto', 'Folio Comanda'].includes(h));
        const headers = hasSpanishHeaders ? allHeaders.filter(h => !legacyEnglishKeys.includes(h)) : allHeaders;

        let table = `<div class="table-responsive my-2"><table class="table table-sm table-bordered bg-white small mb-0"><thead class="table-light"><tr>`;
        headers.forEach(h => {
            table += `<th class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.04em;">${escapeHtml(translateAiKey(h))}</th>`;
        });
        table += `</tr></thead><tbody>`;
        rows.forEach(r => {
            table += `<tr>`;
            headers.forEach(h => {
                table += `<td>${escapeHtml(translateAiValue(r[h]))}</td>`;
            });
            table += `</tr>`;
        });
        table += `</tbody></table></div>`;
        return table;
    }

    function formatDataObject(obj) {
        let list = `<ul class="list-unstyled small mb-0 mt-2 p-2 bg-white rounded border">`;
        const legacyEnglishKeys = ['period', 'sales_count', 'total_revenue', 'average_ticket', 'total_collected', 'cash', 'digital_transfers', 'cards', 'other', 'total_active_customers'];
        for (const [key, val] of Object.entries(obj)) {
            if (legacyEnglishKeys.includes(key)) {
                continue;
            }
            list += `<li class="d-flex justify-content-between py-1 border-bottom border-light">
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">${escapeHtml(translateAiKey(key))}:</span>
                <strong class="text-dark">${escapeHtml(translateAiValue(val))}</strong>
            </li>`;
        }
        list += `</ul>`;
        return list;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    queryForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const query = userInput.value.trim();
        if (!query) return;

        appendUserMessage(query);
        userInput.value = '';
        userInput.disabled = true;
        sendBtn.disabled = true;

        const loadingElem = appendBotLoading();

        try {
            const response = await fetch("{{ route('ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ query: query })
            });

            loadingElem.remove();

            if (response.status === 429) {
                appendBotResponse({
                    intent: 'rate_limited',
                    summary: 'Has alcanzado el límite de 30 consultas por minuto. Por favor espera un momento antes de preguntar de nuevo.',
                    data: []
                });
                return;
            }

            if (!response.ok) {
                appendBotResponse({
                    intent: 'error',
                    summary: 'El asistente no pudo procesar tu solicitud en este momento. Por favor intenta nuevamente más tarde.',
                    data: []
                });
                return;
            }

            const data = await response.json();
            appendBotResponse(data);
        } catch (error) {
            loadingElem.remove();
            appendBotResponse({
                intent: 'network_error',
                summary: 'Hubo un inconveniente de conexión con el servidor. Intenta nuevamente.',
                data: []
            });
        } finally {
            userInput.disabled = false;
            sendBtn.disabled = false;
            userInput.focus();
        }
    });

    window.sendQuestion = function (query) {
        userInput.value = query;
        queryForm.dispatchEvent(new Event('submit'));
    };

    suggestionBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            window.sendQuestion(this.dataset.query);
        });
    });

    clearChatBtn.addEventListener('click', async function () {
        try {
            await fetch("{{ route('ai.clear') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                }
            });
        } catch (e) {
            console.error('Error al limpiar sesión de IA:', e);
        }

        chatMessages.innerHTML = `
            <div class="d-flex gap-3 mb-4">
                <div class="text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 40px; height: 40px; background-color: #10B981;">
                    <i class="bi bi-stars fs-5"></i>
                </div>
                <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                    <p class="mb-1 text-dark fw-semibold">Conversación reiniciada.</p>
                    <p class="mb-0 small text-muted">¿En qué puedo ayudarte hoy?</p>
                </div>
            </div>
        `;
    });
});
</script>
@endpush
@endsection
