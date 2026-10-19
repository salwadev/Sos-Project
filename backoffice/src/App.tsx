import { HydraAdmin } from '@api-platform/admin'

// Point d'entrée Hydra de l'API Symfony — définir VITE_API_ENTRYPOINT dans .env.local (voir .env.example).
const entrypoint = import.meta.env.VITE_API_ENTRYPOINT ?? 'http://localhost:8000/api'

function App() {
  return <HydraAdmin entrypoint={entrypoint} title="SOS — Back-office" />
}

export default App
