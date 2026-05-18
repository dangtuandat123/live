import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const confirmDelete = () => setConfirming(true);

    const closeModal = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-0.5">
                <h2 className="text-lg font-semibold text-destructive">Xóa tài khoản</h2>
                <p className="text-sm text-muted-foreground">
                    Khi tài khoản của bạn bị xóa, tất cả dữ liệu sẽ bị xóa vĩnh viễn. Vui lòng tải xuống mọi dữ liệu bạn muốn giữ lại trước khi xóa tài khoản.
                </p>
            </div>
            <div>
                <Button variant="destructive" onClick={confirmDelete}>Xóa tài khoản</Button>
            </div>

            {confirming && (
                <div className="fixed inset-0 z-50 flex items-center justify-center">
                    <div className="fixed inset-0 bg-black/50" onClick={closeModal} />
                    <div className="relative z-50 w-full max-w-md rounded-lg border bg-background p-6 shadow-lg mx-4">
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <div className="flex flex-col gap-0.5">
                                <h2 className="text-lg font-semibold">Bạn có chắc chắn muốn xóa tài khoản?</h2>
                                <p className="text-sm text-muted-foreground">
                                    Tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn. Vui lòng nhập mật khẩu để xác nhận.
                                </p>
                            </div>
                            <Field>
                                <FieldLabel htmlFor="delete-password" className="sr-only">Mật khẩu</FieldLabel>
                                <Input
                                    id="delete-password"
                                    type="password"
                                    ref={passwordInput}
                                    className="bg-background"
                                    autoFocus
                                    placeholder="Mật khẩu"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                {errors.password && <FieldDescription className="text-destructive font-medium">{errors.password}</FieldDescription>}
                            </Field>
                            <div className="flex justify-end gap-3">
                                <Button variant="outline" type="button" onClick={closeModal}>Hủy</Button>
                                <Button variant="destructive" type="submit" disabled={processing}>Xóa tài khoản</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
