# 08 · Datos y estado

## Quién guarda qué

| Tipo de estado | Herramienta | Ejemplo |
|---|---|---|
| Datos del servidor | TanStack Query v5 | listado de servidores, ficha FEMO |
| Estado global del cliente | Zustand v5 | sesión, sidebar plegado, buscador |
| Estado local de un componente | `useState` | modal abierto, pestaña activa |
| Estado de un formulario | React Hook Form | ver [07](07-formularios.md) |

**Nunca `useState` para datos del API.** Se pierde la caché, la
revalidación, el reintento y el estado de carga, y dos pantallas que muestran
lo mismo se desincronizan.

**Nunca Context API para estado global.** Está Zustand, y no re-renderiza el
árbol entero.

## Capas

```
componente → hook (useServidores)
                → servicio (servidorService)
                    → lib/axios
```

El componente no llama a `axios` ni conoce la URL. El servicio no sabe de
React. El hook une las dos cosas.

```ts
// features/expediente/services/servidorService.ts
import api from '@/lib/axios'
import type { Servidor, RespuestaPaginada } from '@/types/api'

export const servidorService = {
  listar: (params: FiltrosServidor) =>
    api.get<RespuestaPaginada<Servidor>>('/servidores', { params }).then((r) => r.data),

  crear: (datos: ServidorFormData) =>
    api.post<{ datos: Servidor }>('/servidores', datos).then((r) => r.data.datos),
}
```

```ts
// features/expediente/hooks/useServidores.ts
export function useServidores(filtros: FiltrosServidor) {
  return useQuery({
    queryKey: ['servidores', filtros],
    queryFn: () => servidorService.listar(filtros),
  })
}
```

**Siempre `axios` desde `@/lib/axios`**, nunca `fetch` nativo: ahí viven el
token, la URL base y el manejo del 401.

## Claves de consulta

Jerárquicas, del recurso a lo particular. Todo lo que cambie el resultado va en
la clave, o la caché devuelve datos de otro filtro:

```ts
['servidores']                          // todos
['servidores', filtros]                 // listado filtrado
['servidores', id]                      // uno
['servidores', id, 'documentos']        // relación
```

## Mutaciones

Una mutación **siempre** invalida lo que dejó obsoleto y **siempre** avisa al
usuario del resultado:

```ts
export function useServidorMutations() {
  const qc = useQueryClient()

  const crear = useMutation({
    mutationFn: servidorService.crear,
    onSuccess: (servidor) => {
      qc.invalidateQueries({ queryKey: ['servidores'] })
      notifications.show({
        color: 'emerald',
        title: 'Servidor creado',
        message: `${servidor.nombre_completo} quedó registrado.`,
      })
    },
  })

  return { crear }
}
```

## Notificaciones

Se usan para el **resultado de una acción del usuario**, no para informar de
una carga.

```
color="emerald"   la acción se completó
color="red"       la acción falló
color="amber"     se completó con reparos
```

El mensaje dice qué pasó con qué registro: "Servidor creado" es mejor que
"Operación exitosa", y "No se pudo anular el viático V-2026-041" es mucho mejor
que "Error".

Un error que el usuario puede corregir en un campo va **al campo**, no a una
notificación (ver [07](07-formularios.md)).

## Autenticación

```
1. POST /auth/login → { token, primer_login, usuario }
2. El token se guarda en localStorage + cookie sgth_token + Zustand
3. primer_login === true → /cambiar-password
4. clearAuth() borra localStorage y las cookies sgth_token y sgth_primer_login
```

`clearAuth()` que no borre las cookies deja al usuario a medio salir: el store
está vacío pero la cookie sigue autenticando peticiones.

Al cerrar sesión se recarga la página a propósito. Es la forma de descartar la
caché de TanStack Query, que guarda datos del servidor del usuario que sale.

## Efectos

Un efecto sincroniza React con algo de fuera. Llamar a `setState` dentro del
cuerpo de un efecto encadena renders y ESLint lo marca como error.

- ¿Es un valor externo que quieres observar? → `useSyncExternalStore`
  (así lee `useHydrated` la rehidratación del store).
- ¿Es estado que hay que ajustar cuando cambia una prop? → ajústalo **durante
  el render**, como hace `NavItemNested` para desplegar el submenú activo.
- ¿Es una reacción a algo que hizo el usuario? → va en el manejador del evento,
  no en un efecto.
