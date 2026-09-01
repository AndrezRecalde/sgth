import { redirect } from 'next/navigation'

// Toda ficha FEMO nace de una solicitud de Talento Humano; sin un
// identificador de solicitud en la ruta no hay nada válido que mostrar.
export default function NuevaFemoSinSolicitudPage() {
  redirect('/salud/sso')
}
