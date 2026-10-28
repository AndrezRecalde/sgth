<?php

use Illuminate\Database\Eloquent\Model;

/*
| Fixtures de estructura organizacional compartidos por los tests. Se cargan
| desde aquí —y no solo vía composer autoload-dev— para que estén disponibles
| sin depender de un `composer dump-autoload` tras clonar la rama.
*/
require_once __DIR__.'/Support/EstructuraFixtures.php';
require_once __DIR__.'/Support/ViaticoFixtures.php';
require_once __DIR__.'/Support/SesionFixtures.php';
require_once __DIR__.'/Support/AccionesPersonalFixtures.php';
require_once __DIR__.'/Support/VacacionesFixtures.php';
require_once __DIR__.'/Support/ComunicadosFixtures.php';
require_once __DIR__.'/Support/RolesFixtures.php';

/*
|--------------------------------------------------------------------------
| Reguard global tras cada test
|--------------------------------------------------------------------------
|
| Varios Feature tests (Viaticos, Seguridad, Nomina, Helpdesk, Asistencia,
| Dispensario) llaman a Model::unguard() en su beforeEach() para poder
| pasar fixtures con columnas que ya no existen (p.ej. puestos.codigo).
| $unguarded es un flag ESTÁTICO de la clase base Model (declarado en el
| trait GuardsAttributes que usa Model), compartido por TODOS los modelos
| del proceso — sin este reguard(), un test que corre antes deja el resto
| de la suite unguarded, y tests que jamás llamaron unguard() (p.ej.
| ServidorPendienteVinculacionTest) de pronto intentan insertar columnas
| inexistentes que antes se descartaban en silencio por no ser fillable.
| Esto hacía que el conteo de fallos "preexistentes" variara según el
| orden de ejecución. Este hook no arregla la deuda de columnas de Puesto
| (sigue pendiente) — solo evita que un test contamine a los demás.
|
| Nota: un afterEach() de nivel superior sin uses()->in(...) solo se
| registra bajo la clave de ESTE archivo (Pest.php no tiene tests propios,
| así que nunca se ejecutaría). Hay que encadenarlo a uses()->in() para
| que Pest lo propague a todos los archivos de Feature/Unit.
|
*/

uses()
    ->afterEach(function () {
        Model::reguard();
    })
    ->in('Feature', 'Unit');

/*
| Los roles del Dispensario con sus permisos, antes de cada prueba del módulo.
| Las rutas pasan de `role:` a `permission:` (docs/plan-roles-y-accesos.md,
| PR 17) y unas 70 pruebas creaban el rol pelado: ver `rolesDelDispensario()`.
*/
uses()
    ->beforeEach(function () {
        // Solo donde hay base y se refresca: FactoresRiesgoMspTest no levanta
        // Laravel, y en una prueba sin RefreshDatabase los roles quedarían
        // escritos en la base compartida.
        if ($this instanceof Tests\TestCase
            && in_array(Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this), true)) {
            rolesDelDispensario();
        }
    })
    ->in('Feature/Dispensario');

/*
| Talento Humano con los permisos del Expediente, antes de cada prueba de los
| directorios que lo abren. Las rutas y ServidorPolicy pasaron de `role:` a
| `permission:` el 2026-10-28 (docs/revision-permisos-sin-uso.md, 3.2): ver
| `rolesDeTalentoHumano()`.
*/
uses()
    ->beforeEach(function () {
        if ($this instanceof Tests\TestCase
            && in_array(Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this), true)) {
            rolesDeTalentoHumano();
        }
    })
    ->in('Feature/Expediente', 'Feature/Disciplinario');
