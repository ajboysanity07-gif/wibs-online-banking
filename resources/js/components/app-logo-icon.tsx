import type { ImgHTMLAttributes } from 'react';
import { useBranding } from '@/hooks/use-branding';

export default function AppLogoIcon({
    alt,
    src,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    const branding = useBranding();
    const resolvedSrc = src ?? branding.logoUrl;
    const resolvedAlt = alt ?? branding.appTitle;

    return <img {...props} src={resolvedSrc} alt={resolvedAlt} />;
}
