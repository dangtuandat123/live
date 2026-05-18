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
import { GalleryVerticalEndIcon, LayoutDashboardIcon } from "lucide-react"

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const auth = usePage().props.auth

  const user = {
    name: auth.user?.name ?? "User",
    email: auth.user?.email ?? "",
    avatar: "",
  }

  const teams = [
    {
      name: "LiveStream App",
      logo: <GalleryVerticalEndIcon />,
      plan: "Pro",
    },
  ]

  const navMain = [
    {
      title: "Dashboard",
      url: route("dashboard"),
      icon: <LayoutDashboardIcon />,
      isActive: route().current("dashboard"),
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
