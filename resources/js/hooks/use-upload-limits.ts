import { usePage } from '@inertiajs/react';
import type { UploadLimits } from '@/lib/uploads';
import type { SharedProps } from '@/types/cms';

/** The upload limits the server enforces, shared with every page. */
export function useUploadLimits(): UploadLimits {
    return usePage<SharedProps>().props.uploads;
}
