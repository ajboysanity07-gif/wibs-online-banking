import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import api, { mapValidationErrors } from '@/lib/api';
import { login } from '@/routes';

export default function VerifyMember() {
    const [formData, setFormData] = useState({
        accntno: '',
        last_name: '',
        first_name: '',
        middle_initial: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await api.post('/spa/member/verify', formData);
            const redirectTo = response.data?.redirect_to;

            if (redirectTo) {
                router.visit(redirectTo);
            }
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                setErrors(mapValidationErrors(error.response.data?.errors));
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <AuthLayout
            title="Verify your membership"
            description="Enter your account details to continue"
        >
            <Head title="Verify membership" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <InputError
                        message={errors.verification}
                        className="text-sm"
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="accntno">Account number</Label>
                        <Input
                            id="accntno"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="off"
                            inputMode="numeric"
                            name="accntno"
                            placeholder="Account number"
                            value={formData.accntno}
                            onChange={(event) =>
                                setFormData((current) => ({
                                    ...current,
                                    accntno: event.target.value,
                                }))
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            type="text"
                            required
                            tabIndex={2}
                            autoComplete="family-name"
                            name="last_name"
                            placeholder="Last name"
                            value={formData.last_name}
                            onChange={(event) =>
                                setFormData((current) => ({
                                    ...current,
                                    last_name: event.target.value,
                                }))
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="first_name">First name</Label>
                        <Input
                            id="first_name"
                            type="text"
                            required
                            tabIndex={3}
                            autoComplete="given-name"
                            name="first_name"
                            placeholder="First name"
                            value={formData.first_name}
                            onChange={(event) =>
                                setFormData((current) => ({
                                    ...current,
                                    first_name: event.target.value,
                                }))
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="middle_initial">
                            Middle name or initial (optional)
                        </Label>
                        <Input
                            id="middle_initial"
                            type="text"
                            tabIndex={4}
                            autoComplete="additional-name"
                            name="middle_initial"
                            placeholder="A or ANNA"
                            value={formData.middle_initial}
                            onChange={(event) =>
                                setFormData((current) => ({
                                    ...current,
                                    middle_initial: event.target.value,
                                }))
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Enter your name exactly as it appears on your
                            member record. You can use either a middle initial
                            or the full middle name.
                        </p>
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        tabIndex={5}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Verify membership
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink href={login()} tabIndex={6}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
