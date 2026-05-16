@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
@endpush

@section('content')
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Gestión de Usuarios</strong>
                    <button id="btn-crear-admin" class="btn btn-primary btn-sm">
                        + Crear Administrador
                    </button>
                </div>
                <div class="card-body">
                    <table id="tabla-usuarios" class="table table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Registrado</th>
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

const tabla = $('#tabla-usuarios').DataTable({
    ajax: { url: '{{ route("admin.users.list") }}', dataSrc: 'data' },
    columns: [
        { data: 'id', width: '5%' },
        { data: 'name' },
        { data: 'email' },
        {
            data: 'role',
            render: d => d === 'super_admin'
                ? '<span class="badge bg-danger">Admin</span>'
                : '<span class="badge bg-secondary">Cliente</span>'
        },
        {
            data: 'created_at',
            render: d => new Date(d).toLocaleDateString('es-CO')
        },
        {
            data: null,
            orderable: false,
            searchable: false,
            render: (d, t, row) =>
                `<button class="btn btn-sm btn-warning btn-editar me-1"
                    data-id="${row.id}" data-name="${row.name}"
                    data-email="${row.email}" data-role="${row.role}">Editar</button>
                 <button class="btn btn-sm btn-danger btn-eliminar" data-id="${row.id}">Eliminar</button>`
        }
    ],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
});

// --- Crear administrador ---
$('#btn-crear-admin').on('click', function () {
    Swal.fire({
        title: 'Crear Administrador',
        html: `
            <input id="swal-name"  class="swal2-input" placeholder="Nombre completo" type="text">
            <input id="swal-email" class="swal2-input" placeholder="Correo electrónico" type="email">
            <input id="swal-pass"  class="swal2-input" placeholder="Contraseña (mín. 8 caracteres)" type="password">
            <input id="swal-pass2" class="swal2-input" placeholder="Confirmar contraseña" type="password">
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        preConfirm: async () => {
            const body = {
                name: document.getElementById('swal-name').value.trim(),
                email: document.getElementById('swal-email').value.trim(),
                password: document.getElementById('swal-pass').value,
                password_confirmation: document.getElementById('swal-pass2').value,
            };
            if (!body.name || !body.email || !body.password) {
                Swal.showValidationMessage('Todos los campos son requeridos');
                return false;
            }
            const res = await fetch('{{ route("admin.users.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (!res.ok) { Swal.showValidationMessage(data.message); return false; }
            return data;
        }
    }).then(r => {
        if (r.isConfirmed) { Swal.fire('¡Listo!', r.value.message, 'success'); tabla.ajax.reload(); }
    });
});

// --- Editar usuario ---
$(document).on('click', '.btn-editar', function () {
    const { id, name, email, role } = $(this).data();
    Swal.fire({
        title: 'Editar Usuario',
        html: `
            <input id="e-name"  class="swal2-input" value="${name}" placeholder="Nombre" type="text">
            <input id="e-email" class="swal2-input" value="${email}" placeholder="Email" type="email">
            <select id="e-role" class="swal2-input">
                <option value="super_admin" ${role === 'super_admin' ? 'selected' : ''}>Administrador</option>
                <option value="cliente"     ${role === 'cliente'     ? 'selected' : ''}>Cliente</option>
            </select>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: async () => {
            const body = {
                name:  document.getElementById('e-name').value.trim(),
                email: document.getElementById('e-email').value.trim(),
                role:  document.getElementById('e-role').value,
            };
            if (!body.name || !body.email) {
                Swal.showValidationMessage('Nombre y email son requeridos');
                return false;
            }
            const res = await fetch(`/admin/users/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (!res.ok) { Swal.showValidationMessage(data.message); return false; }
            return data;
        }
    }).then(r => {
        if (r.isConfirmed) { Swal.fire('¡Listo!', r.value.message, 'success'); tabla.ajax.reload(); }
    });
});

// --- Eliminar usuario ---
$(document).on('click', '.btn-eliminar', function () {
    const id = $(this).data('id');
    Swal.fire({
        title: '¿Eliminar usuario?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
    }).then(async r => {
        if (!r.isConfirmed) return;
        const res = await fetch(`/admin/users/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF }
        });
        const data = await res.json();
        res.ok
            ? (Swal.fire('¡Eliminado!', data.message, 'success'), tabla.ajax.reload())
            : Swal.fire('Error', data.message, 'error');
    });
});
</script>
@endpush
