<?php
$baseDir = "c:/laragon/www/sgth/sgth-backend/app";

function ensureDir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

ensureDir("$baseDir/Http/Requests/Sso");
ensureDir("$baseDir/Http/Resources/Sso");
ensureDir("$baseDir/Http/Controllers/Sso");

$entities = [
    'RiesgoLaboral' => 'riesgo laboral',
    'AccidenteTrabajo' => 'accidente de trabajo',
    'EquipoProteccion' => 'equipo de protección',
    'InspeccionSso' => 'inspección SSO',
    'CapacitacionSso' => 'capacitación SSO',
];

foreach ($entities as $entity => $name) {
    // StoreRequest
    $storeContent = "<?php\n\nnamespace App\Http\Requests\Sso;\n\nuse Illuminate\Foundation\Http\FormRequest;\n\nclass Store{$entity}Request extends FormRequest\n{\n    public function authorize(): bool\n    {\n        return \$this->user()->can('create', \App\Models\Sso\\{$entity}::class);\n    }\n\n    public function rules(): array\n    {\n        return [\n            // Reglas validadas\n        ];\n    }\n}\n";
    file_put_contents("$baseDir/Http/Requests/Sso/Store{$entity}Request.php", $storeContent);

    // UpdateRequest
    $updateContent = "<?php\n\nnamespace App\Http\Requests\Sso;\n\nuse Illuminate\Foundation\Http\FormRequest;\n\nclass Update{$entity}Request extends FormRequest\n{\n    public function authorize(): bool\n    {\n        return \$this->user()->can('update', \$this->route('" . strtolower($entity) . "'));\n    }\n\n    public function rules(): array\n    {\n        return [\n            // Reglas validadas\n        ];\n    }\n}\n";
    file_put_contents("$baseDir/Http/Requests/Sso/Update{$entity}Request.php", $updateContent);

    // Resource
    $resourceContent = "<?php\n\nnamespace App\Http\Resources\Sso;\n\nuse Illuminate\Http\Request;\nuse Illuminate\Http\Resources\Json\JsonResource;\n\nclass {$entity}Resource extends JsonResource\n{\n    public function toArray(Request \$request): array\n    {\n        return parent::toArray(\$request);\n    }\n}\n";
    file_put_contents("$baseDir/Http/Resources/Sso/{$entity}Resource.php", $resourceContent);

    // Controller
    $controllerContent = "<?php\n\nnamespace App\Http\Controllers\Sso;\n\nuse App\Http\Controllers\Controller;\nuse App\Http\Requests\Sso\Store{$entity}Request;\nuse App\Http\Requests\Sso\Update{$entity}Request;\nuse App\Http\Resources\Sso\\{$entity}Resource;\nuse App\Http\Responses\ApiResponse;\nuse App\Contracts\Sso\SsoServiceInterface;\nuse Illuminate\Http\JsonResponse;\n\nfinal class {$entity}Controller extends Controller\n{\n    public function __construct(\n        private readonly SsoServiceInterface \$ssoService,\n    ) {}\n\n    public function store(Store{$entity}Request \$request): JsonResponse\n    {\n        \$method = 'registrar' . str_replace('Sso', '', '{$entity}');\n        if (!method_exists(\$this->ssoService, \$method)) {\n            \$method = 'registrar{$entity}';\n        }\n        \$registro = \$this->ssoService->\$method(\$request->validated());\n        return ApiResponse::created(new {$entity}Resource(\$registro), '" . ucfirst($name) . " registrado exitosamente.');\n    }\n\n    public function update(Update{$entity}Request \$request, int \$id): JsonResponse\n    {\n        \$method = 'actualizar' . str_replace('Sso', '', '{$entity}');\n        if (!method_exists(\$this->ssoService, \$method)) {\n            \$method = 'actualizar{$entity}';\n        }\n        \$registro = \$this->ssoService->\$method(\$id, \$request->validated());\n        return ApiResponse::ok(new {$entity}Resource(\$registro), '" . ucfirst($name) . " actualizado exitosamente.');\n    }\n}\n";
    file_put_contents("$baseDir/Http/Controllers/Sso/{$entity}Controller.php", $controllerContent);
}

echo "Archivos generados exitosamente.";
