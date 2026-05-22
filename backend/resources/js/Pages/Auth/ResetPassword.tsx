import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Đặt lại mật khẩu" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <h1 className="text-2xl font-bold">Đặt lại mật khẩu</h1>
                        <p className="text-muted-foreground mt-2 text-sm text-balance">
                            Nhập mật khẩu mới cho tài khoản của bạn.
                        </p>
                    </div>
                    <Field>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            className="bg-background"
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <FieldDescription className="text-destructive font-medium">
                                {errors.email}
                            </FieldDescription>
                        )}
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="password" className="mt-2">
                            Mật khẩu mới
                        </FieldLabel>
                        <Input
                            id="password"
                            type="password"
                            className="bg-background"
                            autoComplete="new-password"
                            autoFocus
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                        {errors.password && (
                            <FieldDescription className="text-destructive font-medium">
                                {errors.password}
                            </FieldDescription>
                        )}
                    </Field>
                    <Field>
                        <FieldLabel
                            htmlFor="password_confirmation"
                            className="mt-2"
                        >
                            Xác nhận mật khẩu
                        </FieldLabel>
                        <Input
                            id="password_confirmation"
                            type="password"
                            className="bg-background"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                        {errors.password_confirmation && (
                            <FieldDescription className="text-destructive font-medium">
                                {errors.password_confirmation}
                            </FieldDescription>
                        )}
                    </Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="mt-2 w-full"
                    >
                        Đặt lại mật khẩu
                    </Button>
                </FieldGroup>
            </form>
        </GuestLayout>
    );
}
