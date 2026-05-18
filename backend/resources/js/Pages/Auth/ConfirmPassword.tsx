import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Xác nhận mật khẩu" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <h1 className="text-2xl font-bold">Xác nhận mật khẩu</h1>
                        <p className="text-sm text-balance text-muted-foreground mt-2">
                            Đây là khu vực bảo mật. Vui lòng xác nhận mật khẩu của bạn để tiếp tục.
                        </p>
                    </div>
                    <Field>
                        <FieldLabel htmlFor="password">Mật khẩu</FieldLabel>
                        <Input
                            id="password"
                            type="password"
                            className="bg-background"
                            autoFocus
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        {errors.password && <FieldDescription className="text-destructive font-medium">{errors.password}</FieldDescription>}
                    </Field>
                    <Button type="submit" disabled={processing} className="w-full mt-2">
                        Xác nhận
                    </Button>
                </FieldGroup>
            </form>
        </GuestLayout>
    );
}
