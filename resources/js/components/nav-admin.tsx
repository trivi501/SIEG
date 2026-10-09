import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Shield, Key, Users, LayoutGrid, Ticket, FileBarChart, History } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';

function hasPermission(permission?: string): boolean {
    if (!permission) return true;
    const perms = (usePage().props.userPermissions as string[]) ?? [];
    return perms.includes(permission);
}

interface AdminItem {
  title: string;
  href: string;
  icon: typeof Users;
  permission?: string;
}

const allAdminItems: AdminItem[] = [
  { title: 'Usuarios', href: '/settings/users', icon: Users, permission: 'users-index' },
  { title: 'Roles', href: '/settings/roles', icon: Shield, permission: 'roles-index' },
  { title: 'Permisos', href: '/settings/permissions', icon: Key, permission: 'permisos-index' },
  { title: 'Bitácora de auditoría', href: '/auditoria', icon: History, permission: 'auditoria-index' },
  { title: 'Tickets de Soporte', href: '/support-tickets', icon: Ticket, permission: 'tickets-index' },
  { title: 'Logs', href: '/logs', icon: FileBarChart, permission: 'logs-view' },
];

export function NavAdmin({ open, onToggle }: { open?: boolean; onToggle?: () => void }) {
  const { isCurrentUrl } = useCurrentUrl();
  const adminItems = allAdminItems.filter((item) => hasPermission(item.permission));

  if (adminItems.length === 0) return null;

  const isActive = adminItems.some((item) => isCurrentUrl(item.href));

  return (
    <SidebarGroup className="px-2 py-0">
      <SidebarGroupLabel>Administración</SidebarGroupLabel>
      <SidebarMenu>
        <Collapsible asChild open={open} onOpenChange={onToggle} className="group/collapsible">
          <SidebarMenuItem>
            <CollapsibleTrigger asChild>
              <SidebarMenuButton tooltip={{ children: 'Administración' }} isActive={isActive}>
                <LayoutGrid />
                <span>Administración</span>
                <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
              </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <SidebarMenuSub>
                {adminItems.map((item) => (
                  <SidebarMenuSubItem key={item.title}>
                    <SidebarMenuSubButton asChild isActive={isCurrentUrl(item.href)}>
                      <Link href={item.href} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                      </Link>
                    </SidebarMenuSubButton>
                  </SidebarMenuSubItem>
                ))}
              </SidebarMenuSub>
            </CollapsibleContent>
          </SidebarMenuItem>
        </Collapsible>
      </SidebarMenu>
    </SidebarGroup>
  );
}
