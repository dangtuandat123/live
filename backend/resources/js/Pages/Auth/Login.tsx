import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { GalleryVerticalEndIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword?: boolean; }) {
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
                    <Link href="/" className="flex items-center gap-2 font-medium">
                        <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <GalleryVerticalEndIcon className="size-4" />
                        </div>
                        LiveStream App
                    </Link>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-xs">
                        <form className="flex flex-col gap-6" onSubmit={submit}>
                            <FieldGroup>
                                <div className="flex flex-col items-center gap-1 text-center mb-4">
                                    <h1 className="text-2xl font-bold">Đăng nhập</h1>
                                    <p className="text-sm text-balance text-muted-foreground mt-2">
                                        Nhập email của bạn để đăng nhập vào tài khoản
                                    </p>
                                </div>
                                {status && (
                                    <div className="text-sm font-medium text-green-600 text-center">
                                        {status}
                                    </div>
                                )}
                                <Field>
                                    <FieldLabel htmlFor="email">Email</FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="m@example.com"
                                        required
                                        className="bg-background"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    {errors.email && <FieldDescription className="text-destructive font-medium">{errors.email}</FieldDescription>}
                                </Field>
                                <Field>
                                    <div className="flex items-center mt-2">
                                        <FieldLabel htmlFor="password">Mật khẩu</FieldLabel>
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
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                    {errors.password && <FieldDescription className="text-destructive font-medium">{errors.password}</FieldDescription>}
                                </Field>
                                <Field>
                                    <label className="flex items-center gap-2 text-sm mt-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                            className="rounded border-gray-300 text-primary shadow-sm focus:ring-primary cursor-pointer"
                                        />
                                        Ghi nhớ đăng nhập
                                    </label>
                                </Field>
                                <Field className="mt-4">
                                    <Button type="submit" disabled={processing} className="w-full">Đăng nhập</Button>
                                </Field>
                                <div className="text-center text-sm text-muted-foreground mt-4">
                                    Chưa có tài khoản?{" "}
                                    <Link href={route('register')} className="underline underline-offset-4 hover:text-primary font-medium text-foreground">
                                        Đăng ký ngay
                                    </Link>
                                </div>
                            </FieldGroup>
                        </form>
                    </div>
                </div>
            </div>
            <div className="relative hidden bg-muted lg:block">
                <img
                    src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?q=80&w=2070&auto=format&fit=crop"
                    alt="Image"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
            </div>
        </div>
    );
}
