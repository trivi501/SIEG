import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);

    // Mensajes clásicos de Laravel: redirect()->with('success'|'error', ...), compartidos como props.flash.
    useEffect(() => {
        return router.on('success', (event) => {
            const flash = (event as CustomEvent).detail?.page?.props?.flash as
                | { success?: string | null; error?: string | null }
                | undefined;

            if (flash?.success) {
                toast.success(flash.success);
            }

            if (flash?.error) {
                toast.error(flash.error);
            }
        });
    }, []);
}
