import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";
import paginaConPageShell from "./eslint-rules/pagina-con-pageshell.mjs";

/*
| Las prohibiciones de `.antigravity/rules/09-prohibiciones.md` que se pueden
| comprobar sin ejecutar nada. Mientras vivían solo en el documento, se
| cumplían por buena memoria: `FormModal` pasó meses en el catálogo sin que
| ningún modal lo usara.
|
| ESLint aplica, para cada regla, el último bloque que coincide con el archivo:
| no fusiona listas. De ahí que cada bloque repita las piezas del anterior en
| vez de añadir solo las suyas.
*/

/** Paquetes y rutas que no se importan en ningún sitio. */
const IMPORTS_PROHIBIDOS = [
  {
    name: "zod",
    message: "Importe desde 'zod/v4'. 'zod' a secas trae la API v3 y los mensajes de error cambian (regla 07).",
  },
  {
    name: "@mantine/form",
    message: "Los formularios son React Hook Form + Zod (regla 07).",
  },
  {
    name: "@mantine/charts",
    message: "Las gráficas son ECharts con useEChartsColors() (regla 06).",
  },
];

/** Piezas de Mantine que solo usa el catálogo de `components/`, que las envuelve. */
const IMPORTS_SOLO_CATALOGO = [
  {
    name: "@mantine/core",
    importNames: ["Modal"],
    message: "Use SgthModal o FormModal de '@/components/ui': resuelven la pantalla completa en móvil y el pie estándar (regla 06).",
  },
  {
    name: "@mantine/core",
    importNames: ["Drawer"],
    message: "Use SgthDrawer de '@/components/ui': pone la pantalla completa en móvil, uno de tres anchos y la cabecera con título y descripción. El pie con botones va en ModalFooter (regla 06).",
  },
  {
    name: "@mantine/core",
    importNames: ["Badge"],
    message: "Use StatusBadge (estados, señales y categorías), CountBadge (una cifra) o LegendBadge (leyenda de un gráfico) de '@/components/ui': el color sale del significado, no se escribe a mano (regla 06).",
  },
  {
    name: "@mantine/core",
    importNames: ["Table"],
    message: "Use SgthTable de '@/components/ui', con las columnas en su .columns.tsx. Para pares etiqueta/valor, DetailList (regla 06).",
  },
  {
    name: "@mantine/notifications",
    importNames: ["notifications"],
    message: "Use notificar.exito/error/aviso de '@/components/ui': el color y el icono salen del resultado, no se escriben en cada llamada (regla 08).",
  },
  {
    name: "mantine-datatable",
    importNames: ["DataTable"],
    message: "Use SgthTable de '@/components/ui' (regla 06).",
  },
];

const COLOR_HEX = {
  selector: "Literal[value=/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/]",
  message: "Nada de colores en hexadecimal: se rompen en modo oscuro. Use tokens --sgth-* o props de color de Mantine; en ECharts, useEChartsColors() (regla 03).",
};

/*
| Un color escrito a mano solo puede ser de la paleta del sistema
| (design.tokens.ts): primario (el del subsistema), emerald, ocean, amethyst,
| amber, red, slate y dark, con o sin tono (`amber.7`), además de dimmed,
| inherit, white, currentColor y los tokens --sgth-* o --mantine-color-* de
| esas mismas escalas. `blue`, `orange`, `gray`, `teal`, `violet`… quedan
| fuera: antes el mismo gesto salía de cinco colores según quién hizo la
| pantalla (regla 03).
*/
const VALOR_DE_PALETA =
  "/^(primario|emerald|ocean|amethyst|amber|red|slate|dark)(\\.[0-9])?$|^(dimmed|inherit|white|currentColor)$|^var\\(--(sgth-|mantine-color-(primario|emerald|ocean|amethyst|amber|red|slate|dark|dimmed|text|body|white|default))/";
const MENSAJE_PALETA =
  "Color fuera de la paleta: use primario (el del subsistema), emerald, ocean, amethyst, amber, red o slate (o dimmed), mejor aún un tono de SEMANTIC_COLOR. Un ThemeIcon o Avatar decorativo va sin color: toma el acento del subsistema (regla 03).";
const PROP_COLOR = "JSXAttribute[name.name=/^(color|c|bg)$/]";
const COLORES_FUERA_DE_PALETA = [
  `${PROP_COLOR} > Literal:not([value=${VALOR_DE_PALETA}])`,
  `${PROP_COLOR} > JSXExpressionContainer > Literal:not([value=${VALOR_DE_PALETA}])`,
  `${PROP_COLOR} > JSXExpressionContainer > ConditionalExpression > Literal:not([value=${VALOR_DE_PALETA}])`,
  `Property[key.name="color"] > Literal:not([value=${VALOR_DE_PALETA}])`,
  `Property[key.name="color"] > ConditionalExpression > Literal:not([value=${VALOR_DE_PALETA}])`,
  "Literal[value=/var\\(--mantine-color-(blue|gray|orange|teal|yellow|violet|grape|cyan|green|indigo|pink|lime)-/]",
].map((selector) => ({ selector, message: MENSAJE_PALETA }));

const SINTAXIS_TS = [
  {
    selector: 'TSAsExpression[expression.type="TSAsExpression"][expression.typeAnnotation.type="TSUnknownKeyword"]',
    message: "Nunca `as unknown as`: el problema real es que types/api.ts no coincide con el backend. Corríjalo ahí (regla 09).",
  },
  // Hermano de `as unknown as` y por la misma puerta: apagar el compilador
  // justo donde hace falta. Aparecía en los valores iniciales de un formulario
  // —el esquema Zod declara `number` y el campo arranca vacío— y se resuelve
  // con `DefaultValues<T>` omitiendo la clave, más `resetField()` en vez de
  // `setValue(campo, undefined)`.
  {
    selector: 'TSAsExpression[typeAnnotation.type="TSNeverKeyword"]',
    message: "Nunca `as never`: si son los valores iniciales de un formulario, use `DefaultValues<T>` omitiendo la clave y `resetField()` (regla 09).",
  },
  // El tercero de la familia, y el único que llegó a romper pantallas: afirmar
  // que el valor de un selector de fecha es un `Date`.
  //
  // En Mantine v9 no lo es. `DateValue = string | Date | null`, y el selector
  // entrega la CADENA `YYYY-MM-DD` en cuanto alguien elige una fecha; solo el
  // valor inicial que uno le pasa es un `Date`. Con la aserción, el compilador
  // callaba y `d.getFullYear()` lanzaba en ejecución: cuatro modales —abrir
  // sumario, registrar visto bueno, transicionarlo y completar vínculo— no se
  // enviaban si tocabas el calendario, y sí funcionaban si no lo tocabas.
  //
  // Lo correcto es guardar `Date | string | null` y pasarlo por `fromDateValue`
  // (o su pariente `OrNull`/`OrUndefined`) de `@/lib/fecha`, que lee las dos
  // formas. Ensanchar hacia la verdad —`as Date | string | null`— sigue
  // permitido: eso no afirma de más.
  {
    selector: 'TSAsExpression > TSTypeReference > Identifier[name="Date"]',
    message: "Nunca `as Date`: el selector de Mantine v9 devuelve una cadena en cuanto se elige fecha. Guarde `Date | string | null` y convierta con `fromDateValue` de '@/lib/fecha' (regla 09).",
  },
  {
    selector: 'TSAsExpression > TSUnionType:not(:has(TSStringKeyword)) > TSTypeReference > Identifier[name="Date"]',
    message: "Nunca `as Date | null`: el selector de Mantine v9 devuelve una cadena en cuanto se elige fecha. Guarde `Date | string | null` y convierta con `fromDateValue` de '@/lib/fecha' (regla 09).",
  },
  {
    selector: 'CallExpression[callee.name="fetch"]',
    message: "Nunca fetch nativo: use axios desde '@/lib/axios' (regla 08).",
  },
  {
    selector: 'CallExpression[callee.name="confirm"], CallExpression[callee.object.name="window"][callee.property.name="confirm"]',
    message: "Nunca el confirm() del navegador: use confirmar() de '@/components/ui' (regla 06).",
  },
  // El título de una notificación dice qué pasó y con qué. Eran 105 «Error»
  // idénticos: la misma notificación roja para un cargo, un turno o una nómina.
  {
    selector:
      'CallExpression[callee.property.name=/^(exito|error|aviso|alFallar)$/]:not([callee.object.name="console"]) > Literal.arguments:first-child[value=/^(Error|Éxito|Exito|Aviso|Atención|Advertencia|Listo|Hecho|Guardado|Eliminado|Eliminada|Registrado|Registrada|Actualizado|Finalizado|Cancelado|Operación exitosa|No se pudo [a-záéíóú]+|No se pudieron [a-záéíóú]+|Error al .+)$/]',
    message: "Título de notificación genérico: diga qué pasó y con qué, «No se pudo anular el permiso» o «Cargo creado» (regla 08).",
  },
];

const SINTAXIS_TSX = [
  {
    selector: 'JSXOpeningElement[name.name="table"]',
    message: "Nunca una tabla HTML: use SgthTable de '@/components/ui' (regla 06).",
  },
  // La validación de formularios la hace Zod vía zodResolver. El prop
  // `required` de Mantine llega al DOM como atributo `required` real, y
  // sin `noValidate` el navegador cancela el evento `submit` antes de que
  // React Hook Form llegue a ejecutarse: no se dispara la petición y no se
  // renderiza ningún mensaje de error.
  {
    selector:
      'JSXOpeningElement[name.name="form"]:not(:has(JSXAttribute[name.name="noValidate"], JSXSpreadAttribute))',
    message:
      "Todo <form> debe llevar noValidate: la validación la hace Zod (zodResolver), no el navegador. Sin él, el `required` de Mantine bloquea el submit y los mensajes de error nunca se pintan.",
  },
  // Un título de sección no se escribe dentro de un separador. El `Divider` de
  // Mantine es un `role="separator"`: el nombre de la sección se anunciaba como
  // el texto de una línea divisoria y no como un encabezado, así que nada decía
  // dónde empieza «Alergias» o «Identificación». Y cuando la etiqueta llevaba
  // dentro un botón —el «+» de agregar—, ese botón quedaba dentro del separador.
  //
  // Eran 31 en el repositorio, repartidos en once módulos y con tres aspectos
  // distintos para lo mismo. Ahora hay dos piezas, según quepa la tarjeta:
  // `SectionCard` en el cuerpo de una página, `SectionHeading` en un panel o un
  // cajón. Las dos pintan un `h3` (regla 06).
  //
  // El `Divider` SIN etiqueta sigue estando bien: separar es justo lo suyo.
  {
    selector: 'JSXOpeningElement[name.name="Divider"] > JSXAttribute[name.name="label"]',
    message: "Un título de sección no va dentro de un `Divider`, que es un role=\"separator\": use SectionCard o SectionHeading de '@/components/ui' (regla 06). Un Divider sin etiqueta sigue valiendo.",
  },
];

const USE_CLIENT_EN_PAGINA = {
  selector: 'Program > ExpressionStatement[directive="use client"]',
  message: "Un page.tsx no lleva 'use client': exporta metadata y la lógica va en una vista hermana (regla 05).",
};

/** Los únicos sitios donde un hexadecimal es legítimo (regla 09). */
const HEX_PERMITIDO = [
  "src/config/design.tokens.ts",
  "src/hooks/useEChartsColors.ts",
  "src/app/layout.tsx",
];

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  {
    rules: {
      // Un identificador que empieza por `_` declara que no se usa a
      // propósito: parámetros que hay que mantener por la firma, elementos
      // descartados al desestructurar, errores capturados que no se inspeccionan.
      "@typescript-eslint/no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_",
          destructuredArrayIgnorePattern: "^_",
        },
      ],
    },
  },
  {
    files: ["**/*.ts", "**/*.tsx"],
    rules: {
      "no-restricted-imports": ["error", { paths: [...IMPORTS_PROHIBIDOS, ...IMPORTS_SOLO_CATALOGO] }],
      "no-restricted-syntax": ["error", ...SINTAXIS_TS, COLOR_HEX, ...COLORES_FUERA_DE_PALETA],
    },
  },
  {
    files: ["**/*.tsx"],
    rules: {
      "no-restricted-syntax": ["error", ...SINTAXIS_TS, COLOR_HEX, ...COLORES_FUERA_DE_PALETA, ...SINTAXIS_TSX],
    },
  },
  {
    files: ["src/app/**/page.tsx"],
    rules: {
      "no-restricted-syntax": ["error", ...SINTAXIS_TS, COLOR_HEX, ...COLORES_FUERA_DE_PALETA, ...SINTAXIS_TSX, USE_CLIENT_EN_PAGINA],
    },
  },
  {
    // Solo el área autenticada: login, cambiar contraseña y los cuestionarios
    // públicos tienen su propio lienzo, sin shell.
    files: ["src/app/(sgth)/**/page.tsx", "src/app/(salud)/**/page.tsx", "src/app/(portal)/**/page.tsx"],
    plugins: { sgth: { rules: { "pagina-con-pageshell": paginaConPageShell } } },
    rules: {
      "sgth/pagina-con-pageshell": "error",
    },
  },
  {
    // El catálogo es quien envuelve Modal y DataTable.
    files: ["src/components/**"],
    rules: {
      "no-restricted-imports": ["error", { paths: IMPORTS_PROHIBIDOS }],
    },
  },
  {
    files: HEX_PERMITIDO.filter((f) => f.endsWith(".ts")),
    rules: {
      "no-restricted-syntax": ["error", ...SINTAXIS_TS],
    },
  },
  {
    files: HEX_PERMITIDO.filter((f) => f.endsWith(".tsx")),
    rules: {
      "no-restricted-syntax": ["error", ...SINTAXIS_TS, ...SINTAXIS_TSX],
    },
  },
]);

export default eslintConfig;
