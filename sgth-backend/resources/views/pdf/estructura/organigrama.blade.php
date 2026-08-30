<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Organigrama Institucional</title>
    <style>
        @page { margin: 90px 40px 60px 40px; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            margin: 0;
        }

        header {
            position: fixed;
            top: -70px; left: 0; right: 0;
            text-align: center;
        }
        header img { max-width: 80px; }
        header h1 {
            font-size: 13px;
            margin: 4px 0 0 0;
            letter-spacing: 0.5px;
        }
        header h2 {
            font-size: 8.5px;
            font-weight: normal;
            margin: 2px 0 0 0;
            color: #6b7280;
        }

        footer {
            position: fixed;
            bottom: -40px; left: 0; right: 0;
            font-size: 7.5px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        footer .pagina:after { content: counter(page); }

        /* ── Institución (nivel 1) ───────────────────────────── */
        .institucion {
            background: #065f46;
            color: #fff;
            text-align: center;
            padding: 10px 12px;
            border-radius: 5px;
            margin-bottom: 4px;
        }
        .institucion .nombre {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .institucion .detalle {
            font-size: 7.5px;
            color: #d1fae5;
            margin-top: 3px;
        }

        /* ── Categoría de proceso ────────────────────────────── */
        .categoria {
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .categoria > .titulo {
            background: #ecfdf5;
            border-left: 4px solid #059669;
            color: #065f46;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 5px 8px;
            margin-bottom: 6px;
        }

        /* ── Unidad administrativa (nivel 2) ─────────────────── */
        .unidad {
            border: 1px solid #d1d5db;
            border-left: 3px solid #059669;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .unidad .nombre {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
        }
        .unidad .meta {
            font-size: 7.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── Subprocesos (nivel 3) ───────────────────────────── */
        .subprocesos {
            margin: 6px 0 0 10px;
            border-left: 1px dotted #9ca3af;
            padding-left: 8px;
        }
        .subprocesos .rotulo {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #059669;
            margin-bottom: 3px;
        }
        .subproceso {
            font-size: 8px;
            color: #374151;
            padding: 2px 0;
        }
        .subproceso .nombre { font-weight: bold; }
        .subproceso .codigo { color: #9ca3af; }

        .sin-datos {
            font-size: 8.5px;
            color: #6b7280;
            font-style: italic;
            padding: 12px 0;
        }
    </style>
</head>
<body>
    <header>
        @if(file_exists($logo))
            <img src="{{ $logo }}" alt="Logo GADPE">
        @endif
        <h1>ORGANIGRAMA INSTITUCIONAL</h1>
        <h2>{{ $institucion->descripcion ?? 'GAD Provincial de Esmeraldas' }}</h2>
    </header>

    <footer>
        Generado el {{ $fecha->locale('es')->isoFormat('DD [de] MMMM [del] YYYY, HH:mm') }}
        <span style="float:right;">Página <span class="pagina"></span></span>
    </footer>

    @if(! $institucion)
        <p class="sin-datos">No hay unidades administrativas registradas.</p>
    @else
        <div class="institucion">
            <div class="nombre">{{ $institucion->nombre }}</div>
            @if($institucion->acronimo || $institucion->codigo)
                <div class="detalle">
                    {{ collect([$institucion->acronimo, $institucion->codigo])->filter()->implode(' · ') }}
                </div>
            @endif
        </div>

        @forelse($porCategoria as $categoria)
            <div class="categoria">
                <div class="titulo">{{ $categoria['titulo'] }}</div>

                @foreach($categoria['unidades'] as $unidad)
                    <div class="unidad">
                        <div class="nombre">{{ $unidad->nombre }}</div>
                        <div class="meta">
                            {{ collect([
                                $unidad->acronimo,
                                $unidad->codigo,
                                $unidad->tipoUnidad?->descripcion,
                            ])->filter()->implode(' · ') }}
                        </div>

                        @if($unidad->hijos->isNotEmpty())
                            <div class="subprocesos">
                                <div class="rotulo">
                                    {{ $unidad->hijos->count() === 1 ? 'Subproceso' : 'Subprocesos' }}
                                    ({{ $unidad->hijos->count() }})
                                </div>
                                @foreach($unidad->hijos as $subproceso)
                                    <div class="subproceso">
                                        <span class="nombre">{{ $subproceso->nombre }}</span>
                                        @if($subproceso->acronimo || $subproceso->codigo)
                                            <span class="codigo">
                                                — {{ collect([$subproceso->acronimo, $subproceso->codigo])->filter()->implode(' · ') }}
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <p class="sin-datos">La institución no tiene unidades administrativas registradas.</p>
        @endforelse
    @endif
</body>
</html>
