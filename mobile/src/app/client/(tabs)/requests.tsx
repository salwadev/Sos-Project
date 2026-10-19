import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { catalogApi, interventionsApi, requestsApi } from '@/api/endpoints';
import type { Intervention, Service, ServiceRequest } from '@/api/types';
import { ErrorNotice, LoadingView } from '@/components/error-notice';
import { Screen } from '@/components/screen';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { IconTile } from '@/components/ui/icon-tile';
import { findService, serviceIcon, serviceName } from '@/constants/catalog';
import { POLL_INTERVAL_MS } from '@/constants/config';
import { Colors, Spacing, Typography } from '@/constants/theme';
import { useApiQuery } from '@/hooks/use-api-query';
import { useI18n } from '@/i18n/i18n-context';
import { formatDate, formatMillimes } from '@/utils/format';
import { clientRequestHref, interventionFor, isActiveRequest, requestStatusTone } from '@/utils/request-routing';

export default function ClientRequests() {
  const { t } = useI18n();
  const requests = useApiQuery('requests', requestsApi.list, POLL_INTERVAL_MS);
  const interventions = useApiQuery('interventions', interventionsApi.list, POLL_INTERVAL_MS);
  const services = useApiQuery('services', catalogApi.services);

  const active = requests.data?.filter(isActiveRequest) ?? [];
  const history = requests.data?.filter((r) => !isActiveRequest(r)) ?? [];

  const row = (request: ServiceRequest) => (
    <RequestRow
      key={request.id}
      request={request}
      service={findService(services.data, request.service)}
      intervention={interventionFor(interventions.data, request.id)}
    />
  );

  return (
    <Screen>
      <Text style={Typography.title}>{t.requests.title}</Text>
      <ErrorNotice error={requests.error} onRetry={requests.refresh} />
      {requests.loading ? <LoadingView /> : null}

      {active.length > 0 ? (
        <>
          <Text style={styles.section}>{t.requests.inProgress}</Text>
          {active.map(row)}
        </>
      ) : null}

      <Text style={styles.section}>{t.requests.history}</Text>
      {requests.data && history.length === 0 ? <Text style={Typography.caption}>{t.requests.empty}</Text> : null}
      {history.map(row)}
    </Screen>
  );
}

function RequestRow({
  request,
  service,
  intervention,
}: {
  request: ServiceRequest;
  service?: Service;
  intervention?: Intervention;
}) {
  const { t, rtl, language } = useI18n();
  const total = intervention?.acceptedQuote?.totalMillimes;
  const meta = [`#${request.reference}`, formatDate(request.createdAt), total !== undefined ? formatMillimes(total) : null];

  return (
    <Card onPress={() => router.push(clientRequestHref(request, intervention))}>
      <View style={styles.row}>
        <IconTile icon={serviceIcon(service?.code)} color={Colors.navy} background={Colors.primarySoft} />
        <View style={styles.body}>
          <Text style={Typography.bodyStrong} numberOfLines={1}>
            {service ? serviceName(service, language) : request.reference}
          </Text>
          <Text style={Typography.caption} numberOfLines={1}>
            {meta.filter(Boolean).join(' · ')}
          </Text>
        </View>
        <Badge label={t.requestStatus[request.status]} tone={requestStatusTone(request.status)} />
        <Ionicons name={rtl ? 'chevron-back' : 'chevron-forward'} size={18} color={Colors.textSecondary} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  section: { ...Typography.caption, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm + 4 },
  body: { flex: 1, gap: 2 },
});
