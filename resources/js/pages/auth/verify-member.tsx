import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { verify } from '@/routes/register';

export default function VerifyMember() {
    return (
        <AuthLayout
            title="Verify your membership"
            description="Enter your account details to continue"
        >
            <Head title="Verify membership" />
            <Form {...verify.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
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
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="middle_initial">
                                    Middle initial (optional)
                                </Label>
                                <Input
                                    id="middle_initial"
                                    type="text"
                                    tabIndex={4}
                                    autoComplete="additional-name"
                                    name="middle_initial"
                                    placeholder="M"
                                    maxLength={5}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Enter your name as it appears on record
                                    (LASTNAME, FIRSTNAME, MIDDLE INITIAL).
                                </p>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
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
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
