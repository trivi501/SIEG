<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Compra {{ $orden->folio_completo }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
        .header { text-align: center; margin-bottom: 6px; }
        .header h3 { margin: 2px 0; font-size: 11pt; }
        .header h4 { margin: 2px 0; font-size: 9pt; font-weight: normal; }
        .titulo { text-align: right; font-weight: bold; font-size: 11pt; margin-bottom: 6px; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.info td { padding: 2px 4px; vertical-align: top; }
        .label { font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; margin: 6px 0; }
        table.data th { background: #ccc; border: 1px solid #000; padding: 3px; text-align: center; font-size: 7.5pt; }
        table.data td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        table.totales { margin-left: auto; border-collapse: collapse; }
        table.totales td { padding: 1px 4px; }
        .firmas { margin-top: 30px; width: 100%; }
        .firmas td { width: 33%; vertical-align: top; padding: 0 8px; }
        .firma-linea { border-top: 1px solid #000; margin-top: 40px; padding-top: 4px; text-align: center; font-size: 8pt; }
        .footer { text-align: center; font-size: 7pt; color: #666; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $req = $orden->requisicion;
        $detalles = $req?->tbEgresoRequisicionDetalles ?? collect();
        $subtotal = $detalles->sum(fn ($d) => (float) $d->sub_total);
        $iva = $detalles->sum(fn ($d) => (float) $d->iva);
    @endphp

    <div class="header">
        <h3>MUNICIPIO DE GUADALUPE, ZACATECAS</h3>
        <h4>Recursos Materiales</h4>
    </div>

    <div class="titulo">ORDEN DE COMPRA {{ $orden->folio_completo }}</div>

    <table class="info">
        <tr>
            <td width="50%"><span class="label">Fecha:</span> {{ \Carbon\Carbon::parse($orden->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td>
            <td><span class="label">Requisición:</span> {{ $req?->folio_completo }}</td>
        </tr>
        <tr>
            <td><span class="label">Proveedor:</span> {{ $orden->proveedor->nombre }}</td>
            <td><span class="label">RFC:</span> {{ $orden->proveedor->rfc ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Unidad administrativa solicitante:</span> {{ $req?->catEgresoUnidadAdministrativa?->nombre ?? '—' }}</td>
        </tr>
        @if($orden->proveedor->domicilio)
        <tr><td colspan="2"><span class="label">Domicilio del proveedor:</span> {{ $orden->proveedor->domicilio }}</td></tr>
        @endif
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Fuente</th>
                <th>Proy</th>
                <th>Partida</th>
                <th>Cant</th>
                <th>Descripción</th>
                <th>Precio unit.</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $i => $d)
            <tr>
                <td style="text-align:center">{{ $i + 1 }}</td>
                <td>{{ $d->fuente }}</td>
                <td>{{ $d->proyecto }}</td>
                <td>{{ $d->partida }}</td>
                <td class="num">{{ number_format((float) $d->cantidad, 2) }}</td>
                <td>{{ $d->descripcion }}</td>
                <td class="num">${{ number_format((float) $d->precio_unitario, 2) }}</td>
                <td class="num">${{ number_format((float) $d->sub_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales">
        <tr><td class="label">Subtotal:</td><td class="num">${{ number_format($subtotal, 2) }}</td></tr>
        <tr><td class="label">IVA:</td><td class="num">${{ number_format($iva, 2) }}</td></tr>
        <tr><td class="label">TOTAL:</td><td class="num"><strong>${{ number_format($orden->importe, 2) }}</strong></td></tr>
    </table>

    @if($orden->observaciones)
    <p><span class="label">Observaciones:</span> {{ $orden->observaciones }}</p>
    @endif

    <table class="firmas">
        <tr>
            <td><div class="firma-linea">Elaboró<br>Recursos Materiales</div></td>
            <td><div class="firma-linea">Autorizó</div></td>
            <td><div class="firma-linea">Proveedor<br>{{ $orden->proveedor->nombre }}</div></td>
        </tr>
    </table>

    <div class="footer">{{ $orden->folio_completo }} · Requisición {{ $req?->folio_completo }} · Impreso {{ now('America/Mexico_City')->isoFormat('D/MM/YYYY h:mm a') }}</div>
</body>
</html>
