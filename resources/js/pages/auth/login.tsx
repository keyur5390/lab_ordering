import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { TextLink } from '@/components/ui/text-link';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Login({ canResetPassword }: { canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('login.store'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Log in" description="Use the email and password you registered with.">
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <FormField
                    id="password"
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    labelAside={
                        canResetPassword && (
                            <TextLink href={route('password.request')} className="text-sm">
                                Forgot password?
                            </TextLink>
                        )
                    }
                />

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <Label htmlFor="remember" className="font-normal">
                        Remember me
                    </Label>
                </div>

                <Button type="submit" className="w-full" processing={processing}>
                    Log in
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted">
                New here? <TextLink href={route('register')}>Create an account</TextLink>
            </p>
        </GuestLayout>
    );
}
