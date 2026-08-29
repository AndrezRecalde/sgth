# 10 · Verificación antes de cerrar

## Automático — esto se ejecuta, no se estima

```bash
npx tsc --noEmit
npx eslint src --ext .ts,.tsx
```

Cero errores de tipos. Cero errores de ESLint **nuevos**: al 27 de agosto de
2026 el repositorio arrastra 12 errores previos (`set-state-in-effect`,
`no-unescaped-entities`, un `no-explicit-any`) y 79 avisos de variables sin
usar. No agregues ninguno; si tocas un archivo que tiene uno, arréglalo.

## Estructura

- [ ] ¿El archivo está dentro de su límite de tamaño? ([02](02-arquitectura.md))
- [ ] ¿El componente hace una sola cosa?
- [ ] ¿La lógica está en un hook y no dentro del JSX?
- [ ] ¿Las columnas están en su `.columns.tsx`?
- [ ] ¿El esquema Zod está en su `.schema.ts`?
- [ ] ¿Lo transversal quedó en `components/ui` y lo del módulo en `features/`?

## Página

- [ ] ¿Exporta `metadata` con el nombre del módulo, sin repetir "GADPE"?
- [ ] ¿El `page.tsx` está libre de la directiva de cliente?
- [ ] ¿Está envuelta en `PageShell`?
- [ ] ¿La ruta está en `config/routes.ts` y, si va al menú, en `config/nav.ts`?
- [ ] ¿Resuelve los cuatro estados: cargando, error, vacío y con datos?

## Interfaz

- [ ] ¿Las tablas usan `SgthTable` y las acciones `TableActions`?
- [ ] ¿Los iconos son de Tabler? ¿Cero emojis?
- [ ] ¿Los estados usan `StatusBadge` con tono semántico?
- [ ] ¿Los colores salen de tokens o de props de Mantine? ¿Cero hex?
- [ ] ¿Se ve bien en modo oscuro? **Compruébalo, no lo supongas.**
- [ ] ¿Funciona a 375px de ancho? ¿`Grid.Col` con `base: 12`?
- [ ] ¿Los modales son de pantalla completa en móvil?

## Formularios

- [ ] ¿React Hook Form + `zodResolver`?
- [ ] ¿Zod importado desde `zod/v4`?
- [ ] ¿`defaultValues` presente?
- [ ] ¿Todos los campos con etiqueta visible y patrón contained?
- [ ] ¿`Controller` en los componentes que no son inputs nativos?
- [ ] ¿Los errores de validación del backend caen en su campo?

## Datos

- [ ] ¿Los datos del servidor vienen de TanStack Query, no de `useState`?
- [ ] ¿La clave de consulta incluye todo lo que cambia el resultado?
- [ ] ¿Las mutaciones invalidan lo que corresponde y notifican el resultado?
- [ ] ¿Las peticiones pasan por `@/lib/axios`?

## Limpieza

- [ ] ¿Sin `console.log` de depuración?
- [ ] ¿Sin `any` ni aserciones de tipo nuevas?
- [ ] ¿Sin imports sin usar?
- [ ] ¿Se borró lo que quedó muerto con este cambio?

## Verificación en el navegador

Un cambio visible se **mira** antes de darlo por terminado. Compilar no es
verificar.

```bash
npm run dev
```

Y se revisa: la pantalla en claro y en oscuro, a ancho de escritorio y de
teléfono, con datos y sin datos.
