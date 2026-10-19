/** URL de l'API Symfony — définir EXPO_PUBLIC_API_URL dans .env.local (voir .env.example). */
export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api';

/**
 * Numéros d'urgence publics (cahier des charges §10.2).
 * À revérifier auprès des sources officielles avant chaque publication.
 */
export const EMERGENCY_NUMBERS = {
  samu: '190',
} as const;
