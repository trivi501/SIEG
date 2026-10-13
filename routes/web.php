<?php

use App\Catalogos\Catalogos;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClcController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EgresoRequisicionController;
use App\Http\Controllers\ModificacionPresupuestalController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::bind('catalogo', fn (string $slug) => Catalogos::buscar($slug) ?? abort(404));

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Presupuesto
    Route::middleware('permission:presupuesto-index')->group(function () {
        Route::get('presupuesto', [PresupuestoController::class, 'index'])->name('presupuesto');
    });
    Route::middleware('permission:presupuesto-create')->group(function () {
        Route::get('presupuesto/crear', [PresupuestoController::class, 'create'])->name('presupuesto.create');
        Route::post('presupuesto', [PresupuestoController::class, 'store'])->name('presupuesto.store');
    });
    Route::middleware('permission:presupuesto-edit')->group(function () {
        Route::get('presupuesto/{presupuesto}/edit', [PresupuestoController::class, 'edit'])->name('presupuesto.edit');
        Route::patch('presupuesto/{presupuesto}', [PresupuestoController::class, 'update'])->name('presupuesto.update');
    });
    Route::post('presupuesto/correr-saldos', [PresupuestoController::class, 'correrSaldos'])->name('presupuesto.correr-saldos')->middleware('permission:presupuesto-correr-saldos');
    Route::post('presupuesto/import', [PresupuestoController::class, 'import'])->name('presupuesto.import')->middleware('permission:presupuesto-import');
    Route::delete('presupuesto', [PresupuestoController::class, 'destroy'])->name('presupuesto.destroy')->middleware('permission:presupuesto-delete');

    // Secretarías y direcciones son un solo catálogo (cat_egreso_unidad_administrativa).
    Route::redirect('secretarias', '/catalogos/unidades-administrativas');

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
    Route::get('recursos-materiales', [EgresoRequisicionController::class, 'recursosMateriales'])->name('recursos-materiales.index')->middleware('permission:recursos-materiales-index');

    // Requisiciones
    Route::get('requisiciones', [EgresoRequisicionController::class, 'index'])->name('requisiciones.index')->middleware('permission:requisiciones-index');
    Route::get('requisiciones/gasto', [EgresoRequisicionController::class, 'gasto'])->name('requisiciones.gasto')->middleware('permission:gasto-index');
    Route::get('requisiciones/gasto/detalle', [EgresoRequisicionController::class, 'gastoDetalle'])->name('requisiciones.gasto.detalle')->middleware('permission:gasto-index');
    Route::get('requisiciones/gasto/exportar', [EgresoRequisicionController::class, 'exportarGasto'])->name('requisiciones.gasto.exportar')->middleware('permission:gasto-index');
    Route::get('requisiciones/{requisicion}/pdf', [EgresoRequisicionController::class, 'pdf'])->name('requisiciones.pdf')->middleware('permission:requisiciones-index');
    Route::post('requisiciones/{requisicion}/autorizar-extemporanea', [EgresoRequisicionController::class, 'autorizarExtemporanea'])->name('requisiciones.extemporanea')->middleware('permission:requisiciones-extemporaneas');
    // Permiso requisiciones-fecha o enlace de la misma secretaría (se revisa en el controlador).
    Route::post('requisiciones/{requisicion}/fecha', [EgresoRequisicionController::class, 'cambiarFecha'])->name('requisiciones.fecha')->middleware('permission:requisiciones-index');
    // Permiso requisiciones-cancelar o enlace de la misma secretaría (se revisa en el controlador).
    Route::post('requisiciones/{requisicion}/cancelar', [EgresoRequisicionController::class, 'cancelar'])->name('requisiciones.cancelar')->middleware('permission:requisiciones-index');
    Route::post('requisiciones/{requisicion}/enviar', [EgresoRequisicionController::class, 'enviarRevision'])->name('requisiciones.enviar')->middleware('permission:requisiciones-create');
    Route::post('requisiciones/{requisicion}/aprobar', [EgresoRequisicionController::class, 'aprobar'])->name('requisiciones.aprobar')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/rechazar', [EgresoRequisicionController::class, 'rechazar'])->name('requisiciones.rechazar')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/recursos-materiales', [EgresoRequisicionController::class, 'enviarRecursosMateriales'])->name('requisiciones.recursos')->middleware('permission:requisiciones-edit');
    Route::post('requisiciones/{requisicion}/recibido', [EgresoRequisicionController::class, 'recibido'])->name('requisiciones.recibido')->middleware('permission:requisiciones-cotizar');
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
    // Trámite de CLC entre áreas (cada acción revisa su propio permiso en App\Services\TramiteClc).
    Route::get('clc', [ClcController::class, 'index'])->name('clc.index')->middleware('permission:clc-index');
    Route::post('clc/accion', [ClcController::class, 'accion'])->name('clc.accion')->middleware('permission:clc-index');
    Route::get('clc/relaciones/{relacion}/pdf', [ClcController::class, 'relacionPdf'])->name('clc.relacion.pdf')->middleware('permission:clc-index');
    Route::post('ordenes-compra/{ordenCompra}/pago', [OrdenCompraController::class, 'registrarPago'])->name('ordenes-compra.pago')->middleware('permission:ordenes-compra-pagar');
    Route::get('ordenes-compra/{ordenCompra}/xml', [OrdenCompraController::class, 'descargarXml'])->name('ordenes-compra.xml.descargar')->middleware('permission:requisiciones-index');
    Route::post('ordenes-compra/{ordenCompra}/fecha', [OrdenCompraController::class, 'cambiarFecha'])->name('ordenes-compra.fecha')->middleware('permission:ordenes-compra-fecha');
    Route::post('ordenes-compra/{ordenCompra}/verificar-sat', [OrdenCompraController::class, 'verificarSat'])->name('ordenes-compra.verificar-sat')->middleware('permission:ordenes-compra-verificar-sat');
    Route::post('ordenes-compra/{ordenCompra}/clc', [OrdenCompraController::class, 'generarClc'])->name('ordenes-compra.clc')->middleware('permission:requisiciones-clc');
    Route::get('ordenes-compra/{ordenCompra}/clc', [OrdenCompraController::class, 'clcPdf'])->name('ordenes-compra.clc.pdf')->middleware('permission:requisiciones-index');

    // Proveedores: ahora es un catálogo más (/catalogos/proveedores)
    Route::redirect('proveedores', '/catalogos/proveedores');

    // Catálogos generales (definidos en App\Catalogos\Catalogos). El permiso depende del catálogo:
    // el middleware catalogo:<accion> exige "{prefijo}-<accion>" (p. ej. catalogos-vehiculos-edit).
    Route::get('catalogos', [CatalogoController::class, 'inicio'])->name('catalogos.inicio');
    Route::get('catalogos/{catalogo}', [CatalogoController::class, 'index'])->name('catalogos.index')->middleware('catalogo:index');
    Route::post('catalogos/{catalogo}', [CatalogoController::class, 'store'])->name('catalogos.store')->middleware('catalogo:create');
    Route::get('catalogos/{catalogo}/plantilla', [CatalogoController::class, 'plantilla'])->name('catalogos.plantilla')->middleware('catalogo:import');
    Route::post('catalogos/{catalogo}/importar/previa', [CatalogoController::class, 'previa'])->name('catalogos.previa')->middleware('catalogo:import');
    Route::post('catalogos/{catalogo}/importar', [CatalogoController::class, 'importar'])->name('catalogos.importar')->middleware('catalogo:import');
    Route::put('catalogos/{catalogo}/{registro}', [CatalogoController::class, 'update'])->name('catalogos.update')->middleware('catalogo:edit');
    Route::post('catalogos/{catalogo}/{registro}/estado', [CatalogoController::class, 'estado'])->name('catalogos.estado')->middleware('catalogo:delete');
    Route::get('catalogos/{catalogo}/{registro}/historial', [CatalogoController::class, 'historial'])->name('catalogos.historial')->middleware('catalogo:index');

    // Bitácora de auditoría
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index')->middleware('permission:auditoria-index');

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
