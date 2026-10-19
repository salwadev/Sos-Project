/** Montant en dinars tunisiens, 3 décimales (millimes) : 85 → « 85,000 TND ». */
export function formatTND(amount: number): string {
  const [int, dec] = amount.toFixed(3).split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return `${grouped},${dec} TND`;
}

/** Montant de l'API (millimes) : 85000 → « 85,000 TND ». */
export function formatMillimes(millimes: number): string {
  return formatTND(millimes / 1000);
}

/** « XX XXX XXX » pour un numéro tunisien à 8 chiffres (saisie partielle acceptée). */
export function formatPhone(digits: string): string {
  return [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 8)].filter(Boolean).join(' ');
}

/** « +21650000002 » → « +216 50 000 002 ». */
export function formatE164(phone: string): string {
  return phone.startsWith('+216') ? `+216 ${formatPhone(phone.slice(4))}` : phone;
}

/** Secondes → « mm:ss ». */
export function formatCountdown(totalSeconds: number): string {
  const s = Math.max(0, totalSeconds);
  const mm = String(Math.floor(s / 60)).padStart(2, '0');
  const ss = String(s % 60).padStart(2, '0');
  return `${mm}:${ss}`;
}

const pad = (n: number) => String(n).padStart(2, '0');

/** Heure locale « HH:mm » d'une date ISO de l'API (UTC). */
export function formatTime(iso: string | null | undefined): string {
  if (!iso) {
    return '--:--';
  }
  const date = new Date(iso);
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Date locale « JJ/MM/AAAA ». */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

/** Minutes restantes avant une heure estimée, arrondies au supérieur (0 si dépassée). */
export function minutesUntil(iso: string | null): number | null {
  if (!iso) {
    return null;
  }
  return Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 60000));
}

/** Saisie « 12,5 » ou « 12.5 » en dinars → millimes ; null si invalide. */
export function parseTNDToMillimes(input: string): number | null {
  const value = Number(input.replace(',', '.').trim());
  return input.trim() !== '' && Number.isFinite(value) && value >= 0 ? Math.round(value * 1000) : null;
}
