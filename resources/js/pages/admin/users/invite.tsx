import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function InviteStaff({ roles }: { roles: Option[] }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', role: roles[0]?.value ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout title="Invite staff">
            <TextLink href={route('admin.users.index')} className="text-sm">
                ← Users
            </TextLink>
            <form
                onSubmit={submit}
                noValidate
                className="mt-4 max-w-xl space-y-4 rounded-card border border-border bg-surface p-4 md:p-6"
            >
                <p className="text-sm text-muted">
                    They'll get an email with a link to set their password. Customers sign up themselves.
                </p>
                <FormField
                    id="name"
                    label="Name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="off"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Field id="role" label="Role" error={errors.role}>
                    {(control) => (
                        <Select {...control} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                <Button type="submit" processing={processing}>
                    Send invitation
                </Button>
            </form>
        </AdminLayout>
    );
}
