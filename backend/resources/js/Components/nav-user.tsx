import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheckIcon,
    BellIcon,
    ChevronsUpDownIcon,
    CreditCardIcon,
    LaptopIcon,
    LogOutIcon,
    MoonIcon,
    SparklesIcon,
    SunIcon,
} from 'lucide-react';
import * as React from 'react';

export function NavUser({
    user,
}: {
    user: {
        name: string;
        email: string;
        avatar: string;
    };
}) {
    const { isMobile } = useSidebar();
    const { auth } = usePage<PageProps>().props;
    const isProOrEnterprise =
        auth.subscription?.active &&
        (auth.subscription.package_name === 'Pro' ||
            auth.subscription.package_name === 'Enterprise');
    const [theme, setThemeState] = React.useState<'light' | 'dark' | 'system'>(
        () => {
            if (typeof window !== 'undefined') {
                return (
                    (localStorage.getItem('theme') as
                        | 'light'
                        | 'dark'
                        | 'system') || 'system'
                );
            }
            return 'system';
        },
    );

    const setTheme = (value: 'light' | 'dark' | 'system') => {
        setThemeState(value);
        localStorage.setItem('theme', value);

        const root = window.document.documentElement;
        root.classList.remove('light', 'dark');

        if (value === 'system') {
            const systemTheme = window.matchMedia(
                '(prefers-color-scheme: dark)',
            ).matches
                ? 'dark'
                : 'light';
            root.classList.add(systemTheme);
        } else {
            root.classList.add(value);
        }
    };

    React.useEffect(() => {
        const root = window.document.documentElement;
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        const handleSystemThemeChange = () => {
            if (theme === 'system') {
                root.classList.remove('light', 'dark');
                root.classList.add(mediaQuery.matches ? 'dark' : 'light');
            }
        };

        mediaQuery.addEventListener('change', handleSystemThemeChange);
        return () =>
            mediaQuery.removeEventListener('change', handleSystemThemeChange);
    }, [theme]);

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <Avatar className="h-8 w-8 rounded-lg">
                                <AvatarImage
                                    src={user.avatar}
                                    alt={user.name}
                                />
                                <AvatarFallback className="rounded-lg">
                                    CN
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs">
                                    {user.email}
                                </span>
                            </div>
                            <ChevronsUpDownIcon className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        side={isMobile ? 'bottom' : 'right'}
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <Avatar className="h-8 w-8 rounded-lg">
                                    <AvatarImage
                                        src={user.avatar}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="rounded-lg">
                                        CN
                                    </AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-medium">
                                        {user.name}
                                    </span>
                                    <span className="truncate text-xs">
                                        {user.email}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href="/subscription"
                                    className="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <SparklesIcon className="text-primary size-4" />
                                    <span>
                                        {isProOrEnterprise
                                            ? 'Quản lý gói'
                                            : 'Nâng cấp Pro'}
                                    </span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href="/profile"
                                    className="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <BadgeCheckIcon className="size-4" />
                                    <span>Tài khoản</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                                <BellIcon className="size-4" />
                                <span>Thông báo</span>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <SunIcon className="size-4 dark:hidden" />
                                <MoonIcon className="hidden size-4 dark:block" />
                                Giao diện
                            </DropdownMenuSubTrigger>
                            <DropdownMenuPortal>
                                <DropdownMenuSubContent>
                                    <DropdownMenuRadioGroup
                                        value={theme}
                                        onValueChange={(val) =>
                                            setTheme(
                                                val as
                                                    | 'light'
                                                    | 'dark'
                                                    | 'system',
                                            )
                                        }
                                    >
                                        <DropdownMenuRadioItem value="light">
                                            <SunIcon className="mr-2 size-4" />
                                            Sáng
                                        </DropdownMenuRadioItem>
                                        <DropdownMenuRadioItem value="dark">
                                            <MoonIcon className="mr-2 size-4" />
                                            Tối
                                        </DropdownMenuRadioItem>
                                        <DropdownMenuRadioItem value="system">
                                            <LaptopIcon className="mr-2 size-4" />
                                            Hệ thống
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSubContent>
                            </DropdownMenuPortal>
                        </DropdownMenuSub>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="flex w-full cursor-pointer items-center gap-2 text-red-500 focus:bg-red-50 focus:text-red-500 dark:focus:bg-red-950/20"
                            >
                                <LogOutIcon className="size-4" />
                                <span>Đăng xuất</span>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
