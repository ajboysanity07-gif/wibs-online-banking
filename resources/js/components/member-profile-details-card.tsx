import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type MemberProfileDetailItem = {
    label: string;
    value: ReactNode;
};

type MemberProfileDetailsCardProps = {
    title: string;
    description?: string;
    items: MemberProfileDetailItem[];
};

export function MemberProfileDetailsCard({
    title,
    description,
    items,
}: MemberProfileDetailsCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
                {items.map((item) => (
                    <div key={item.label}>
                        <p className="text-xs text-muted-foreground">
                            {item.label}
                        </p>
                        <p className="text-sm font-medium">{item.value}</p>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
