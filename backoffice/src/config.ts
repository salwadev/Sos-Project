// Point d'entrée Hydra de l'API Symfony — définir VITE_API_ENTRYPOINT dans .env.local (voir .env.example).
export const entrypoint = (import.meta.env.VITE_API_ENTRYPOINT ?? 'http://localhost:8000/api').replace(/\/+$/, '')
