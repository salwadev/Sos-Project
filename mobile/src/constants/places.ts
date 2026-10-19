/**
 * Quartiers proposés pour situer une demande ou le point de départ d'un professionnel,
 * en attendant la géolocalisation de l'appareil (expo-location) et une vraie carte (§20).
 * Coordonnées approximatives, à l'intérieur des zones chargées par app:seed-catalog.
 */
export type Place = { code: string; fr: string; ar: string; latitude: number; longitude: number };

export const PLACES: Place[] = [
  { code: 'la_marsa', fr: 'La Marsa', ar: 'المرسى', latitude: 36.88, longitude: 10.33 },
  { code: 'sidi_bou_said', fr: 'Sidi Bou Saïd', ar: 'سيدي بوسعيد', latitude: 36.869, longitude: 10.341 },
  { code: 'carthage', fr: 'Carthage', ar: 'قرطاج', latitude: 36.852, longitude: 10.33 },
  { code: 'ariana', fr: 'Ariana', ar: 'أريانة', latitude: 36.875, longitude: 10.185 },
  { code: 'tunis_centre', fr: 'Tunis Centre', ar: 'وسط تونس', latitude: 36.8, longitude: 10.18 },
  { code: 'le_bardo', fr: 'Le Bardo', ar: 'باردو', latitude: 36.81, longitude: 10.13 },
  { code: 'ben_arous', fr: 'Ben Arous', ar: 'بن عروس', latitude: 36.75, longitude: 10.23 },
];

export const DEFAULT_PLACE = PLACES[0];
