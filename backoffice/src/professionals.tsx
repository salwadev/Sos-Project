import { useState } from 'react'
import type { FormEvent } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField as MuiTextField,
  Typography,
} from '@mui/material'
import {
  Datagrid,
  DateField,
  FunctionField,
  Labeled,
  List,
  SelectInput,
  Show,
  SimpleShowLayout,
  TextField,
  useDataProvider,
  useNotify,
  useRecordContext,
  useRefresh,
} from 'react-admin'

import type { BackofficeDataProvider, VerificationDecision } from './dataProvider.ts'

/** Ressource Hydra : chemin de la collection relatif au point d'entrée. */
export const PROFESSIONALS_RESOURCE = 'admin/professionals'

type VerificationStatus = 'pending' | 'verified' | 'rejected' | 'suspended'

interface ProfessionalDocument {
  id: string
  type: string
  status: string
  mimeType: string
  sizeBytes: number
  expiresAt: string | null
  uploadedAt: string
  reviewedAt: string | null
  rejectionReason: string | null
  fileUrl: string
}

/** Vue AdminProfessional (App\ApiResource\Professional\ProfessionalProfileView::forAdmin). id = IRI, originId = UUID. */
interface AdminProfessional {
  id: string
  originId: string
  displayName: string
  phone: string
  legalStatus: 'individual' | 'company'
  verificationStatus: VerificationStatus
  verificationNote: string | null
  verifiedAt: string | null
  services: Array<{ serviceCode: string; serviceName: string; travelFeeMillimes: number; diagnosticFeeMillimes: number | null }>
  serviceAreas: Array<{ code: string; name: string; active: boolean }>
  documents: ProfessionalDocument[]
  createdAt: string
}

const STATUS_LABELS: Record<VerificationStatus, string> = {
  pending: 'En attente',
  verified: 'Vérifié',
  rejected: 'Rejeté',
  suspended: 'Suspendu',
}
const STATUS_COLORS = { pending: 'warning', verified: 'success', rejected: 'error', suspended: 'default' } as const
const STATUS_CHOICES = Object.entries(STATUS_LABELS).map(([id, name]) => ({ id, name }))

const DOCUMENT_TYPES: Record<string, string> = {
  identity: 'Pièce d’identité',
  qualification: 'Qualification',
  company_registration: 'Registre du commerce',
  insurance: 'Assurance',
  other: 'Autre',
}

/** Actions possibles selon l'état du profil (App\Domain\Professional\ProfessionalVerifier). */
const ACTIONS: Record<VerificationStatus, Array<{ id: NonNullable<VerificationDecision['action']>; name: string }>> = {
  pending: [
    { id: 'approve', name: 'Approuver' },
    { id: 'reject', name: 'Rejeter' },
  ],
  rejected: [{ id: 'approve', name: 'Approuver' }],
  verified: [{ id: 'suspend', name: 'Suspendre' }],
  suspended: [{ id: 'reactivate', name: 'Réactiver' }],
}

/** Affiche le choix vide (« Inchangé », « Aucune ») au lieu d'un champ blanc sous le libellé. */
const EMPTY_SELECT_PROPS = { inputLabel: { shrink: true }, select: { displayEmpty: true } }

const tnd = (millimes: number) => `${(millimes / 1000).toFixed(3)} TND`

const StatusChip = ({ status }: { status: VerificationStatus }) => (
  <Chip size="small" label={STATUS_LABELS[status] ?? status} color={STATUS_COLORS[status] ?? 'default'} />
)

const listFilters = [
  <SelectInput key="verificationStatus" source="verificationStatus" label="Statut" choices={STATUS_CHOICES} alwaysOn emptyText="En attente (défaut)" />,
]

/** File de vérification : plus anciens d'abord, 30 par page côté API. */
export const ProfessionalList = () => (
  <List
    title="Vérification des professionnels"
    filters={listFilters}
    filterDefaultValues={{ verificationStatus: 'pending' }}
    perPage={30}
    pagination={false}
    exporter={false}
    sort={{ field: '', order: 'ASC' }}
  >
    <Datagrid rowClick="show" bulkActionButtons={false}>
      <TextField source="displayName" label="Nom affiché" sortable={false} />
      <TextField source="phone" label="Téléphone" sortable={false} />
      <FunctionField<AdminProfessional>
        label="Statut"
        sortable={false}
        render={(record) => <StatusChip status={record.verificationStatus} />}
      />
      <FunctionField<AdminProfessional>
        label="Métiers"
        sortable={false}
        render={(record) => record.services.map((s) => s.serviceName).join(', ')}
      />
      <FunctionField<AdminProfessional>
        label="Justificatifs"
        sortable={false}
        render={(record) => `${record.documents.filter((d) => d.status === 'pending').length} à examiner / ${record.documents.length}`}
      />
      <DateField source="createdAt" label="Inscrit le" showTime sortable={false} />
    </Datagrid>
  </List>
)

type DocumentChoice = { status: '' | 'approved' | 'rejected'; rejectionReason: string; expiresAt: string }

function DocumentsTable({ record, choices, onChange }: {
  record: AdminProfessional
  choices: Record<string, DocumentChoice>
  onChange: (id: string, choice: DocumentChoice) => void
}) {
  const dataProvider = useDataProvider<BackofficeDataProvider>()
  const notify = useNotify()

  if (record.documents.length === 0) {
    return <Typography variant="body2">Aucun justificatif déposé.</Typography>
  }

  // La fenêtre est ouverte pendant le clic (sinon le navigateur bloque la popup), puis remplie avec le fichier.
  const open = (document: ProfessionalDocument) => {
    const target = window.open('', '_blank')
    dataProvider
      .fetchDocument(document.fileUrl)
      .then(({ data }) => {
        const url = URL.createObjectURL(data)
        if (target) {
          target.location.href = url
        } else {
          // Popup refusée : téléchargement à la place.
          const link = window.document.createElement('a')
          link.href = url
          link.download = `justificatif-${document.type}`
          link.click()
        }
        setTimeout(() => URL.revokeObjectURL(url), 60_000)
      })
      .catch((e: Error) => {
        target?.close()
        notify(e.message, { type: 'error' })
      })
  }

  return (
    <Table size="small">
      <TableHead>
        <TableRow>
          <TableCell>Type</TableCell>
          <TableCell>Statut</TableCell>
          <TableCell>Expire le</TableCell>
          <TableCell>Fichier</TableCell>
          <TableCell>Décision</TableCell>
        </TableRow>
      </TableHead>
      <TableBody>
        {record.documents.map((document) => {
          const choice = choices[document.id] ?? { status: '', rejectionReason: '', expiresAt: '' }
          return (
            <TableRow key={document.id}>
              <TableCell>{DOCUMENT_TYPES[document.type] ?? document.type}</TableCell>
              <TableCell>
                {document.status}
                {document.rejectionReason ? ` — ${document.rejectionReason}` : ''}
              </TableCell>
              <TableCell>{document.expiresAt ?? '—'}</TableCell>
              <TableCell>
                <Button size="small" onClick={() => open(document)}>
                  Ouvrir ({document.mimeType.replace('application/', '').replace('image/', '')}, {Math.ceil(document.sizeBytes / 1024)} Kio)
                </Button>
              </TableCell>
              <TableCell>
                <Stack spacing={1} sx={{ minWidth: 220 }}>
                  <MuiTextField
                    select
                    size="small"
                    label="Décision"
                    slotProps={EMPTY_SELECT_PROPS}
                    value={choice.status}
                    onChange={(e) => onChange(document.id, { ...choice, status: e.target.value as DocumentChoice['status'] })}
                  >
                    <MenuItem value="">Inchangé</MenuItem>
                    <MenuItem value="approved">Approuver</MenuItem>
                    <MenuItem value="rejected">Rejeter</MenuItem>
                  </MuiTextField>
                  {choice.status === 'rejected' && (
                    <MuiTextField
                      size="small"
                      label="Motif du rejet"
                      required
                      value={choice.rejectionReason}
                      onChange={(e) => onChange(document.id, { ...choice, rejectionReason: e.target.value })}
                    />
                  )}
                  {choice.status === 'approved' && (
                    <MuiTextField
                      size="small"
                      type="date"
                      label="Expire le (facultatif)"
                      value={choice.expiresAt}
                      onChange={(e) => onChange(document.id, { ...choice, expiresAt: e.target.value })}
                      slotProps={{ inputLabel: { shrink: true } }}
                    />
                  )}
                </Stack>
              </TableCell>
            </TableRow>
          )
        })}
      </TableBody>
    </Table>
  )
}

/**
 * Décision de vérification (§7.2) : justificatifs traités d'abord, puis l'action sur le profil, en un seul PATCH.
 * Le motif est obligatoire et journalisé côté API.
 */
function VerificationPanel() {
  const record = useRecordContext<AdminProfessional>()
  const dataProvider = useDataProvider<BackofficeDataProvider>()
  const notify = useNotify()
  const refresh = useRefresh()
  const [action, setAction] = useState('')
  const [reason, setReason] = useState('')
  const [choices, setChoices] = useState<Record<string, DocumentChoice>>({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!record) {
    return null
  }

  const documents = Object.entries(choices)
    .filter(([, c]) => c.status !== '')
    .map(([id, c]) => ({
      id,
      status: c.status as 'approved' | 'rejected',
      ...(c.status === 'rejected' ? { rejectionReason: c.rejectionReason.trim() } : {}),
      ...(c.status === 'approved' && c.expiresAt ? { expiresAt: c.expiresAt } : {}),
    }))

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await dataProvider.reviewProfessional(record.id, {
        ...(action ? { action: action as VerificationDecision['action'] } : {}),
        reason: reason.trim(),
        ...(documents.length > 0 ? { documents } : {}),
      })
      notify('Décision enregistrée.', { type: 'success' })
      setAction('')
      setReason('')
      setChoices({})
      refresh()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erreur inattendue.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card variant="outlined" sx={{ mt: 2 }}>
      <CardContent>
        <Stack component="form" spacing={2} onSubmit={submit}>
          <Typography variant="h6">Justificatifs</Typography>
          <DocumentsTable record={record} choices={choices} onChange={(id, c) => setChoices({ ...choices, [id]: c })} />

          <Typography variant="h6">Décision</Typography>
          <Typography variant="body2" color="text.secondary">
            Approuver exige une pièce d’identité approuvée et valide (plus le registre du commerce pour une entreprise) ;
            elle peut être validée dans la même décision.
          </Typography>
          <MuiTextField
            select
            label="Action sur le profil"
            slotProps={EMPTY_SELECT_PROPS}
            value={action}
            onChange={(e) => setAction(e.target.value)}
            sx={{ maxWidth: 320 }}
          >
            <MenuItem value="">Aucune (justificatifs seulement)</MenuItem>
            {(ACTIONS[record.verificationStatus] ?? []).map((a) => (
              <MenuItem key={a.id} value={a.id}>{a.name}</MenuItem>
            ))}
          </MuiTextField>
          <MuiTextField label="Motif (obligatoire, journalisé)" value={reason} onChange={(e) => setReason(e.target.value)} required multiline minRows={2} />
          {error && <Alert severity="error">{error}</Alert>}
          <Box>
            <Button type="submit" variant="contained" disabled={busy || reason.trim() === '' || (action === '' && documents.length === 0)}>
              Enregistrer la décision
            </Button>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  )
}

export const ProfessionalShow = () => (
  <Show title="Professionnel">
    <SimpleShowLayout>
      <TextField source="displayName" label="Nom affiché" />
      <TextField source="phone" label="Téléphone" />
      <FunctionField<AdminProfessional>
        label="Statut juridique"
        render={(record) => (record.legalStatus === 'company' ? 'Entreprise' : 'Indépendant')}
      />
      <FunctionField<AdminProfessional> label="Vérification" render={(record) => <StatusChip status={record.verificationStatus} />} />
      <TextField source="verificationNote" label="Dernier motif" emptyText="—" />
      <DateField source="verifiedAt" label="Vérifié le" showTime emptyText="—" />
      <FunctionField<AdminProfessional>
        label="Métiers et frais de déplacement"
        render={(record) => record.services.map((s) => `${s.serviceName} (${tnd(s.travelFeeMillimes)})`).join(', ')}
      />
      <FunctionField<AdminProfessional>
        label="Zones couvertes"
        render={(record) => record.serviceAreas.map((a) => (a.active ? a.name : `${a.name} (pas encore ouverte)`)).join(', ')}
      />
      <DateField source="createdAt" label="Inscrit le" showTime />
      <Labeled label="Vérification" fullWidth>
        <VerificationPanel />
      </Labeled>
    </SimpleShowLayout>
  </Show>
)
