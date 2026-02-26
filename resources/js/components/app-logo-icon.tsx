import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    alt = 'MRDINC Portal',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img {...props} src="/mrdinc-logo-mark.png" alt={alt} />
    );
}
