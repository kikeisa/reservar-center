@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    .badge-standard  { background-color: #6c757d; }
    .badge-premium   { background-color: #0d6efd; }
    .badge-active    { background-color: #198754; }
    .badge-cancelled { background-color: #dc3545; }
</style>
@endpush

@section('content')
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Gestión de Reservas</strong>
                    <div class="d-flex gap-2">
                        <span class="badge bg-secondary" id="total-badge">Cargando...</span>
                    </div>
                </div>
                <div class="card-body">
                    <table id="tabla-reservas" class="table table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Servicio</th>
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
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF = '{{ csrf_token() }}';

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('es-CO', {
        dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Bogota'
    });
}

function fmtCurrency(v) {
    if (v === null || v === undefined) return '—';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v);
}

const tabla = $('#tabla-reservas').DataTable({
    ajax: {
        url: '{{ route("admin.reservations.list") }}',
        dataSrc: 'data'
    },
    columns: [
        { data: 'id', width: '4%' },
        { data: 'user_name' },
        {
            data: 'user_plan',
            render: d => d === 'premium'
                ? '<span class="badge badge-premium">Premium</span>'
                : '<span class="badge badge-standard">Estándar</span>'
        },
        { data: 'service_name' },
        { data: 'starts_at', render: d => fmtDate(d) },
        { data: 'ends_at',   render: d => fmtDate(d) },
        {
            data: 'status',
            render: d => d === 'active'
                ? '<span class="badge badge-active">Activa</span>'
                : '<span class="badge badge-cancelled">Cancelada</span>'
        },
        { data: 'refund_amount', render: d => fmtCurrency(d) },
        {
            data: null,
            orderable: false,
            searchable: false,
            render: (d, t, row) => row.status === 'active'
                ? `<button class="btn btn-sm btn-danger btn-cancelar" data-id="${row.id}" data-name="${row.user_name}" data-service="${row.service_name}">Cancelar</button>`
                : '<span class="text-muted small">—</span>'
        }
    ],
    order: [[4, 'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
    drawCallback: function () {
        const total = this.api().data().length;
        const activas = this.api().data().toArray().filter(r => r.status === 'active').length;
        $('#total-badge').text(`${activas} activas / ${total} total`);
    }
});

$(document).on('click', '.btn-cancelar', function () {
    const id      = $(this).data('id');
    const cliente = $(this).data('name');
    const servicio = $(this).data('service');

    Swal.fire({
        title: '¿Cancelar reserva?',
        html: `<p>Cliente: <strong>${cliente}</strong><br>Servicio: <strong>${servicio}</strong></p>
               <p class="text-muted small">Se calculará el reembolso según el plan del usuario.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No',
        confirmButtonColor: '#d33',
    }).then(async result => {
        if (!result.isConfirmed) return;

        const res = await fetch(`/admin/reservations/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF }
        });
        const data = await res.json();

        if (res.ok) {
            const msg = data.refund_amount > 0
                ? `Reembolso: ${fmtCurrency(data.refund_amount)}`
                : 'Sin reembolso aplicable.';
            Swal.fire('Cancelada', msg, 'success');
            tabla.ajax.reload();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
});
</script>
@endpush
