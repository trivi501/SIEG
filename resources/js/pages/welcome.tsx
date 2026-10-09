import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bienvenido" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                {auth.user && (
                    <header className="mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl">
                        <nav className="flex items-center justify-end gap-4">
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                Panel
                            </Link>
                        </nav>
                    </header>
                )}
                <div className="flex w-full flex-1 flex-col items-center justify-center gap-6 opacity-100 transition-opacity duration-750 starting:opacity-0">
                    <img
                        src="/img/Logo Ayuntamiento.png"
                        alt="Ayuntamiento"
                        className="h-[60vh] w-auto max-w-[90vw]"
                    />
                    <p className="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        Sistema Integral de Gestión Municipal
                    </p>
                    {!auth.user && (
                        <Link
                            href={login()}
                            className="inline-block rounded-sm border border-black bg-[#1b1b18] px-6 py-2 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                        >
                            Iniciar sesión
                        </Link>
                    )}
                </div>
            </div>
        </>
    );
}
