import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import * as React from 'react';

import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import { Progress } from '@/components/ui/progress';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    BarChart3Icon,
    CreditCardIcon,
    GalleryVerticalEndIcon,
    LayoutDashboardIcon,
    PackageIcon,
    SettingsIcon,
    ShieldCheckIcon,
    SparklesIcon,
    VideoIcon,
} from 'lucide-react';

function SidebarCredits() {
    const { auth } = usePage<PageProps>().props;
    const { state, isMobile } = useSidebar();

    React.useEffect(() => {
        if (!auth?.user) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['auth', 'activeSubscription'],
            });
        }, 10000);

        return () => clearInterval(interval);
    }, [auth?.user]);

    if (!auth?.user) return null;

    const subscription = auth?.subscription;
    const used = subscription?.used_ai_credits ?? 0;
    const limit = subscription?.features?.ai_credits ?? 1000;
    const percentage =
        limit > 0 ? Math.min(100, Math.max(0, (used / limit) * 100)) : 0;

    if (state === 'collapsed' && !isMobile) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href="/subscription"
                        className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground group relative mx-auto flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                    >
                        <div className="relative flex items-center justify-center">
                            <SparklesIcon className="text-primary size-5 animate-pulse" />
                            {limit !== -1 && (
                                <div className="bg-primary text-primary-foreground border-sidebar absolute -right-1 -bottom-1 flex h-4 w-4 items-center justify-center rounded-full border text-[8px] font-bold">
                                    {Math.round(percentage)}%
                                </div>
                            )}
                        </div>
                    </Link>
                </TooltipTrigger>
                <TooltipContent
                    side="right"
                    align="center"
                    className="bg-sidebar border-sidebar-border space-y-1 border p-2 shadow-lg"
                >
                    <p className="text-sidebar-foreground text-xs font-semibold">
                        AI Credits
                    </p>
                    <p className="text-sidebar-foreground/70 text-[11px]">
                        {used.toLocaleString()} /{' '}
                        {limit === -1 ? 'Vô hạn' : limit.toLocaleString()}
                    </p>
                </TooltipContent>
            </Tooltip>
        );
    }

    return (
        <div className="border-sidebar-border/50 bg-sidebar-accent/10 mx-2 my-2 space-y-2 rounded-lg border p-3 backdrop-blur-md">
            <div className="flex items-center justify-between text-xs">
                <div className="text-sidebar-foreground/80 flex items-center gap-1.5 font-medium">
                    <SparklesIcon className="text-primary size-3.5 animate-pulse" />
                    <span>AI Credits</span>
                </div>
                <Link
                    href="/subscription"
                    className="text-primary hover:text-primary/80 text-[10px] font-bold tracking-wider uppercase transition-colors"
                >
                    Nâng cấp
                </Link>
            </div>

            <div className="space-y-1">
                <div className="text-sidebar-foreground/80 flex justify-between text-[10px] font-semibold">
                    <span className="text-sidebar-foreground/50">Đã dùng</span>
                    <span>
                        {used.toLocaleString()} /{' '}
                        {limit === -1 ? 'Vô hạn' : limit.toLocaleString()}
                    </span>
                </div>
                {limit === -1 ? (
                    <div className="bg-primary/20 h-1.5 w-full overflow-hidden rounded-full">
                        <div className="from-primary h-full w-full animate-pulse bg-gradient-to-r via-purple-500 to-indigo-500" />
                    </div>
                ) : (
                    <Progress value={percentage} className="h-1.5" />
                )}
            </div>
        </div>
    );
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { auth } = usePage<PageProps>().props;

    const user = {
        name: auth.user?.name ?? 'User',
        email: auth.user?.email ?? '',
        avatar: '',
    };

    const subscription = auth?.subscription;
    const planName = subscription?.package_name ?? 'Free';

    const teams = [
        {
            name: 'LiveStream AI',
            logo: <GalleryVerticalEndIcon />,
            plan: planName,
        },
    ];

    const navMain = [
        {
            title: 'Tổng quan',
            url: route('dashboard'),
            icon: <LayoutDashboardIcon />,
            isActive: route().current('dashboard'),
        },
        {
            title: 'Phân tích phiên live',
            url: route('lives.index'),
            icon: <VideoIcon />,
            isActive: route().current('lives.*'),
        },
        {
            title: 'Sản phẩm',
            url: route('products.index'),
            icon: <PackageIcon />,
            isActive: route().current('products.*'),
        },
        {
            title: 'Báo cáo',
            url: route('reports.index'),
            icon: <BarChart3Icon />,
            isActive: route().current('reports.*'),
        },
        {
            title: 'Gói dịch vụ',
            url: route('subscription.index'),
            icon: <CreditCardIcon />,
            isActive: route().current('subscription.index'),
        },
        {
            title: 'Cài đặt',
            url: route('settings.index'),
            icon: <SettingsIcon />,
            isActive: route().current('settings.*'),
        },
        // Admin link - chỉ hiển thị cho admin
        ...(auth.user?.role === 'admin'
            ? [
                  {
                      title: 'Quản trị hệ thống',
                      url: route('admin.dashboard'),
                      icon: <ShieldCheckIcon />,
                      isActive: route().current('admin.*'),
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <TeamSwitcher teams={teams} />
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navMain} />
            </SidebarContent>
            <SidebarFooter>
                <SidebarCredits />
                <NavUser user={user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
