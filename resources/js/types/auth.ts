export type User = {
    id: number;
    name: string;
    username?: string;
    email: string;
    phoneno?: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isAdmin: boolean;
    isSuperadmin: boolean;
    hasMemberAccess: boolean;
    isAdminOnly: boolean;
    isHybrid: boolean;
    experience?: 'superadmin' | 'user' | 'user-admin' | 'admin-only';
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
