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

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Quên mật khẩu" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <h1 className="text-2xl font-bold">Quên mật khẩu</h1>
                        <p className="text-muted-foreground mt-2 text-sm text-balance">
                            Nhập email của bạn và chúng tôi sẽ gửi liên kết đặt
                            lại mật khẩu.
                        </p>
                    </div>
                    {status && (
                        <div className="text-center text-sm font-medium text-green-600">
                            {status}
                        </div>
                    )}
                    <Field>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            placeholder="m@example.com"
                            className="bg-background"
                            autoFocus
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <FieldDescription className="text-destructive font-medium">
                                {errors.email}
                            </FieldDescription>
                        )}
                    </Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="mt-2 w-full"
                    >
                        Gửi liên kết đặt lại mật khẩu
                    </Button>
                </FieldGroup>
            </form>
        </GuestLayout>
    );
}
