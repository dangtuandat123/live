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

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title="Đăng ký" />
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
                                        Tạo tài khoản
                                    </h1>
                                    <p className="text-muted-foreground mt-2 text-sm text-balance">
                                        Điền thông tin bên dưới để tạo tài khoản
                                        mới
                                    </p>
                                </div>

                                <Field>
                                    <FieldLabel htmlFor="name">
                                        Họ và tên
                                    </FieldLabel>
                                    <Input
                                        id="name"
                                        type="text"
                                        placeholder="Nguyễn Văn A"
                                        required
                                        className="bg-background"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    {errors.name && (
                                        <FieldDescription className="text-destructive font-medium">
                                            {errors.name}
                                        </FieldDescription>
                                    )}
                                </Field>

                                <Field>
                                    <FieldLabel
                                        htmlFor="email"
                                        className="mt-2"
                                    >
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
                                    <FieldLabel
                                        htmlFor="password"
                                        className="mt-2"
                                    >
                                        Mật khẩu
                                    </FieldLabel>
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
                                    <FieldLabel
                                        htmlFor="password_confirmation"
                                        className="mt-2"
                                    >
                                        Xác nhận mật khẩu
                                    </FieldLabel>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        required
                                        className="bg-background"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.password_confirmation && (
                                        <FieldDescription className="text-destructive font-medium">
                                            {errors.password_confirmation}
                                        </FieldDescription>
                                    )}
                                </Field>

                                <Field className="mt-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full"
                                    >
                                        Đăng ký
                                    </Button>
                                </Field>

                                <div className="text-muted-foreground mt-4 text-center text-sm">
                                    Đã có tài khoản?{' '}
                                    <Link
                                        href={route('login')}
                                        className="hover:text-primary text-foreground font-medium underline underline-offset-4"
                                    >
                                        Đăng nhập
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
