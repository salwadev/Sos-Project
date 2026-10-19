import { Platform } from 'react-native';

/**
 * URL de l'API Symfony, préfixe /api compris — définir EXPO_PUBLIC_API_URL dans .env.local (voir .env.example).
 * Par défaut : localhost, sauf sur l'émulateur Android où la machine hôte est joignable en 10.0.2.2.
 */
export const API_URL =
  process.env.EXPO_PUBLIC_API_URL ?? (Platform.OS === 'android' ? 'http://10.0.2.2:8000/api' : 'http://localhost:8000/api');

/** Intervalle de rafraîchissement des écrans de suivi, en attendant les notifications push. */
export const POLL_INTERVAL_MS = 5000;

/**
 * Numéros d'urgence publics (cahier des charges §10.2).
 * À revérifier auprès des sources officielles avant chaque publication.
 */
export const EMERGENCY_NUMBERS = {
  samu: '190',
} as const;
