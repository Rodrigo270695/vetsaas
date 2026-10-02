import { useSidebar } from '@/components/ui/sidebar';
import { APP_VERSION } from '@/lib/app-version';

export function SidebarAppVersion() {
    const { state, isMobile } = useSidebar();
    const collapsed = !isMobile && state === 'collapsed';

    if (collapsed) {
        return null;
    }

    return (
        <p className="px-2 text-[10px] font-medium tracking-[0.18em] text-muted-foreground/80 uppercase">
            v{APP_VERSION}
        </p>
    );
}
