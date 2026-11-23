import { ShieldCheck } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <ShieldCheck className="size-5" aria-hidden="true" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-none font-semibold tracking-tight">CodeSentinel</span>
                <span className="text-muted-foreground mt-1 truncate font-mono text-[11px] leading-none">code review</span>
            </div>
        </>
    );
}
