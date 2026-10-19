/** Montant en dinars tunisiens, 3 décimales (millimes) : 85 → « 85,000 TND ». */
export function formatTND(amount: number): string {
  const [int, dec] = amount.toFixed(3).split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return `${grouped},${dec} TND`;
}

/** « XX XXX XXX » pour un numéro tunisien à 8 chiffres (saisie partielle acceptée). */
export function formatPhone(digits: string): string {
  return [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 8)].filter(Boolean).join(' ');
}

/** Secondes → « mm:ss ». */
export function formatCountdown(totalSeconds: number): string {
  const s = Math.max(0, totalSeconds);
  const mm = String(Math.floor(s / 60)).padStart(2, '0');
  const ss = String(s % 60).padStart(2, '0');
  return `${mm}:${ss}`;
}
