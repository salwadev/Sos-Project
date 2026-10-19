import * as SecureStore from 'expo-secure-store';

// Jetons stockés dans le trousseau / keystore, jamais en AsyncStorage (cahier des charges §15.3).
const ACCESS_TOKEN_KEY = 'sos.accessToken';
const REFRESH_TOKEN_KEY = 'sos.refreshToken';

export async function getAccessToken(): Promise<string | null> {
  return SecureStore.getItemAsync(ACCESS_TOKEN_KEY);
}

export async function saveTokens(accessToken: string, refreshToken: string): Promise<void> {
  await SecureStore.setItemAsync(ACCESS_TOKEN_KEY, accessToken);
  await SecureStore.setItemAsync(REFRESH_TOKEN_KEY, refreshToken);
}

export async function clearTokens(): Promise<void> {
  await SecureStore.deleteItemAsync(ACCESS_TOKEN_KEY);
  await SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY);
}
