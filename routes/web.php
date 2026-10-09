<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EgresoRequisicionController;
use App\Http\Controllers\ModificacionPresupuestalController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SecretariaController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Presupuesto
    Route::middleware('permission:presupuesto-index')->group(function () {
        Route::get('presupuesto', [PresupuestoController::class, 'index'])->name('presupuesto');
    });
    Route::middleware('permission:presupuesto-edit')->group(function () {
        Route::get('presupuesto/{presupuesto}/edit', [PresupuestoController::class, 'edit'])->name('presupuesto.edit');
        Route::patch('presupuesto/{presupuesto}', [PresupuestoController::class, 'update'])->name('presupuesto.update');
    });
    Route::post('presupuesto/import', [PresupuestoController::class, 'import'])->name('presupuesto.import')->middleware('permission:presupuesto-import');
    Route::delete('presupuesto', [PresupuestoController::class, 'destroy'])->name('presupuesto.destroy')->middleware('permission:presupuesto-delete');

    // Secretarías
    Route::resource('secretarias', SecretariaController::class)
        ->middleware('permission:secretarias-index|secretarias-create|secretarias-edit|secretarias-delete');

    // Support Tickets
    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index')->middleware('permission:tickets-index');
    Route::get('support-tickets/create', [SupportTicketController::class, 'create'])->name('support-tickets.create')->middleware('permission:tickets-create');
    Route::post('support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store')->middleware('permission:tickets-create');
    Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support-tickets.show')->middleware('permission:tickets-index');
    Route::put('support-tickets/{supportTicket}', [SupportTicketController::class, 'update'])->name('support-tickets.update')->middleware('permission:tickets-update');
    Route::post('support-tickets/{supportTicket}/comment', [SupportTicketController::class, 'comment'])->name('support-tickets.comment')->middleware('permission:tickets-create');
    Route::get('notifications', [SupportTicketController::class, 'notifications'])->name('notifications.index')->middleware('permission:tickets-index');
    Route::put('notifications/{notification}/read', [SupportTicketController::class, 'markNotification'])->name('notifications.read')->middleware('permission:tickets-index');

    // Logs
    Route::get('logs', [LogViewerController::class, 'index'])->name('logs.index')->middleware('permission:logs-view');

    // Recursos Materiales dashboard
    Route::get('recursos-materiales', [EgresoRequisicionController::class, 'recursosMateriales'])->name('recursos-materiales.index')->middleware('permission:requisiciones-index');

    // Requisiciones
    Route::get('requisiciones', [EgresoRequisicionController::class, 'index'])->name('requisiciones.index')->middleware('permission:requisiciones-index');
    Route::get('requisiciones/gasto', [EgresoRequisicionController::class, 'gasto'])->name('requisiciones.gasto')->middleware('permission:requisiciones-index');
    Route::get('requisiciones/{requisicion}/pdf', [EgresoRequisicionController::class, 'pdf'])->name('requisiciones.pdf')->middleware('permission:requisiciones-index');
    Route::post('requisiciones/{requisicion}/enviar', [EgresoRequisicionController::class, 'enviarRevision'])->name('requisiciones.enviar')->middleware('permission:requisiciones-create');
    Route::post('requisiciones/{requisicion}/aprobar', [EgresoRequisicionController::class, 'aprobar'])->name('requisiciones.aprobar')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/rechazar', [EgresoRequisicionController::class, 'rechazar'])->name('requisiciones.rechazar')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/recursos-materiales', [EgresoRequisicionController::class, 'enviarRecursosMateriales'])->name('requisiciones.recursos')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/recibido', [EgresoRequisicionController::class, 'recibido'])->name('requisiciones.recibido')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones', [EgresoRequisicionController::class, 'store'])->name('requisiciones.store')->middleware('permission:requisiciones-create');
    Route::get('requisiciones/crear', [EgresoRequisicionController::class, 'create'])->name('requisiciones.create')->middleware('permission:requisiciones-create');
    Route::get('requisiciones/{requisicion}', [EgresoRequisicionController::class, 'show'])->name('requisiciones.show')->middleware('permission:requisiciones-index');
    Route::get('requisiciones/{requisicion}/edit', [EgresoRequisicionController::class, 'edit'])->name('requisiciones.edit')->middleware('permission:requisiciones-edit');
    Route::put('requisiciones/{requisicion}', [EgresoRequisicionController::class, 'update'])->name('requisiciones.update')->middleware('permission:requisiciones-edit');
    Route::delete('requisiciones/{requisicion}', [EgresoRequisicionController::class, 'destroy'])->name('requisiciones.destroy')->middleware('permission:requisiciones-delete');
    Route::post('requisiciones/{requisicion}/suficiencia', [EgresoRequisicionController::class, 'validarSuficiencia'])->name('requisiciones.suficiencia')->middleware('permission:requisiciones-suficiencia');

    // Órdenes de compra (Recursos Materiales)
    Route::post('requisiciones/{requisicion}/orden-compra', [OrdenCompraController::class, 'store'])->name('ordenes-compra.store')->middleware('permission:ordenes-compra-create');
    Route::get('ordenes-compra/{ordenCompra}/pdf', [OrdenCompraController::class, 'pdf'])->name('ordenes-compra.pdf')->middleware('permission:requisiciones-index');
    Route::post('ordenes-compra/{ordenCompra}/xml', [OrdenCompraController::class, 'cargarXml'])->name('ordenes-compra.xml')->middleware('permission:ordenes-compra-create');
    Route::get('ordenes-compra/{ordenCompra}/xml', [OrdenCompraController::class, 'descargarXml'])->name('ordenes-compra.xml.descargar')->middleware('permission:requisiciones-index');

    // Proveedores
    Route::get('proveedores', [ProveedorController::class, 'index'])->name('proveedores.index')->middleware('permission:proveedores-index');
    Route::post('proveedores', [ProveedorController::class, 'store'])->name('proveedores.store')->middleware('permission:proveedores-create');
    Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update')->middleware('permission:proveedores-edit');
    Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy')->middleware('permission:proveedores-delete');

    // Modificaciones presupuestales
    Route::get('modificaciones-presupuestales', [ModificacionPresupuestalController::class, 'index'])->name('modificaciones-presupuestales.index')->middleware('permission:modificaciones-index');
    Route::get('modificaciones-presupuestales/crear', [ModificacionPresupuestalController::class, 'create'])->name('modificaciones-presupuestales.create')->middleware('permission:modificaciones-create');
    Route::post('modificaciones-presupuestales', [ModificacionPresupuestalController::class, 'store'])->name('modificaciones-presupuestales.store')->middleware('permission:modificaciones-create');
    Route::get('modificaciones-presupuestales/{modificacion}', [ModificacionPresupuestalController::class, 'show'])->name('modificaciones-presupuestales.show')->middleware('permission:modificaciones-index');
    Route::get('modificaciones-presupuestales/{modificacion}/pdf', [ModificacionPresupuestalController::class, 'pdf'])->name('modificaciones-presupuestales.pdf')->middleware('permission:modificaciones-index');
    Route::post('modificaciones-presupuestales/{modificacion}/autorizar', [ModificacionPresupuestalController::class, 'autorizar'])->name('modificaciones-presupuestales.autorizar')->middleware('permission:modificaciones-autorizar');
    Route::post('modificaciones-presupuestales/{modificacion}/rechazar', [ModificacionPresupuestalController::class, 'rechazar'])->name('modificaciones-presupuestales.rechazar')->middleware('permission:modificaciones-autorizar');

    // Redirects for Admin module
    Route::redirect('users', '/settings/users');
    Route::redirect('roles', '/settings/roles');
    Route::redirect('permisos', '/settings/permissions');
});

require __DIR__.'/settings.php';
