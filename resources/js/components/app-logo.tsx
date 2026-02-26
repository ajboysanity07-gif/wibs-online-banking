import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="h-8 w-auto object-contain" />
            <span className="text-sm font-semibold leading-tight">
                MRDINC Portal
            </span>
        </>
    );
}
