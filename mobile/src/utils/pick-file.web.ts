import type { UploadFile } from '@/api/endpoints';

/** Version web : sélecteur de fichiers du navigateur, mêmes formats que l'API (PDF, JPEG, PNG). */
export const canPickFile = true;

export function pickFile(): Promise<UploadFile | null> {
  return new Promise((resolve) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/pdf,image/jpeg,image/png';
    input.onchange = () => resolve(input.files?.[0] ?? null);
    input.oncancel = () => resolve(null);
    input.click();
  });
}
