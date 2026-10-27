import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { proApi } from '@/api/endpoints';
import type { DocumentType } from '@/api/types';
import { useAuth } from '@/auth/auth-context';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { LanguageSwitch } from '@/components/language-switch';
import { Screen } from '@/components/screen';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Colors, Radius, Spacing, Typography } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { formatDate, formatMillimes } from '@/utils/format';
import { canPickFile, pickFile } from '@/utils/pick-file';

const DOCUMENT_TYPES: DocumentType[] = ['identity', 'qualification', 'company_registration', 'insurance', 'other'];

export default function ProProfile() {
  const { t, rtl } = useI18n();
  const s = t.proSpace.profile;
  const auth = useAuth();
  const profile = useApiQuery('pro-profile', proApi.profile);
  const upload = useAction();
  const [docType, setDocType] = useState<DocumentType>('identity');
  const data = profile.data;

  const sendDocument = async () => {
    const file = await pickFile();
    if (!file) {
      return;
    }
    const uploaded = await upload.run(() => proApi.uploadDocument(file, docType));
    if (uploaded) {
      profile.refresh();
    }
  };

  const openClientSpace = async () => {
    await auth.setSpace('client');
    router.replace('/client');
  };

  return (
    <Screen>
      <Text style={Typography.title}>{s.title}</Text>
      {profile.loading ? <LoadingView /> : null}
      <ErrorNotice error={profile.error} onRetry={profile.refresh} />

      {data ? (
        <>
          <View style={styles.identity}>
            <Avatar name={data.displayName} size={64} verified={data.verificationStatus === 'verified'} />
            <View style={styles.grow}>
              <Text style={Typography.heading}>{data.displayName}</Text>
              <Text style={Typography.caption}>{data.services.map((service) => service.serviceName).join(' · ')}</Text>
            </View>
          </View>

          <Card tone={data.verificationStatus === 'verified' ? 'primarySoft' : 'warning'}>
            <View style={styles.row}>
              <Ionicons
                name={data.verificationStatus === 'verified' ? 'shield-checkmark' : 'hourglass-outline'}
                size={26}
                color={data.verificationStatus === 'verified' ? Colors.primary : Colors.warning}
              />
              <View style={styles.grow}>
                <Text style={Typography.bodyStrong}>{t.proSpace.verification[data.verificationStatus]}</Text>
                <Text style={Typography.caption}>
                  {data.verificationNote ?? (data.verifiedAt ? formatDate(data.verifiedAt) : t.proSpace.verification.hint)}
                </Text>
              </View>
            </View>
          </Card>

          <Card>
            <Text style={Typography.bodyStrong}>{s.services}</Text>
            {data.services.map((service) => (
              <View key={service.serviceCode} style={styles.serviceRow}>
                <Text style={[Typography.body, styles.grow]}>{service.serviceName}</Text>
                <Text style={Typography.caption}>{formatMillimes(service.travelFeeMillimes)}</Text>
              </View>
            ))}
          </Card>

          <Card>
            <Text style={Typography.bodyStrong}>{s.zones}</Text>
            <View style={styles.chips}>
              {data.serviceAreas.map((zone) => (
                <View key={zone.code} style={styles.chip}>
                  <Ionicons name="location-outline" size={14} color={Colors.primaryDark} />
                  <Text style={styles.chipText}>{zone.name}</Text>
                </View>
              ))}
            </View>
          </Card>

          <Card>
            <Text style={Typography.bodyStrong}>{s.documents}</Text>
            {data.documents.length === 0 ? <Text style={Typography.caption}>{s.noDocuments}</Text> : null}
            {data.documents.map((doc) => (
              <View key={doc.id} style={styles.docRow}>
                <Ionicons name="document-attach-outline" size={20} color={Colors.textSecondary} />
                <View style={styles.grow}>
                  <Text style={Typography.body}>{s.documentTypes[doc.type]}</Text>
                  <Text style={Typography.small}>
                    {formatDate(doc.uploadedAt)}
                    {doc.rejectionReason ? ` · ${doc.rejectionReason}` : ''}
                  </Text>
                </View>
                <Badge label={s.documentStatus[doc.status]} tone={doc.status === 'approved' ? 'success' : 'warning'} />
              </View>
            ))}

            {canPickFile ? (
              <View style={styles.upload}>
                <View style={styles.chips}>
                  {DOCUMENT_TYPES.map((type) => (
                    <Pressable
                      key={type}
                      accessibilityRole="radio"
                      accessibilityState={{ checked: type === docType }}
                      onPress={() => setDocType(type)}
                      style={[styles.typeChip, type === docType && styles.typeChipActive]}>
                      <Text style={styles.typeChipText}>{s.documentTypes[type]}</Text>
                    </Pressable>
                  ))}
                </View>
                <ErrorNotice error={upload.error} />
                <Button label={s.upload} variant="outline" icon="cloud-upload-outline" loading={upload.pending} onPress={sendDocument} />
              </View>
            ) : (
              <Text style={[Typography.small, styles.uploadHint]}>{s.uploadNative}</Text>
            )}
          </Card>
        </>
      ) : null}

      <Card>
        <View style={styles.row}>
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.language}</Text>
          <LanguageSwitch />
        </View>
      </Card>
      <Card onPress={openClientSpace}>
        <View style={styles.row}>
          <Ionicons name="person-outline" size={22} color={Colors.primary} />
          <Text style={[Typography.bodyStrong, styles.grow]}>{s.clientSpace}</Text>
          <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
        </View>
      </Card>

      <Button label={s.logout} variant="outline" icon="log-out-outline" onPress={auth.signOut} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  identity: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  grow: { flex: 1 },
  serviceRow: { flexDirection: 'row', alignItems: 'center', marginTop: Spacing.sm },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm, marginTop: Spacing.sm },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: Colors.primarySoft,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 5,
  },
  chipText: { fontSize: 13, color: Colors.primaryDark, fontWeight: '600' },
  docRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, marginTop: Spacing.sm },
  upload: { gap: Spacing.sm, marginTop: Spacing.sm },
  uploadHint: { marginTop: Spacing.sm },
  typeChip: {
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.pill,
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 4,
  },
  typeChipActive: { borderColor: Colors.primary, backgroundColor: Colors.primarySoft },
  typeChipText: { fontSize: 12, color: Colors.text, fontWeight: '600' },
});
