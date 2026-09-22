/*
 * Locale et fuseau fixes : sans cela le rendu serveur et le rendu navigateur
 * peuvent differer et Vue signale une divergence d'hydratation.
 */
const dateCourte = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Paris',
})

export function formaterDate(iso: string) {
  return dateCourte.format(new Date(iso))
}

export function formaterTaille(octets: number) {
  if (octets < 1024) return `${octets} o`
  if (octets < 1024 * 1024) return `${Math.round(octets / 1024)} ko`
  return `${(octets / (1024 * 1024)).toFixed(1).replace('.', ',')} Mo`
}
