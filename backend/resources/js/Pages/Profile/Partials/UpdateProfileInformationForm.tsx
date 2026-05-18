import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <FieldGroup>
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-lg font-semibold">Thông tin cá nhân</h2>
                    <p className="text-sm text-muted-foreground">
                        Cập nhật tên và địa chỉ email của bạn.
                    </p>
                </div>
                <Field>
                    <FieldLabel htmlFor="name">Họ và tên</FieldLabel>
                    <Input
                        id="name"
                        className="bg-background"
                        required
                        autoFocus
                        autoComplete="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && <FieldDescription className="text-destructive font-medium">{errors.name}</FieldDescription>}
                </Field>
                <Field>
                    <FieldLabel htmlFor="email">Email</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        className="bg-background"
                        required
                        autoComplete="username"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && <FieldDescription className="text-destructive font-medium">{errors.email}</FieldDescription>}
                </Field>
                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="text-sm text-muted-foreground">
                        Email của bạn chưa được xác thực.{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="underline underline-offset-4 hover:text-foreground font-medium text-foreground"
                        >
                            Nhấn vào đây để gửi lại email xác thực.
                        </Link>
                        {status === 'verification-link-sent' && (
                            <span className="block mt-1 text-green-600 font-medium">
                                Liên kết xác thực mới đã được gửi đến email của bạn.
                            </span>
                        )}
                    </div>
                )}
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
