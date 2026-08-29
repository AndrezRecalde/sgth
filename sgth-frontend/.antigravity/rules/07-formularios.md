# 07 · Formularios

## React Hook Form + Zod, sin excepciones

Es el estándar. `@mantine/form` sigue instalado pero no lo usa ninguna
pantalla; no lo reintroduzcas.

Zod se importa **siempre** desde `zod/v4`. Importar desde `zod` a secas trae la
API v3 y los mensajes de error salen distintos.

## El esquema va en su propio archivo

```ts
// features/expediente/schemas/servidor.schema.ts
import { z } from 'zod/v4'

export const servidorSchema = z.object({
  nombres: z.string().min(2, 'Mínimo 2 caracteres'),
  cedula: z.string().length(10, 'Debe tener 10 dígitos'),
  genero: z.enum(['masculino', 'femenino', 'otro']),
  unidad_administrativa_id: z.number({ message: 'Selecciona una unidad' }),
})

export type ServidorFormData = z.infer<typeof servidorSchema>
```

El tipo del formulario **se infiere del esquema**. No se declara por separado:
dos declaraciones se separan en cuanto alguien toca una sola.

## Estructura

```tsx
const contained = useContainedInput()

const {
  register,
  control,
  handleSubmit,
  formState: { errors, isSubmitting },
} = useForm<ServidorFormData>({
  resolver: zodResolver(servidorSchema),
  defaultValues: { nombres: '', cedula: '', genero: 'masculino' },
})

return (
  <FormModal
    opened={opened}
    onClose={cerrar}
    title="Nuevo servidor"
    onSubmit={handleSubmit(guardar)}
    submitting={isSubmitting}
  >
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Nombres"
          placeholder="Primer y segundo nombre"
          error={errors.nombres?.message}
          {...contained}
          {...register('nombres')}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="genero"
          control={control}
          render={({ field }) => (
            <Select
              label="Género"
              data={OPCIONES_GENERO}
              error={errors.genero?.message}
              {...contained}
              value={field.value}
              onChange={field.onChange}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  </FormModal>
)
```

`defaultValues` **siempre**. Sin ellos el campo arranca no controlado y React
avisa en consola la primera vez que se escribe.

## `register` o `Controller`

```
register     TextInput, PasswordInput, Textarea, NumberInput
             — los que envuelven un input HTML nativo

Controller   Select, MultiSelect, DatePickerInput, Switch, Checkbox,
             Radio, FileInput, Dropzone, editor de texto enriquecido
             — cualquier componente de Mantine que NO sea un input nativo
```

Usar `register` en un `Select` compila pero no guarda el valor: el componente
no emite un evento `change` nativo.

## Etiqueta obligatoria

**Todo campo lleva `label` visible.** El marcador de posición no es una
etiqueta: desaparece al escribir, y quien vuelve a revisar el formulario ya no
sabe qué hay en cada casilla.

```tsx
<TextInput label="Número de extensión" placeholder="Ej: 1234" />   // sí
<TextInput placeholder="Número de extensión" />                    // no
```

El marcador de posición sirve para dar un **ejemplo de formato**, no para
repetir la etiqueta.

## Patrón contained

La etiqueta va dentro del control. Es la firma visual de los formularios del
SGTH: 509 campos en 136 archivos.

```tsx
const contained = useContainedInput()        // 48px — formulario de captura
const compacto  = useContainedInput('sm')    // 40px — barras de filtros
```

Se aplica esparciéndolo, **antes** de `register` para que este último gane si
hay colisión de props. Nunca se importa el CSS Module directamente.

### Un campo contained no lleva `leftSection`

La etiqueta vive DENTRO del control, en la franja superior izquierda, que es
exactamente donde Mantine dibuja el icono de `leftSection`. Se montan uno sobre
otro, y el resultado es una lupa encima del texto de la etiqueta.

Si el campo necesita señalar que es un buscador, dilo en el marcador de
posición («Buscar código o descripción…») o pon el icono en el botón contiguo,
que sí puede llevarlo.

Lo mismo vale para las flechas de `NumberInput`: se solapan con etiquetas
largas. En campos estrechos va `hideControls` y el número se teclea.

Y para las etiquetas en general: una etiqueta más larga que su campo se sale.
Si no cabe, el campo necesita más ancho o la etiqueta menos palabras —
«Consumo (meses)» en vez de «Tiempo de consumo (meses)».

## Rejilla

```tsx
<Grid.Col span={{ base: 12, sm: 6 }}>    // dos columnas en escritorio, una en móvil
<Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
<Grid.Col span={12}>                     // campos anchos: observaciones, direcciones
```

`base: 12` siempre presente: en un teléfono nada va a dos columnas.

Ojo: en Mantine v9 la separación de `Grid` es `gap`, no `gutter`.

## Errores del servidor

La validación de Zod cubre el formato. Las reglas de negocio las valida el
backend, y su respuesta se devuelve al campo que corresponde:

```ts
catch (error) {
  if (esErrorDeValidacion(error)) {
    for (const [campo, mensajes] of Object.entries(error.response.data.errors)) {
      setError(campo as keyof ServidorFormData, { message: mensajes[0] })
    }
    return
  }
  notifications.show({ color: 'red', message: 'No se pudo guardar el servidor' })
}
```

Un error de validación del backend que solo aparece como notificación deja al
usuario buscando cuál de los veinte campos está mal.

## Formularios largos

La ficha FEMO y el expediente tienen decenas de campos. Se parten en secciones
con `SectionCard`, o en pasos con `Stepper` cuando hay un orden obligatorio. No
se entrega un muro de cuarenta campos seguidos.
