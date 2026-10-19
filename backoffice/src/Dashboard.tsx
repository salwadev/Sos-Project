import { Card, CardContent, Typography } from '@mui/material'
import { Title, useGetIdentity, usePermissions } from 'react-admin'

/** Accueil : rappelle le compte connecté et ce que son rôle permet ici. */
export function Dashboard() {
  const { identity } = useGetIdentity()
  const { permissions } = usePermissions<string[]>()
  const isAdmin = permissions?.includes('ROLE_ADMIN') ?? false

  return (
    <Card sx={{ mt: 2 }}>
      <Title title="SOS — Back-office" />
      <CardContent>
        <Typography variant="h6" gutterBottom>Connecté : {identity?.fullName ?? '…'}</Typography>
        <Typography variant="body2" gutterBottom>Rôles : {(permissions ?? []).join(', ')}</Typography>
        <Typography variant="body1">
          {isAdmin
            ? 'Menu « Vérification des professionnels » : file des inscriptions, justificatifs et décisions (approuver, rejeter, suspendre, réactiver).'
            : 'Le rôle support n’a pas encore d’écran : la vérification des professionnels est réservée à l’administration.'}
        </Typography>
      </CardContent>
    </Card>
  )
}
