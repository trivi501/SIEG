<?php

namespace Tests;

use App\Services\EstadoCfdiSat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Respuesta simulada del servicio de verificación de CFDI del SAT (null = el SAT no responde). */
    protected ?string $estadoSat = EstadoCfdiSat::VIGENTE;

    protected bool $efosSat = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas nunca salen a internet: el SAT responde lo que diga $estadoSat.
        Http::preventStrayRequests();
        Http::fake([
            EstadoCfdiSat::URL => fn () => $this->estadoSat === null
                ? Http::response('Service Unavailable', 503)
                : Http::response($this->respuestaSat($this->estadoSat, $this->efosSat), 200, ['Content-Type' => 'text/xml']),
        ]);
    }

    protected function respuestaSat(string $estado, bool $efos = false): string
    {
        $cancelacion = $estado === EstadoCfdiSat::CANCELADO ? 'Cancelado sin aceptación' : '';

        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
            .'<ConsultaResponse xmlns="http://tempuri.org/"><ConsultaResult xmlns:a="http://schemas.datacontract.org/2004/07/Sat.Cfdi.Negocio.ConsultaCfdi.Servicio" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">'
            .'<a:CodigoEstatus>S - Comprobante obtenido satisfactoriamente.</a:CodigoEstatus>'
            .'<a:EsCancelable>Cancelable con aceptación</a:EsCancelable>'
            ."<a:Estado>{$estado}</a:Estado>"
            ."<a:EstatusCancelacion>{$cancelacion}</a:EstatusCancelacion>"
            .'<a:ValidacionEFOS>'.($efos ? '100' : '200').'</a:ValidacionEFOS>'
            .'</ConsultaResult></ConsultaResponse></s:Body></s:Envelope>';
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
