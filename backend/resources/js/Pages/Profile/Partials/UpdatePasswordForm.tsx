import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { CheckIcon, KeyRound, LoaderIcon } from 'lucide-react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
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
        <form onSubmit={submit}>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <KeyRound className="text-primary size-5" />
                        Đổi mật khẩu
                    </CardTitle>
                    <CardDescription>
                        Sử dụng mật khẩu dài và ngẫu nhiên để bảo mật tài khoản.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="current_password">
                            Mật khẩu hiện tại
                        </Label>
                        <Input
                            id="current_password"
                            ref={currentPasswordInput}
                            type="password"
                            autoComplete="current-password"
                            value={data.current_password}
                            onChange={(e) =>
                                setData('current_password', e.target.value)
                            }
                        />
                        {errors.current_password && (
                            <p className="text-destructive text-sm font-medium">
                                {errors.current_password}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Mật khẩu mới</Label>
                        <Input
                            id="password"
                            ref={passwordInput}
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                        {errors.password && (
                            <p className="text-destructive text-sm font-medium">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Xác nhận mật khẩu mới
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                        {errors.password_confirmation && (
                            <p className="text-destructive text-sm font-medium">
                                {errors.password_confirmation}
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing ? (
                            <LoaderIcon className="size-4 animate-spin" />
                        ) : recentlySuccessful ? (
                            <CheckIcon className="size-4" />
                        ) : null}
                        {recentlySuccessful ? 'Đã lưu' : 'Cập nhật mật khẩu'}
                    </Button>
                </CardContent>
            </Card>
        </form>
    );
}
