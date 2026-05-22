import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/Layouts/AdminLayout';
import type { User } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ShieldCheckIcon } from 'lucide-react';

interface Props {
    users: User[];
}

export default function UsersIndex({ users }: Props) {
    function handleRoleChange(userId: number, newRole: string) {
        router.put(
            route('admin.users.update-role', userId),
            {
                role: newRole,
            },
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <AdminLayout>
            <Head title="Admin - Quản lý người dùng" />
            <header className="border-border/40 bg-background/95 sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                <div className="flex items-center gap-2 px-4">
                    <SidebarTrigger className="-ml-1" />
                    <Separator
                        orientation="vertical"
                        className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                    />
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem className="hidden md:block">
                                <BreadcrumbLink href={route('admin.dashboard')}>
                                    Admin
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                <BreadcrumbPage>
                                    Quản lý người dùng
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Quản lý người dùng
                        </h1>
                        <p className="text-muted-foreground">
                            Xem danh sách và phân quyền người dùng trên hệ thống
                        </p>
                    </div>
                    <Badge variant="secondary" className="gap-1.5">
                        <ShieldCheckIcon className="size-3.5" />
                        {users.length} người dùng
                    </Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Danh sách người dùng</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">ID</TableHead>
                                    <TableHead>Tên</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Vai trò</TableHead>
                                    <TableHead>Gói</TableHead>
                                    <TableHead>Xác thực email</TableHead>
                                    <TableHead>Ngày đăng ký</TableHead>
                                    <TableHead className="text-right">
                                        Thao tác
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="text-muted-foreground font-mono">
                                            #{user.id}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.role === 'admin'
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {user.role === 'admin'
                                                    ? 'Admin'
                                                    : 'User'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.plan_name ===
                                                    'Enterprise'
                                                        ? 'default'
                                                        : user.plan_name ===
                                                            'Pro'
                                                          ? 'secondary'
                                                          : 'outline'
                                                }
                                            >
                                                {user.plan_name || 'Free'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="gap-1"
                                                >
                                                    ✓ Đã xác thực
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    Chưa xác thực
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.created_at
                                                ? new Date(
                                                      user.created_at,
                                                  ).toLocaleDateString('vi-VN')
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Select
                                                defaultValue={user.role}
                                                onValueChange={(value) =>
                                                    handleRoleChange(
                                                        user.id,
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="h-8 w-28">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="user">
                                                        User
                                                    </SelectItem>
                                                    <SelectItem value="admin">
                                                        Admin
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
