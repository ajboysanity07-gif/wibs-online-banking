import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
};

export function LoanRequestSectionCard({
    title,
    description,
    children,
}: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-6">{children}</CardContent>
        </Card>
    );
}
