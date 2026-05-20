import * as React from "react"
import { usePage } from "@inertiajs/react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import { TeamSwitcher } from "@/components/team-switcher"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from "@/components/ui/sidebar"
import {
  GalleryVerticalEndIcon,
  LayoutDashboardIcon,
  PackageIcon,
  VideoIcon,
  BarChart3Icon,
  SettingsIcon,
} from "lucide-react"

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const auth = usePage().props.auth

  const user = {
    name: auth.user?.name ?? "User",
    email: auth.user?.email ?? "",
    avatar: "",
  }

  const teams = [
    {
      name: "LiveStream AI",
      logo: <GalleryVerticalEndIcon />,
      plan: "Pro",
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
      title: "Cài đặt",
      url: route("settings.index"),
      icon: <SettingsIcon />,
      isActive: route().current("settings.*"),
    },
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
        <NavUser user={user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
