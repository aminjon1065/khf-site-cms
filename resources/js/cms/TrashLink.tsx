import { Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import EditorialTrashController from '@/actions/App/Http/Controllers/Cms/EditorialTrashController';

/**
 * «Корзина (3)» beside a list's tabs, as in WordPress: deleted materials of
 * the section are found where people look for them. Nothing when empty.
 */
export function TrashLink({ type, count }: { type: string; count: number }) {
    if (count <= 0) {
        return null;
    }

    return (
        <Link href={EditorialTrashController.index.url({ query: { type } })}>
            <Trash2 size={14} strokeWidth={1.5} aria-hidden />
            Корзина ({count})
        </Link>
    );
}
