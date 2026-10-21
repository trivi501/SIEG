import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    LayoutGrid,
    Wallet,
    ClipboardCheck,
    TrendingUp,
    FileText,
    Receipt,
    Warehouse,
    Car,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { SidebarGroup, SidebarGroupLabel } from '@/components/ui/sidebar';
import { dashboard, presupuesto } from '@/routes';
import type { NavItem } from '@/types';

/** `catalogos-*` = cualquier permiso que empiece con "catalogos-". */
function hasPermission(permission?: string): boolean {
    if (!permission) {
        return true;
    }

    const perms = (usePage().props.userPermissions as string[]) ?? [];

    if (permission.endsWith('*')) {
        const prefijo = permission.slice(0, -1);

        return perms.some((p) => p.startsWith(prefijo));
    }

    return perms.includes(permission);
}

function getCurrentPath(): string {
    return typeof window !== 'undefined' ? window.location.pathname : '';
}

function findActiveSection(path: string): SectionKey | null {
    // "Ejercicio y control del gasto" es una entrada propia (debajo de Presupuesto), no abre el grupo Requisiciones.
    if (path.startsWith('/requisiciones/gasto')) {
        return null;
    }

    if (
        path.startsWith('/requisiciones') ||
        path.startsWith('/recursos-materiales') ||
        path.startsWith('/catalogos/proveedores') ||
        path.startsWith('/catalogos/calendario-captura') ||
        path.startsWith('/catalogos/calendario-modificaciones') ||
        path.startsWith('/modificaciones-presupuestales')
    ) {
        return 'Requisiciones';
    }

    if (path.startsWith('/almacen')) return 'Almacén';
    if (
        path.startsWith('/vehiculos') ||
        path.startsWith('/catalogos/vehiculos')
    )
        return 'Vehículos';
    if (
        path.startsWith('/settings/users') ||
        path.startsWith('/settings/roles') ||
        path.startsWith('/settings/permissions') ||
        path.startsWith('/auditoria')
    ) {
        return 'Administración';
    }

    if (
        path.startsWith('/settings/profile') ||
        path.startsWith('/settings/security') ||
        path.startsWith('/settings/appearance')
    ) {
        return 'Ajustes';
    }

    return null;
}

function filterItems(items: NavItem[]): NavItem[] {
    return items.filter((item) => {
        if (!hasPermission(item.permission)) {
            return false;
        }

        if (item.items) {
            item.items = item.items.filter((sub) =>
                hasPermission(sub.permission),
            );
        }

        return true;
    });
}

const SECTION_KEYS = [
    'Requisiciones',
    'Almacén',
    'Vehículos',
    'Administración',
    'Ajustes',
] as const;
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
        title: 'Ejercicio y control del gasto',
        href: '/requisiciones/gasto',
        icon: TrendingUp,
        permission: 'gasto-index',
    },
    {
        title: 'Requisiciones',
        icon: ClipboardCheck,
        items: [
            {
                title: 'Listado',
                href: '/requisiciones',
                permission: 'requisiciones-index',
            },
            {
                title: 'Nueva',
                href: '/requisiciones/crear',
                permission: 'requisiciones-create',
            },
            {
                title: 'Recursos Materiales',
                href: '/recursos-materiales',
                permission: 'recursos-materiales-index',
            },
            {
                title: 'Proveedores',
                href: '/catalogos/proveedores',
                permission: 'proveedores-index',
            },
            {
                title: 'Calendario de captura',
                href: '/catalogos/calendario-captura',
                permission: 'catalogos-calendario-captura-index',
            },
            {
                title: 'Modificaciones Presupuestales',
                href: '/modificaciones-presupuestales',
                permission: 'modificaciones-index',
            },
            {
                title: 'Calendario de modificaciones',
                href: '/catalogos/calendario-modificaciones',
                permission: 'catalogos-calendario-modificaciones-index',
            },
        ],
    },
    // Trámite de CLC y Facturas son entradas propias, fuera del grupo Requisiciones.
    {
        title: 'Trámite de CLC',
        href: '/clc',
        icon: FileText,
        permission: 'clc-index',
    },
    {
        title: 'Facturas',
        href: '/facturas',
        icon: Receipt,
        permission: 'facturas-index',
    },
    {
        title: 'Vehículos',
        icon: Car,
        items: [
            {
                title: 'Padrón vehicular',
                href: '/catalogos/vehiculos',
                permission: 'catalogos-vehiculos-index',
            },
            {
                title: 'Mantenimiento',
                href: '/vehiculos/mantenimientos',
                permission: 'vehiculos-mantenimiento',
            },
        ],
    },
    {
        title: 'Almacén',
        icon: Warehouse,
        items: [
            {
                title: 'Órdenes de compra',
                href: '/almacen',
                permission: 'almacen-index',
            },
            {
                title: 'Inventario',
                href: '/almacen/inventario',
                permission: 'almacen-index',
            },
        ],
    },
];

const moduleNavItems: NavItem[] = [
    {
        title: 'Catálogos',
        href: '/catalogos',
        icon: BookOpen,
        permission: 'catalogos-*',
    },
    {
        title: 'Secretarías y direcciones',
        href: '/catalogos/unidades-administrativas',
        icon: Building2,
        permission: 'catalogos-unidades-administrativas-index',
    },
];

export function AppSidebar() {
    const { url } = usePage();
    const filteredMain = filterItems(mainNavItems);
    const filteredModules = filterItems(moduleNavItems);

    const urlSection = useMemo(
        () => findActiveSection(getCurrentPath() || url),
        [url],
    );
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
                        if (
                            title === 'Requisiciones' ||
                            title === 'Almacén' ||
                            title === 'Vehículos'
                        ) {
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
                                    <SidebarMenuButton
                                        asChild
                                        tooltip={{ children: item.title }}
                                    >
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
                <NavAdmin
                    open={openSection === 'Administración'}
                    onToggle={() => toggleSection('Administración')}
                />
                <NavSettings
                    open={openSection === 'Ajustes'}
                    onToggle={() => toggleSection('Ajustes')}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
