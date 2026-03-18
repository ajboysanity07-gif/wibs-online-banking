import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type MemberAccountAlertProps = {
    title: string;
    description: string;
};

export function MemberAccountAlert({
    title,
    description,
}: MemberAccountAlertProps) {
    return (
        <Alert>
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>{description}</AlertDescription>
        </Alert>
    );
}
