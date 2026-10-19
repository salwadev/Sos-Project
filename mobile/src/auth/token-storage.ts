import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

// Jetons stockés dans le trousseau / keystore, jamais en AsyncStorage (cahier des charges §15.3).
const ACCESS_TOKEN_KEY = 'sos.accessToken';
const REFRESH_TOKEN_KEY = 'sos.refreshToken';
const SESSION_KEY = 'sos.session';

/** Informations non secrètes de la session, relues au démarrage pour choisir l'espace à ouvrir. */
export type StoredSession = {
  phone: string;
  roles: string[];
  space: 'client' | 'pro';
};

// expo-secure-store n'existe pas sur le web : la démo web garde la session en mémoire, le temps de l'onglet.
const memory = new Map<string, string>();

async function getItem(key: string): Promise<string | null> {
  return Platform.OS === 'web' ? (memory.get(key) ?? null) : SecureStore.getItemAsync(key);
}

async function setItem(key: string, value: string): Promise<void> {
  if (Platform.OS === 'web') {
    memory.set(key, value);
    return;
  }
  await SecureStore.setItemAsync(key, value);
}

async function deleteItem(key: string): Promise<void> {
  if (Platform.OS === 'web') {
    memory.delete(key);
    return;
  }
  await SecureStore.deleteItemAsync(key);
}

export async function getAccessToken(): Promise<string | null> {
  return getItem(ACCESS_TOKEN_KEY);
}

export async function getRefreshToken(): Promise<string | null> {
  return getItem(REFRESH_TOKEN_KEY);
}

export async function saveTokens(accessToken: string, refreshToken: string): Promise<void> {
  await setItem(ACCESS_TOKEN_KEY, accessToken);
  await setItem(REFRESH_TOKEN_KEY, refreshToken);
}

export async function getSession(): Promise<StoredSession | null> {
  const raw = await getItem(SESSION_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as StoredSession;
  } catch {
    return null;
  }
}

export async function saveSession(session: StoredSession): Promise<void> {
  await setItem(SESSION_KEY, JSON.stringify(session));
}

export async function clearTokens(): Promise<void> {
  await deleteItem(ACCESS_TOKEN_KEY);
  await deleteItem(REFRESH_TOKEN_KEY);
  await deleteItem(SESSION_KEY);
}
