import { Link } from '@inertiajs/react';
import { ChevronRight, LayoutGrid, User } from 'lucide-react';
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

interface SettingsItem {
  title: string;
  href: string;
  icon: typeof User;
}

const settingsItems: SettingsItem[] = [
  { title: 'Perfil', href: '/settings/profile', icon: User },
];

export function NavSettings({ open, onToggle }: { open?: boolean; onToggle?: () => void }) {
  const { isCurrentUrl } = useCurrentUrl();

  return (
    <SidebarGroup className="px-2 py-0">
      <SidebarGroupLabel>Ajustes</SidebarGroupLabel>
      <SidebarMenu>
        <Collapsible asChild open={open} onOpenChange={onToggle} className="group/collapsible">
          <SidebarMenuItem>
            <CollapsibleTrigger asChild>
              <SidebarMenuButton tooltip={{ children: 'Ajustes' }}>
                <LayoutGrid />
                <span>Ajustes</span>
                <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
              </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <SidebarMenuSub>
                {settingsItems.map((item) => (
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
