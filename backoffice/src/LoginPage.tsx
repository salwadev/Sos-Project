import { useState } from 'react'
import type { FormEvent } from 'react'
import { Alert, Box, Button, Card, CardContent, Stack, TextField, Typography } from '@mui/material'
import { useLogin } from 'react-admin'

import { requestOtp } from './auth.ts'

/** Connexion par OTP (§5.1) : numéro → code reçu par SMS → JWT. Réservée aux rôles admin et support. */
export function LoginPage() {
  const login = useLogin()
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [step, setStep] = useState<'phone' | 'code'>('phone')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const run = async (action: () => Promise<void>) => {
    setBusy(true)
    setError(null)
    try {
      await action()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erreur inattendue.')
    } finally {
      setBusy(false)
    }
  }

  const sendCode = (event: FormEvent) => {
    event.preventDefault()
    void run(async () => {
      await requestOtp(phone.trim())
      setCode('')
      setStep('code')
    })
  }

  const verify = (event: FormEvent) => {
    event.preventDefault()
    void run(() => login({ phone: phone.trim(), code: code.trim() }))
  }

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', bgcolor: 'background.default', p: 2 }}>
      <Card sx={{ width: '100%', maxWidth: 380 }}>
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="h5" component="h1">SOS — Back-office</Typography>
            {step === 'phone' ? (
              <Stack component="form" spacing={2} onSubmit={sendCode}>
                <TextField
                  label="Numéro de téléphone"
                  placeholder="+216 50 000 001"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  autoComplete="tel"
                  autoFocus
                  required
                />
                <Button type="submit" variant="contained" disabled={busy || phone.trim() === ''}>
                  Recevoir un code
                </Button>
              </Stack>
            ) : (
              <Stack component="form" spacing={2} onSubmit={verify}>
                <Typography variant="body2">
                  Code envoyé au {phone}. En développement, il est écrit dans <code>backend/var/sms_outbox.log</code>.
                </Typography>
                <TextField
                  label="Code à 6 chiffres"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  autoComplete="one-time-code"
                  slotProps={{ htmlInput: { inputMode: 'numeric', pattern: '\\d{6}' } }}
                  autoFocus
                  required
                />
                <Button type="submit" variant="contained" disabled={busy || code.length !== 6}>
                  Se connecter
                </Button>
                <Button onClick={() => setStep('phone')} disabled={busy}>
                  Changer de numéro
                </Button>
              </Stack>
            )}
            {error && <Alert severity="error">{error}</Alert>}
          </Stack>
        </CardContent>
      </Card>
    </Box>
  )
}
