<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>FUR {{ $requisicion->folio_completo }}</title>
    <style>
        @page { margin: 8mm 12mm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
        .header { text-align: center; margin-bottom: 8px; }
        .header img { max-height: 60px; }
        .header h3 { margin: 2px 0; font-size: 9pt; }
        .header h4 { margin: 2px 0; font-size: 8pt; font-weight: normal; }
        .info { margin-bottom: 6px; line-height: 1.4; }
        .info span.label { font-weight: bold; }
        .fur { text-align: right; font-weight: bold; font-size: 10pt; margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin: 6px 0; }
        table.data th { background: #ccc; border: 1px solid #000; padding: 3px; text-align: center; font-size: 7pt; }
        table.data td { border: 1px solid #000; padding: 2px 3px; vertical-align: top; }
        .concepto { margin: 8px 0; line-height: 1.3; }
        .firmas { margin-top: 14px; }
        .firmas td { width: 50%; vertical-align: top; padding: 0 8px; }
        .firma-linea { border-top: 1px solid #000; margin-top: 35px; padding-top: 4px; text-align: center; font-size: 8pt; }
        .footer { text-align: center; font-size: 7pt; color: #666; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h3>MUNICIPIO DE GUADALUPE, ZACATECAS</h3>
        <h4>{{ $requisicion->catEgresoUnidadAdministrativa?->nombre ?? '—' }}</h4>
    </div>

    <div class="fur">
        FUR {{ $requisicion->folio_completo }}
    </div>

    <div class="info">
        <span class="label">Fecha:</span><br>
        {{ \Carbon\Carbon::parse($requisicion->registro ?? now())->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}<br>
        <span class="label">Dirección o Unidad administrativa:</span><br>
        {{ strtoupper($requisicion->catEgresoUnidadAdministrativa?->nombre ?? '—') }}
    </div>

    @php $detalles = $requisicion->tbEgresoRequisicionDetalles ?? collect(); @endphp

    @if($detalles->count() > 0)
    <table class="data">
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Proy</th>
                <th>Partida</th>
                <th>Cant</th>
                <th>Artículo / material / Servicio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $d)
            <tr>
                <td>{{ $d->fuente ?? '—' }}</td>
                <td>{{ $d->proyecto ?? '—' }}</td>
                <td>{{ $d->partida ?? '—' }}</td>
                <td style="text-align:center">{{ is_numeric($d->cantidad) ? number_format((float)$d->cantidad, 2) : $d->cantidad }}</td>
                <td>{{ $d->descripcion }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="concepto">
        <strong>CONCEPTO:</strong><br>
        {{ $requisicion->observaciones ?: '—' }}
    </div>

    <table class="firmas" width="100%">
        <tr>
            <td>
                <div class="firma-linea">
                    <strong>{{ $requisicion->beneficiario ?: '___________________________' }}</strong><br>
                    Solicitante
                    <br><small>{{ now()->isoFormat('D/MM/YYYY h:mm a') }}</small>
                </div>
            </td>
            <td>
                @php
                    $autoriza = \App\Models\Firmante::vigente('requisicion', 'autoriza', $requisicion->id_cat_egreso_unidad_administrativa,
                        $requisicion->registro ? \Carbon\Carbon::parse($requisicion->registro) : null);
                @endphp
                <div class="firma-linea">
                    <strong>{{ $autoriza?->nombre ?? '___________________________' }}</strong><br>
                    Autorizó
                    <br><small>{{ $autoriza?->cargo ?? "\u{00A0}" }}</small>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        FUR {{ $requisicion->folio_completo }}
    </div>
</body>
</html>
