import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Building2, LayoutGrid, Wallet, ClipboardCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavAdmin } from '@/components/nav-admin';
import { NavMain } from '@/components/nav-main';
import { NavSettings } from '@/components/nav-settings';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, presupuesto } from '@/routes';
import type { NavItem } from '@/types';

function hasPermission(permission?: string): boolean {
    if (!permission) return true;
    const perms = (usePage().props.userPermissions as string[]) ?? [];
    return perms.includes(permission);
}

function getCurrentPath(): string {
    return typeof window !== 'undefined' ? window.location.pathname : '';
}

function findActiveSection(path: string): SectionKey | null {
    if (path.startsWith('/requisiciones') || path.startsWith('/recursos-materiales') || path.startsWith('/proveedores') || path.startsWith('/modificaciones-presupuestales')) return 'Requisiciones';
    if (path.startsWith('/settings/users') || path.startsWith('/settings/roles') || path.startsWith('/settings/permissions')) return 'Administración';
    if (path.startsWith('/settings/profile') || path.startsWith('/settings/security') || path.startsWith('/settings/appearance')) return 'Ajustes';
    return null;
}

function filterItems(items: NavItem[]): NavItem[] {
    return items.filter((item) => {
        if (!hasPermission(item.permission)) return false;
        if (item.items) {
            item.items = item.items.filter((sub) => hasPermission(sub.permission));
        }
        return true;
    });
}

const SECTION_KEYS = ['Requisiciones', 'Administración', 'Ajustes'] as const;
type SectionKey = (typeof SECTION_KEYS)[number];

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Presupuesto',
        href: presupuesto(),
        icon: Wallet,
        permission: 'presupuesto-index',
    },
    {
        title: 'Requisiciones',
        icon: ClipboardCheck,
        items: [
            { title: 'Listado', href: '/requisiciones', permission: 'requisiciones-index' },
            { title: 'Nueva', href: '/requisiciones/crear', permission: 'requisiciones-create' },
            { title: 'Recursos Materiales', href: '/recursos-materiales', permission: 'requisiciones-index' },
            { title: 'Proveedores', href: '/proveedores', permission: 'proveedores-index' },
            { title: 'Cómo va el gasto', href: '/requisiciones/gasto', permission: 'requisiciones-index' },
            { title: 'Modificaciones Presupuestales', href: '/modificaciones-presupuestales', permission: 'modificaciones-index' },
        ],
    },
];

const moduleNavItems: NavItem[] = [
    {
        title: 'Secretarías',
        href: '/secretarias',
        icon: Building2,
        permission: 'secretarias-index',
    },
];

import { SidebarGroup, SidebarGroupLabel } from '@/components/ui/sidebar';

export function AppSidebar() {
    const { url } = usePage();
    const filteredMain = filterItems(mainNavItems);
    const filteredModules = filterItems(moduleNavItems);

    const urlSection = useMemo(() => findActiveSection(getCurrentPath() || url), [url]);
    const [manualSection, setManualSection] = useState<SectionKey | null>(null);

    const openSection = manualSection ?? urlSection;
    const toggleSection = (key: SectionKey) => {
        setManualSection((prev) => (prev === key ? null : key));
    };

    useEffect(() => {
        setManualSection(null);
    }, [url]);

    const navOpenSections: Record<string, boolean> = {};
    filteredMain.forEach((item) => {
        if (item.items) {
            navOpenSections[item.title] = openSection === item.title;
        }
    });

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={filteredMain}
                    openSections={navOpenSections}
                    onToggle={(title) => {
                        if (title === 'Requisiciones') {
                            toggleSection(title);
                        }
                    }}
                />
                {filteredModules.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Módulos</SidebarGroupLabel>
                        <SidebarMenu>
                            {filteredModules.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild tooltip={{ children: item.title }}>
                                        <Link href={item.href!} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                <NavAdmin open={openSection === 'Administración'} onToggle={() => toggleSection('Administración')} />
                <NavSettings open={openSection === 'Ajustes'} onToggle={() => toggleSection('Ajustes')} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
