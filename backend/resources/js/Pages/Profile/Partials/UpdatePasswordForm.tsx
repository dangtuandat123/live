import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <FieldGroup>
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-lg font-semibold">Đổi mật khẩu</h2>
                    <p className="text-sm text-muted-foreground">
                        Sử dụng mật khẩu dài và ngẫu nhiên để bảo mật tài khoản.
                    </p>
                </div>
                <Field>
                    <FieldLabel htmlFor="current_password">Mật khẩu hiện tại</FieldLabel>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        className="bg-background"
                        autoComplete="current-password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                    />
                    {errors.current_password && <FieldDescription className="text-destructive font-medium">{errors.current_password}</FieldDescription>}
                </Field>
                <Field>
                    <FieldLabel htmlFor="password">Mật khẩu mới</FieldLabel>
                    <Input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        className="bg-background"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && <FieldDescription className="text-destructive font-medium">{errors.password}</FieldDescription>}
                </Field>
                <Field>
                    <FieldLabel htmlFor="password_confirmation">Xác nhận mật khẩu mới</FieldLabel>
                    <Input
                        id="password_confirmation"
                        type="password"
                        className="bg-background"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    {errors.password_confirmation && <FieldDescription className="text-destructive font-medium">{errors.password_confirmation}</FieldDescription>}
                </Field>
                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Lưu</Button>
                    {recentlySuccessful && (
                        <span className="text-sm text-muted-foreground">Đã lưu.</span>
                    )}
                </div>
            </FieldGroup>
        </form>
    );
}
