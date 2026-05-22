import * as React from "react"
import { usePage, Link } from "@inertiajs/react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import { TeamSwitcher } from "@/components/team-switcher"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
  useSidebar,
} from "@/components/ui/sidebar"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { Progress } from "@/components/ui/progress"
import {
  GalleryVerticalEndIcon,
  LayoutDashboardIcon,
  PackageIcon,
  VideoIcon,
  BarChart3Icon,
  SettingsIcon,
  ShieldCheckIcon,
  CreditCardIcon,
  SparklesIcon,
} from "lucide-react"

function SidebarCredits() {
  const { auth } = usePage().props as any
  const { state } = useSidebar()
  
  if (!auth?.user) return null

  const subscription = auth?.subscription
  const used = subscription?.used_ai_credits ?? 0
  const limit = subscription?.features?.ai_credits ?? 1000
  const percentage = limit > 0 ? Math.min(100, Math.max(0, (used / limit) * 100)) : 0

  if (state === "collapsed") {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <Link
            href="/subscription"
            className="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors mx-auto relative group"
          >
            <div className="relative flex items-center justify-center">
              <SparklesIcon className="size-5 text-primary animate-pulse" />
              {limit !== -1 && (
                <div className="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[8px] font-bold text-primary-foreground border border-sidebar">
                  {Math.round(percentage)}%
                </div>
              )}
            </div>
          </Link>
        </TooltipTrigger>
        <TooltipContent side="right" align="center" className="space-y-1 p-2 bg-sidebar border border-sidebar-border shadow-lg">
          <p className="font-semibold text-xs text-sidebar-foreground">AI Credits</p>
          <p className="text-[11px] text-sidebar-foreground/70">
            {used.toLocaleString()} / {limit === -1 ? "Vô hạn" : limit.toLocaleString()}
          </p>
        </TooltipContent>
      </Tooltip>
    )
  }

  return (
    <div className="mx-2 my-2 p-3 rounded-lg border border-sidebar-border/50 bg-sidebar-accent/10 backdrop-blur-md space-y-2">
      <div className="flex justify-between items-center text-xs">
        <div className="flex items-center gap-1.5 font-medium text-sidebar-foreground/80">
          <SparklesIcon className="size-3.5 text-primary animate-pulse" />
          <span>AI Credits</span>
        </div>
        <Link
          href="/subscription"
          className="text-[10px] font-bold text-primary hover:text-primary/80 transition-colors uppercase tracking-wider"
        >
          Nâng cấp
        </Link>
      </div>

      <div className="space-y-1">
        <div className="flex justify-between text-[10px] font-semibold text-sidebar-foreground/80">
          <span className="text-sidebar-foreground/50">Đã dùng</span>
          <span>
            {used.toLocaleString()} / {limit === -1 ? "Vô hạn" : limit.toLocaleString()}
          </span>
        </div>
        {limit === -1 ? (
          <div className="h-1.5 w-full rounded-full bg-primary/20 overflow-hidden">
            <div className="h-full bg-gradient-to-r from-primary via-purple-500 to-indigo-500 w-full animate-pulse" />
          </div>
        ) : (
          <Progress
            value={percentage}
            className="h-1.5"
          />
        )}
      </div>
    </div>
  )
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { auth } = usePage().props as any

  const user = {
    name: auth.user?.name ?? "User",
    email: auth.user?.email ?? "",
    avatar: "",
  }

  const subscription = auth?.subscription
  const planName = subscription?.package_name ?? "Free"

  const teams = [
    {
      name: "LiveStream AI",
      logo: <GalleryVerticalEndIcon />,
      plan: planName,
    },
  ]

  const navMain = [
    {
      title: "Tổng quan",
      url: route("dashboard"),
      icon: <LayoutDashboardIcon />,
      isActive: route().current("dashboard"),
    },
    {
      title: "Phân tích phiên live",
      url: route("lives.index"),
      icon: <VideoIcon />,
      isActive: route().current("lives.*"),
    },
    {
      title: "Sản phẩm",
      url: route("products.index"),
      icon: <PackageIcon />,
      isActive: route().current("products.*"),
    },
    {
      title: "Báo cáo",
      url: route("reports.index"),
      icon: <BarChart3Icon />,
      isActive: route().current("reports.*"),
    },
    {
      title: "Gói dịch vụ",
      url: route("subscription.index"),
      icon: <CreditCardIcon />,
      isActive: route().current("subscription.index"),
    },
    {
      title: "Cài đặt",
      url: route("settings.index"),
      icon: <SettingsIcon />,
      isActive: route().current("settings.*"),
    },
    // Admin link - chỉ hiển thị cho admin
    ...(auth.user?.role === "admin" ? [{
      title: "Quản trị hệ thống",
      url: route("admin.dashboard"),
      icon: <ShieldCheckIcon />,
      isActive: route().current("admin.*"),
    }] : []),
  ]

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
  )
}
