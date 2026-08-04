<?php

use App\Enums\EstadoTicket;
use App\Mail\Helpdesk\TicketCerradoMail;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Models\Helpdesk\Sla;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\OrigenBien;
use App\Models\User;
use App\Services\Helpdesk\HelpdeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Sla::unguard();
    Ticket::unguard();
    BienInformatico::unguard();
    TipoBien::unguard();
    Marca::unguard();
    OrigenBien::unguard();

    $this->servidorUser = User::create([
        'email' => 'normal@example.com',
        'usuario_ti' => 'normal',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->tecnicoUser = User::create([
        'email' => 'tecnico@example.com',
        'usuario_ti' => 'tecnico',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01',
        'nombre' => 'Direccion Test',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P01',
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);

    $this->servidor = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Pedro',
        'apellido' => 'Gomez',
        'user_id' => $this->servidorUser->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => \App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    $this->slaCritico = Sla::create([
        'prioridad' => 'critica',
        'tiempo_resolucion_horas' => 2,
    ]);

    $this->slaNormal = Sla::create([
        'prioridad' => 'media',
        'tiempo_resolucion_horas' => 24,
    ]);

    $this->tipoBien = TipoBien::create(['nombre' => 'Computadora']);
    $this->marca = Marca::create(['nombre' => 'Dell']);
    $this->origen = OrigenBien::create([
        'tipo_origen' => 'compra_publica',
        'entidad_origen' => 'Proveedor S.A.',
        'fecha_adquisicion' => now()->subYear()->format('Y-m-d')
    ]);
});

test('cualquier_servidor_puede_crear_ticket', function () {
    $service = new HelpdeskService();
    
    $ticket = $service->crearTicket([
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaNormal->id,
        'asunto' => 'Mi PC no enciende',
        'descripcion' => 'Al presionar el boton no hace nada',
    ]);

    $ticket->refresh();
    
    expect($ticket)->toBeInstanceOf(Ticket::class);
    // Verificamos que el estado inicial es 'abierto' tal como indica el Enum
    expect($ticket->estado)->toBe(EstadoTicket::ABIERTO->value);
    expect($ticket->solicitante_id)->toBe($this->servidor->id);
});

test('tecnico_puede_asignar_ticket', function () {
    $ticket = Ticket::create([
        'codigo_ticket' => 'TIC-12345',
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaNormal->id,
        'estado' => EstadoTicket::ABIERTO->value,
        'asunto' => 'No hay internet',
        'descripcion' => 'Desconectado',
    ]);

    // Simulamos la lógica de asignación (actualización del ticket)
    $ticket->update([
        'tecnico_id' => $this->tecnicoUser->id,
        'estado' => EstadoTicket::ASIGNADO->value,
    ]);

    $ticket->refresh();
    
    expect($ticket->tecnico_id)->toBe($this->tecnicoUser->id);
    expect($ticket->estado)->toBe(EstadoTicket::ASIGNADO->value);
});

test('sla_critico_tiene_tiempo_2_horas', function () {
    $service = new HelpdeskService();
    
    $ahora = now();
    Carbon::setTestNow($ahora);

    $ticket = $service->crearTicket([
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaCritico->id, // Este SLA da 2 horas
        'asunto' => 'Servidor caido',
        'descripcion' => 'El ERP no responde',
    ]);

    $diferenciaMinutos = $ahora->diffInMinutes($ticket->fecha_vencimiento_sla);
    
    // Verificamos que son exactamente 120 minutos en tiempo calendario continuo
    expect(round($diferenciaMinutos))->toBe(120.0);
});

test('vincular_bien_cambia_estado_a_en_mantenimiento', function () {
    $bien = BienInformatico::create([
        'codigo_institucional' => 'BI-001',
        'codigo_qr' => 'QR-001',
        'tipo_bien_id' => $this->tipoBien->id,
        'marca_id' => $this->marca->id,
        'origen_bien_id' => $this->origen->id,
        'modelo' => 'Optiplex',
        'numero_serie' => 'SN123456',
        'estado_operativo' => 'activo',
    ]);

    $ticket = Ticket::create([
        'codigo_ticket' => 'TIC-12346',
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaNormal->id,
        'estado' => EstadoTicket::ABIERTO->value,
        'asunto' => 'PC lenta',
        'descripcion' => 'No abre word',
    ]);

    $service = new HelpdeskService();
    $service->vincularBienATicket($ticket->id, $bien->id);

    $bien->refresh();
    
    // El estado del bien debe cambiar a en_mantenimiento
    expect($bien->estado_operativo)->toBe('en_mantenimiento');
});

test('cerrar_ticket_devuelve_bien_a_activo', function () {
    Mail::fake();

    $bien = BienInformatico::create([
        'codigo_institucional' => 'BI-002',
        'codigo_qr' => 'QR-002',
        'tipo_bien_id' => $this->tipoBien->id,
        'marca_id' => $this->marca->id,
        'origen_bien_id' => $this->origen->id,
        'modelo' => 'ProDesk',
        'numero_serie' => 'SN98765',
        'estado_operativo' => 'en_mantenimiento',
    ]);

    $ticket = Ticket::create([
        'codigo_ticket' => 'TIC-12347',
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaNormal->id,
        'estado' => EstadoTicket::EN_PROCESO->value,
        'asunto' => 'Cambio RAM',
        'descripcion' => 'Aumento de 8 a 16gb',
        'bien_informatico_id' => $bien->id,
    ]);

    $service = new HelpdeskService();
    $service->cerrarTicket($ticket->id, []);

    $bien->refresh();
    
    // Al cerrar el ticket, el bien informático vuelve a estar activo
    expect($bien->estado_operativo)->toBe('activo');
});

test('encuesta_satisfaccion_se_envia_al_cerrar', function () {
    Mail::fake();

    $ticket = Ticket::create([
        'codigo_ticket' => 'TIC-12348',
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket' => 'incidente',
        'categoria_id' => null,
        'sla_id' => $this->slaNormal->id,
        'estado' => EstadoTicket::RESUELTO->value,
        'asunto' => 'Instalacion',
        'descripcion' => 'Instalar Adobe',
    ]);

    $service = new HelpdeskService();
    $service->cerrarTicket($ticket->id, []);

    // 1. Verificamos que se haya insertado un registro de encuesta pendiente en la base de datos
    $encuesta = EncuestaSatisfaccion::where('ticket_id', $ticket->id)->first();
    expect($encuesta)->not->toBeNull();
    expect($encuesta->calificacion)->toBe(0); // Estado pendiente
    expect($encuesta->fecha_respuesta)->toBeNull();

    // 2. Verificamos que se encoló el correo usando Mail::assertQueued
    Mail::assertQueued(TicketCerradoMail::class, function ($mail) use ($ticket) {
        return $mail->ticket->id === $ticket->id &&
               $mail->hasTo($this->servidorUser->email);
    });
});
