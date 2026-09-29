import { Form } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useRef, useState } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import {
    RowEditButton,
    SettingsRow,
} from '@/components/settings/settings-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminToastCopy, showSuccessToast } from '@/lib/toast';

export function PasswordRow() {
    const [open, setOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <SettingsRow
            label="Password"
            value={
                <p className="text-muted-foreground">
                    Ensure your account is using a long, random password to stay
                    secure.
                </p>
            }
            action={
                open ? null : (
                    <RowEditButton onClick={() => setOpen(true)}>
                        Change
                    </RowEditButton>
                )
            }
        >
            {open && (
                <Form
                    {...PasswordController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onSuccess={() => {
                        showSuccessToast(
                            adminToastCopy.success.updated('Password'),
                            { id: 'password-update' },
                        );
                        setOpen(false);
                    }}
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="max-w-md space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Current password
                                </Label>

                                <Input
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    type="password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>

                                <Input
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    type="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="size-4 animate-spin" />
                                            Saving...
                                        </>
                                    ) : (
                                        'Save password'
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={processing}
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </SettingsRow>
    );
}
