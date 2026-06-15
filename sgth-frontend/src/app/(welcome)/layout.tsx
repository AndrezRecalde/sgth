import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Bienvenido — GADPE',
}

export default function WelcomeLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <>{children}</>
}
