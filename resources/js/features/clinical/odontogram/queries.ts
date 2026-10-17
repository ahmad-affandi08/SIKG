import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { fetchOdontogram, saveCondition } from '@/features/clinical/odontogram/api';
import type { OdontogramData } from '@/features/clinical/odontogram/api';
import { applyCondition } from '@/features/clinical/odontogram/model';
import type { TargetSurface } from '@/features/clinical/odontogram/model';
import { STALE_TIME } from '@/lib/queryClient';

export const odontogramKeys = {
  patient: (patientId: string, encounterId: string) =>
    ['clinical', 'odontogram', patientId, encounterId] as const,
};

export function useOdontogram(patientId: string, encounterId: string) {
  return useQuery({
    queryKey: odontogramKeys.patient(patientId, encounterId),
    queryFn: ({ signal }) => fetchOdontogram(patientId, encounterId, signal),
    staleTime: STALE_TIME.default,
  });
}

interface SetConditionInput {
  fdi: number;
  surface: TargetSurface;
  conditionCode: string;
}

/** Simpan per gigi dengan pembaruan optimistis; bila server menolak, tampilan kembali ke keadaan sebelumnya. */
export function useSetCondition(patientId: string, encounterId: string) {
  const client = useQueryClient();
  const key = odontogramKeys.patient(patientId, encounterId);

  return useMutation({
    mutationFn: (input: SetConditionInput) =>
      saveCondition(encounterId, input.fdi, {
        surface: input.surface,
        condition_code: input.conditionCode,
      }),
    onMutate: async (input) => {
      await client.cancelQueries({ queryKey: key });
      const previous = client.getQueryData<OdontogramData>(key);
      if (previous) {
        client.setQueryData<OdontogramData>(
          key,
          applyCondition(
            previous,
            input.fdi,
            input.surface,
            input.conditionCode,
            new Date().toISOString(),
            encounterId,
          ),
        );
      }
      return { previous };
    },
    onError: (_error, _input, context) => {
      if (context?.previous) {
        client.setQueryData(key, context.previous);
      }
    },
    onSettled: () => client.invalidateQueries({ queryKey: key }),
  });
}
