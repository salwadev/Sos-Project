import { HydraAdmin } from '@api-platform/admin'
import { Resource } from 'react-admin'

import { authProvider } from './auth.ts'
import { entrypoint } from './config.ts'
import { Dashboard } from './Dashboard.tsx'
import { dataProvider } from './dataProvider.ts'
import { LoginPage } from './LoginPage.tsx'
import { PROFESSIONALS_RESOURCE, ProfessionalList, ProfessionalShow } from './professionals.tsx'

/**
 * Back-office réservé aux rôles admin et support (connexion OTP, JWT envoyé en Bearer).
 * Les ressources sont déclarées explicitement : seules celles prévues pour le back-office apparaissent.
 */
function App() {
  return (
    <HydraAdmin
      entrypoint={entrypoint}
      dataProvider={dataProvider}
      authProvider={authProvider}
      loginPage={LoginPage}
      dashboard={Dashboard}
      requireAuth
      disableTelemetry
      title="SOS — Back-office"
    >
      {(permissions: string[] | undefined) =>
        permissions?.includes('ROLE_ADMIN')
          ? [
              <Resource
                key={PROFESSIONALS_RESOURCE}
                name={PROFESSIONALS_RESOURCE}
                options={{ label: 'Vérification des professionnels' }}
                list={ProfessionalList}
                show={ProfessionalShow}
              />,
            ]
          : []
      }
    </HydraAdmin>
  )
}

export default App
