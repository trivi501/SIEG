<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>CLC {{ $orden->clc_folio_completo }}</title>
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
        .importe { border: 1px solid #000; padding: 6px; margin: 8px 0; font-size: 10pt; }
        .firmas { margin-top: 30px; width: 100%; }
        .firmas td { width: 33%; vertical-align: top; padding: 0 8px; }
        .firma-linea { border-top: 1px solid #000; margin-top: 40px; padding-top: 4px; text-align: center; font-size: 8pt; }
        .footer { text-align: center; font-size: 7pt; color: #666; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $req = $orden->requisicion;
        $unidad = $req?->catEgresoUnidadAdministrativa;
        // Importe por clave presupuestal (fuente / proyecto / partida) de los conceptos de la requisición.
        $claves = ($req?->tbEgresoRequisicionDetalles ?? collect())
            ->groupBy(fn ($d) => trim((string) $d->fuente).'|'.trim((string) $d->proyecto).'|'.trim((string) $d->partida))
            ->map(fn ($ds) => ['detalle' => $ds->first(), 'importe' => $ds->sum(fn ($d) => (float) ($d->total ?? $d->sub_total))]);
        $proveedor = $orden->proveedor;
        $cuenta = $proveedor->clabe || $proveedor->cuenta_bancaria
            ? (object) ['banco' => $proveedor->banco, 'clabe' => $proveedor->clabe, 'cuenta' => $proveedor->cuenta_bancaria]
            : $proveedor->cuentas()->where('activo', true)->orderByDesc('principal')->with('banco')->first();
        $fecha = \Carbon\Carbon::parse($orden->clc_fecha);
    @endphp

    <div class="header">
        <h3>MUNICIPIO DE GUADALUPE, ZACATECAS</h3>
        <h4>Cuenta por Liquidar Certificada</h4>
    </div>

    <div class="titulo">CLC {{ $orden->clc_folio_completo }}</div>

    <table class="info">
        <tr>
            <td width="50%"><span class="label">Fecha:</span> {{ $fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td>
            <td><span class="label">Requisición:</span> {{ $req?->folio_completo }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Unidad administrativa:</span> {{ $unidad?->clave }} {{ $unidad?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="label">Beneficiario:</span> {{ $orden->proveedor->nombre }}</td>
            <td><span class="label">RFC:</span> {{ $orden->proveedor->rfc ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="label">Orden de compra:</span> {{ $orden->folio_completo }}</td>
            <td><span class="label">Factura:</span> {{ trim(($orden->xml_serie ?? '').' '.($orden->xml_folio ?? '')) ?: '—' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Folio fiscal (UUID):</span> {{ $orden->xml_uuid }}</td>
        </tr>
        @if($cuenta)
        <tr>
            <td><span class="label">Banco:</span> {{ $cuenta->banco?->descripcion ?? '—' }}</td>
            <td><span class="label">CLABE:</span> {{ $cuenta->clabe ?? '—' }} @if($cuenta->cuenta) · <span class="label">Cuenta:</span> {{ $cuenta->cuenta }} @endif</td>
        </tr>
        @endif
        @if($req?->observaciones)
        <tr><td colspan="2"><span class="label">Concepto:</span> {{ $req->observaciones }}</td></tr>
        @endif
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Unidad</th>
                <th>Fuente</th>
                <th>Proyecto</th>
                <th>Partida</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($claves as $c)
            <tr>
                <td>{{ $unidad?->clave }}</td>
                <td>{{ $c['detalle']->fuente }}</td>
                <td>{{ $c['detalle']->proyecto }}</td>
                <td>{{ $c['detalle']->partida }}</td>
                <td class="num">${{ number_format($c['importe'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="importe">
        <span class="label">Importe a pagar:</span> <strong>${{ number_format($orden->importe, 2) }}</strong>
    </div>

    @php
        $unidadId = $req?->id_cat_egreso_unidad_administrativa;
        $elabora = \App\Models\Firmante::vigente('clc', 'elabora', $unidadId, $fecha);
        $revisa = \App\Models\Firmante::vigente('clc', 'revisa', $unidadId, $fecha);
        $autoriza = \App\Models\Firmante::vigente('clc', 'autoriza', $unidadId, $fecha);
    @endphp
    <table class="firmas">
        <tr>
            <td><div class="firma-linea">Elaboró<br>{{ $elabora?->nombre ?? ($unidad?->nombre ?? 'Área solicitante') }}@if($elabora)<br>{{ $elabora->cargo }}@endif</div></td>
            <td><div class="firma-linea">Revisó@if($revisa)<br>{{ $revisa->nombre }}<br>{{ $revisa->cargo }}@endif</div></td>
            <td><div class="firma-linea">Autorizó@if($autoriza)<br>{{ $autoriza->nombre }}<br>{{ $autoriza->cargo }}@endif</div></td>
        </tr>
    </table>

    <div class="footer">{{ $orden->clc_folio_completo }} · {{ $orden->folio_completo }} · Requisición {{ $req?->folio_completo }} · Impreso {{ now('America/Mexico_City')->isoFormat('D/MM/YYYY h:mm a') }}</div>
</body>
</html>
