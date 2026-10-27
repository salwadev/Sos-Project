import type { UploadFile } from '@/api/endpoints';

/**
 * Choix d'un justificatif sur mobile : nécessite expo-document-picker (pas encore installé).
 * En attendant, le dépôt se fait depuis la version web (pick-file.web.ts).
 */
export const canPickFile = false;

export async function pickFile(): Promise<UploadFile | null> {
  return null;
}
