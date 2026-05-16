@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    .badge-active    { background-color: #198754; }
    .badge-cancelled { background-color: #dc3545; }
    .badge-premium   { background-color: #0d6efd; }
    .badge-standard  { background-color: #6c757d; }
    .slot-counter { font-size: .85rem; }
    .slot-counter .slots-num { font-weight: 700; font-size: 1.1rem; }
    .slot-empty { color: #198754; }
    .slot-warn  { color: #fd7e14; }
    .slot-full  { color: #dc3545; }
</style>
@endpush

@section('content')
<div class="container-fluid px-4">

    {{-- Encabezado --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0">Mis Reservas</h5>
                <small class="text-muted">
                    Bienvenido, <strong>{{ Auth::user()->name }}</strong> &mdash;
                    Plan:
                    @if(Auth::user()->plan === 'premium')
                        <span class="badge badge-premium">Premium</span>
                    @else
                        <span class="badge badge-standard">Estándar</span>
                    @endif
                </small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="slot-counter" id="slot-info">
                    Reservas activas: <span class="slots-num" id="slots-used">–</span> / 3
                </span>
                <button id="btn-nueva-reserva" class="btn btn-primary btn-sm">
                    + Nueva reserva
                </button>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm">
        <div class="card-body p-2">
            <table id="tabla-reservas" class="table table-striped table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Servicio</th>
                        <th>Duración</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th>Reembolso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF        = '{{ csrf_token() }}';
const USER_PLAN   = '{{ Auth::user()->plan }}';
const ROUTES      = {
    services : '{{ route("client.services") }}',
    list     : '{{ route("client.bookings.list") }}',
    store    : '{{ route("client.bookings.store") }}',
    refund   : id => `/client/bookings/${id}/refund`,
    cancel   : id => `/client/bookings/${id}`,
};

// ─── Festivos Colombia 2026 ───────────────────────────────────────────────────
const HOLIDAYS = new Set([
    '2026-01-01','2026-01-12','2026-03-23','2026-04-02','2026-04-03',
    '2026-05-01','2026-05-18','2026-06-08','2026-06-15','2026-06-29',
    '2026-07-20','2026-08-07','2026-08-17','2026-10-12','2026-11-02',
    '2026-11-16','2026-12-08','2026-12-25',
]);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('es-CO', {
        dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Bogota'
    });
}

function fmtCOP(v) {
    if (v === null || v === undefined || v == 0) return '—';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', maximumFractionDigits: 0
    }).format(v);
}

function pad(n) { return String(n).padStart(2, '0'); }

/** Hora actual en Bogotá (UTC-5, sin DST) */
function nowBogota() {
    const d = new Date();
    const utc = d.getTime() + d.getTimezoneOffset() * 60000;
    return new Date(utc - 5 * 3600000);
}

/** Valida las reglas de negocio en el cliente antes de enviar */
function validateSlot(datetimeLocalValue) {
    if (!datetimeLocalValue) return 'Selecciona fecha y hora.';

    // datetime-local da "YYYY-MM-DDTHH:mm" — lo tratamos como hora Bogotá
    const [datePart, timePart] = datetimeLocalValue.split('T');
    const [year, month, day]   = datePart.split('-').map(Number);
    const [hour, minute]       = timePart.split(':').map(Number);

    const selected  = new Date(year, month - 1, day, hour, minute);
    const dowJS     = selected.getDay(); // 0 = domingo

    if (dowJS === 0)
        return 'No se puede reservar los domingos.';

    if (HOLIDAYS.has(datePart))
        return 'Esa fecha es festivo en Colombia.';

    const decimalHour = hour + minute / 60;
    if (decimalHour < 7 || decimalHour >= 19)
        return 'Solo se puede reservar entre 7:00 y 19:00 hora Bogotá.';

    const bogotaNow  = nowBogota();
    const diffMs     = selected - bogotaNow;
    const diffHours  = diffMs / 3600000;

    if (diffHours < 2)
        return 'La reserva debe hacerse con al menos 2 horas de anticipación.';

    return null; // OK
}

/** Mínimo datetime-local: 2 horas desde ahora en hora Bogotá */
function minDatetimeLocal() {
    const min = new Date(nowBogota().getTime() + 2 * 3600000);
    return `${min.getFullYear()}-${pad(min.getMonth()+1)}-${pad(min.getDate())}T${pad(min.getHours())}:${pad(min.getMinutes())}`;
}

// ─── DataTable ────────────────────────────────────────────────────────────────
const tabla = $('#tabla-reservas').DataTable({
    ajax: {
        url: ROUTES.list,
        dataSrc: json => {
            updateSlotCounter(json.active_count);
            return json.data;
        }
    },
    columns: [
        { data: 'id', width: '4%' },
        { data: 'service_name' },
        { data: 'duration', render: d => `${d} min` },
        { data: 'starts_at', render: d => fmtDate(d) },
        { data: 'ends_at',   render: d => fmtDate(d) },
        {
            data: 'status',
            render: d => d === 'active'
                ? '<span class="badge badge-active">Activa</span>'
                : '<span class="badge badge-cancelled">Cancelada</span>'
        },
        {
            data: 'refund_amount',
            render: (d, t, row) => {
                if (row.status === 'active') return '—';
                return d > 0 ? `<span class="text-success">${fmtCOP(d)}</span>` : '<span class="text-muted">Sin reembolso</span>';
            }
        },
        {
            data: null,
            orderable: false,
            searchable: false,
            render: (d, t, row) => {
                if (row.status !== 'active' || !row.is_future) return '<span class="text-muted small">—</span>';
                return `<button class="btn btn-sm btn-outline-danger btn-cancelar"
                            data-id="${row.id}"
                            data-service="${row.service_name}"
                            data-starts="${row.starts_at}">
                            Cancelar
                        </button>`;
            }
        },
    ],
    order: [[3, 'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
});

function updateSlotCounter(active) {
    const el  = $('#slots-used');
    const info = $('#slot-info');
    el.text(active);
    el.removeClass('slot-empty slot-warn slot-full');
    info.removeClass('slot-empty slot-warn slot-full');
    if (active >= 3)      { el.addClass('slot-full');  info.addClass('slot-full'); }
    else if (active >= 2) { el.addClass('slot-warn');  info.addClass('slot-warn'); }
    else                  { el.addClass('slot-empty'); info.addClass('slot-empty'); }
}

// ─── Nueva reserva ────────────────────────────────────────────────────────────
$('#btn-nueva-reserva').on('click', async function () {

    // Verificar cupo antes de abrir modal
    const listRes   = await fetch(ROUTES.list);
    const listData  = await listRes.json();
    if (listData.active_count >= 3) {
        Swal.fire('Sin cupo', 'Ya tienes 3 reservas activas. Cancela una para poder agendar.', 'warning');
        return;
    }

    // Cargar servicios
    const svcRes  = await fetch(ROUTES.services);
    const services = await svcRes.json();

    const options = services.map(s =>
        `<option value="${s.id}" data-dur="${s.duration_minutes}" data-price="${s.price}" data-nr="${s.non_refundable}">
            ${s.name} — ${s.duration_minutes} min — ${fmtCOP(s.price)}${s.non_refundable ? ' ⚠️ No reembolsable' : ''}
         </option>`
    ).join('');

    const minDt = minDatetimeLocal();

    const { value: formData, isConfirmed } = await Swal.fire({
        title: 'Nueva Reserva',
        width: 540,
        html: `
            <div class="text-start mb-2">
                <label class="form-label fw-semibold">Servicio</label>
                <select id="swal-service" class="form-select form-select-sm">
                    <option value="">— Selecciona un servicio —</option>
                    ${options}
                </select>
            </div>
            <div class="text-start mb-2">
                <label class="form-label fw-semibold">Fecha y hora <small class="text-muted">(hora Bogotá, Lun–Sáb 7:00–19:00)</small></label>
                <input id="swal-datetime" type="datetime-local" class="form-control form-control-sm" min="${minDt}">
            </div>
            <div id="swal-preview" class="alert alert-info py-2 d-none small"></div>
            <div class="text-muted small mt-2">
                Plan actual: <strong>${USER_PLAN === 'premium' ? 'Premium' : 'Estándar'}</strong>
                &nbsp;|&nbsp; Cupos disponibles: <strong>${3 - listData.active_count}</strong>
            </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Agendar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Preview dinámico de horario al cambiar fecha
            document.getElementById('swal-datetime').addEventListener('change', function () {
                const err     = validateSlot(this.value);
                const preview = document.getElementById('swal-preview');
                if (err) {
                    preview.className = 'alert alert-danger py-2 small';
                    preview.textContent = '⚠ ' + err;
                    preview.classList.remove('d-none');
                } else {
                    const [, time] = this.value.split('T');
                    preview.className = 'alert alert-success py-2 small';
                    preview.textContent = `✔ Horario válido — ${time} hora Bogotá`;
                    preview.classList.remove('d-none');
                }
            });
        },
        preConfirm: async () => {
            const serviceId = document.getElementById('swal-service').value;
            const startsAt  = document.getElementById('swal-datetime').value;

            if (!serviceId) {
                Swal.showValidationMessage('Selecciona un servicio.');
                return false;
            }

            const clientErr = validateSlot(startsAt);
            if (clientErr) {
                Swal.showValidationMessage(clientErr);
                return false;
            }

            const res  = await fetch(ROUTES.store, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ service_id: serviceId, starts_at: startsAt.replace('T', ' ') })
            });
            const data = await res.json();
            if (!res.ok) { Swal.showValidationMessage(data.message); return false; }
            return data;
        }
    });

    if (isConfirmed && formData) {
        Swal.fire({
            icon: 'success',
            title: '¡Reserva creada!',
            html: `<strong>${formData.reservation.service.name}</strong><br>
                   ${fmtDate(formData.reservation.starts_at)}`,
            timer: 3000,
            showConfirmButton: false,
        });
        tabla.ajax.reload();
    }
});

// ─── Cancelar reserva ─────────────────────────────────────────────────────────
$(document).on('click', '.btn-cancelar', async function () {
    const id      = $(this).data('id');
    const service = $(this).data('service');
    const starts  = fmtDate($(this).data('starts'));

    // 1. Obtener preview del reembolso
    Swal.fire({ title: 'Calculando reembolso…', didOpen: () => Swal.showLoading() });

    const refundRes  = await fetch(ROUTES.refund(id));
    const refundData = await refundRes.json();
    Swal.close();

    const refundMsg = refundData.refund_amount > 0
        ? `Recibirás <strong>${fmtCOP(refundData.refund_amount)}</strong> de reembolso.`
        : `<span class="text-danger">No aplica reembolso</span> según tu plan y el tiempo restante.`;

    // 2. Confirmar con el usuario
    const confirm = await Swal.fire({
        title: '¿Cancelar reserva?',
        html: `<p class="mb-1">Servicio: <strong>${service}</strong></p>
               <p class="mb-2">Inicio: ${starts}</p>
               <hr>
               ${refundMsg}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No',
        confirmButtonColor: '#d33',
    });

    if (!confirm.isConfirmed) return;

    // 3. Ejecutar cancelación
    const res  = await fetch(ROUTES.cancel(id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();

    if (res.ok) {
        Swal.fire({
            icon: 'success',
            title: 'Reserva cancelada',
            html: data.refund_amount > 0
                ? `Reembolso aplicado: <strong>${fmtCOP(data.refund_amount)}</strong>`
                : 'Sin reembolso aplicado.',
            timer: 3000,
            showConfirmButton: false,
        });
        tabla.ajax.reload();
    } else {
        Swal.fire('Error', data.message, 'error');
    }
});
</script>
@endpush
