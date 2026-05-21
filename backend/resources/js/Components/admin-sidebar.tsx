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
  UsersIcon,
  SettingsIcon,
  ArrowLeftIcon,
  PackageIcon,
  CreditCardIcon,
} from "lucide-react"

export function AdminSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const auth = usePage().props.auth

  const user = {
    name: auth.user?.name ?? "Admin",
    email: auth.user?.email ?? "",
    avatar: "",
  }

  const teams = [
    {
      name: "LiveStream AI",
      logo: <GalleryVerticalEndIcon />,
      plan: "Admin",
    },
  ]

  const navMain = [
    {
      title: "Tổng quan Admin",
      url: route("admin.dashboard"),
      icon: <LayoutDashboardIcon />,
      isActive: route().current("admin.dashboard"),
    },
    {
      title: "Quản lý người dùng",
      url: route("admin.users.index"),
      icon: <UsersIcon />,
      isActive: route().current("admin.users.*"),
    },
    {
      title: "Quản lý gói dịch vụ",
      url: route("admin.packages.index"),
      icon: <PackageIcon />,
      isActive: route().current("admin.packages.*"),
    },
    {
      title: "Cấu hình thanh toán",
      url: route("admin.payments.index"),
      icon: <CreditCardIcon />,
      isActive: route().current("admin.payments.*"),
    },
    {
      title: "Cài đặt hệ thống",
      url: route("admin.settings.index"),
      icon: <SettingsIcon />,
      isActive: route().current("admin.settings.*"),
    },
    {
      title: "Quay lại Dashboard",
      url: route("dashboard"),
      icon: <ArrowLeftIcon />,
      isActive: false,
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
