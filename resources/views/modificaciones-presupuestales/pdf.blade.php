<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Modificación Presupuestal {{ $modificacion->folio_completo }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
        .header { text-align: center; margin-bottom: 6px; }
        .header h3 { margin: 2px 0; font-size: 11pt; }
        .header h4 { margin: 2px 0; font-size: 9pt; font-weight: normal; }
        .titulo { text-align: right; font-weight: bold; font-size: 11pt; margin-bottom: 6px; }
        .label { font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; margin: 8px 0; }
        table.data th { background: #ccc; border: 1px solid #000; padding: 3px; text-align: center; font-size: 7.5pt; }
        table.data td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .bloque { margin: 8px 0; line-height: 1.4; }
        .estado { padding: 1px 6px; border: 1px solid #000; font-weight: bold; }
        .firmas { margin-top: 36px; width: 100%; }
        .firmas td { width: 50%; vertical-align: top; padding: 0 12px; }
        .firma-linea { border-top: 1px solid #000; margin-top: 40px; padding-top: 4px; text-align: center; font-size: 8pt; }
        .footer { text-align: center; font-size: 7pt; color: #666; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $money = fn ($n) => '$'.number_format((float) $n, 2);
        $tz = 'America/Mexico_City'; // la app corre en UTC; el formato se imprime en hora local
        $lados = [
            'origen' => ['etiqueta' => 'Origen (disminuye)', 'linea' => $origen, 'signo' => '-'],
            'destino' => ['etiqueta' => 'Destino (aumenta)', 'linea' => $destino, 'signo' => '+'],
        ];
    @endphp

    <div class="header">
        <h3>MUNICIPIO DE GUADALUPE, ZACATECAS</h3>
        <h4>Solicitud de Modificación Presupuestal</h4>
    </div>

    <div class="titulo">{{ $modificacion->folio_completo }}</div>

    <div class="bloque">
        <span class="label">Tipo:</span> {{ mb_strtoupper($tipos[$modificacion->tipo] ?? $modificacion->tipo) }}<br>
        <span class="label">Fecha de solicitud:</span> {{ $modificacion->created_at->setTimezone($tz)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}<br>
        <span class="label">Solicitó:</span> {{ $modificacion->solicitante?->name ?? '—' }}<br>
        @if($modificacion->requisicion)
            <span class="label">Requisición relacionada:</span> {{ $modificacion->requisicion->folio_completo }}<br>
        @endif
        <span class="label">Importe:</span> {{ $money($modificacion->importe) }}<br>
        <span class="label">Estado:</span> <span class="estado">{{ mb_strtoupper($modificacion->estado) }}</span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th></th>
                <th>Unidad</th>
                <th>Fuente</th>
                <th>Proyecto</th>
                <th>Partida</th>
                <th>Vigente actual</th>
                <th>Disponible actual</th>
                <th>Movimiento</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lados as $lado => $info)
                @if($modificacion->{$lado.'_clave'} !== null)
                    @php $l = $info['linea']; @endphp
                    <tr>
                        <td class="label">{{ $info['etiqueta'] }}</td>
                        <td>{{ $l['unidad'] ?? $modificacion->{$lado.'_clave'} }}</td>
                        <td>{{ $modificacion->{$lado.'_fuente'} }} {{ $l['nombre_fuente'] ?? '' }}</td>
                        <td>{{ $modificacion->{$lado.'_proyecto'} }} {{ $l['nombre_proyecto'] ?? '' }}</td>
                        <td>{{ $modificacion->{$lado.'_partida'} }} {{ $l['nombre_partida'] ?? '' }}</td>
                        <td class="num">{{ $l ? $money($l['vigente']) : '—' }}</td>
                        <td class="num">{{ $l ? $money($l['disponible']) : '—' }}</td>
                        <td class="num">{{ $info['signo'] }}{{ $money($modificacion->importe) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="bloque">
        <span class="label">Justificación:</span><br>
        {!! nl2br(e($modificacion->justificacion)) !!}
    </div>

    @if($modificacion->estado !== 'pendiente')
    <div class="bloque">
        <span class="label">{{ $modificacion->estado === 'autorizada' ? 'Autorizó' : 'Rechazó' }}:</span>
        {{ $modificacion->autorizador?->name ?? '—' }} el {{ $modificacion->resuelto_at?->setTimezone($tz)->isoFormat('D/MM/YYYY h:mm a') }}<br>
        @if($modificacion->observaciones_resolucion)
            <span class="label">Observaciones:</span> {{ $modificacion->observaciones_resolucion }}
        @endif
    </div>
    @endif

    @php
        $autoriza = \App\Models\Firmante::vigente('modificacion_presupuestal', 'autoriza', null, $modificacion->created_at);
    @endphp
    <table class="firmas">
        <tr>
            <td><div class="firma-linea">Solicita<br>{{ $modificacion->solicitante?->name }}</div></td>
            <td><div class="firma-linea">Procede / No procede<br>@if($autoriza){{ $autoriza->nombre }}<br>{{ $autoriza->cargo }}@else Jefe de Control Presupuestal @endif</div></td>
        </tr>
    </table>

    <div class="footer">{{ $modificacion->folio_completo }} · Impreso {{ now($tz)->isoFormat('D/MM/YYYY h:mm a') }}</div>
</body>
</html>
