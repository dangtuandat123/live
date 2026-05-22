import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Head, Link, useForm } from '@inertiajs/react';
import { GalleryVerticalEndIcon } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword?: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title="Đăng nhập" />
            <div className="flex flex-col gap-4 p-6 md:p-10">
                <div className="flex justify-center gap-2 md:justify-start">
                    <Link
                        href="/"
                        className="flex items-center gap-2 font-medium"
                    >
                        <div className="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md">
                            <GalleryVerticalEndIcon className="size-4" />
                        </div>
                        LiveStream App
                    </Link>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-xs">
                        <form className="flex flex-col gap-6" onSubmit={submit}>
                            <FieldGroup>
                                <div className="mb-4 flex flex-col items-center gap-1 text-center">
                                    <h1 className="text-2xl font-bold">
                                        Đăng nhập
                                    </h1>
                                    <p className="text-muted-foreground mt-2 text-sm text-balance">
                                        Nhập email của bạn để đăng nhập vào tài
                                        khoản
                                    </p>
                                </div>
                                {status && (
                                    <div className="text-center text-sm font-medium text-green-600">
                                        {status}
                                    </div>
                                )}
                                <Field>
                                    <FieldLabel htmlFor="email">
                                        Email
                                    </FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="m@example.com"
                                        required
                                        className="bg-background"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                    {errors.email && (
                                        <FieldDescription className="text-destructive font-medium">
                                            {errors.email}
                                        </FieldDescription>
                                    )}
                                </Field>
                                <Field>
                                    <div className="mt-2 flex items-center">
                                        <FieldLabel htmlFor="password">
                                            Mật khẩu
                                        </FieldLabel>
                                        {canResetPassword && (
                                            <Link
                                                href={route('password.request')}
                                                className="ml-auto text-sm underline-offset-4 hover:underline"
                                            >
                                                Quên mật khẩu?
                                            </Link>
                                        )}
                                    </div>
                                    <Input
                                        id="password"
                                        type="password"
                                        required
                                        className="bg-background"
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
                                    <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) =>
                                                setData(
                                                    'remember',
                                                    e.target.checked,
                                                )
                                            }
                                            className="text-primary focus:ring-primary cursor-pointer rounded border-gray-300 shadow-sm"
                                        />
                                        Ghi nhớ đăng nhập
                                    </label>
                                </Field>
                                <Field className="mt-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full"
                                    >
                                        Đăng nhập
                                    </Button>
                                </Field>
                                <div className="text-muted-foreground mt-4 text-center text-sm">
                                    Chưa có tài khoản?{' '}
                                    <Link
                                        href={route('register')}
                                        className="hover:text-primary text-foreground font-medium underline underline-offset-4"
                                    >
                                        Đăng ký ngay
                                    </Link>
                                </div>
                            </FieldGroup>
                        </form>
                    </div>
                </div>
            </div>
            <div className="bg-muted relative hidden lg:block">
                <img
                    src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?q=80&w=2070&auto=format&fit=crop"
                    alt="Image"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
            </div>
        </div>
    );
}
