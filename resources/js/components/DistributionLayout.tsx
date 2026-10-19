import Navbar from '@/components/Navbar';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type DistributionLayoutProps = {
    user?: { name?: string; role?: string };
    title: string;
    description: string;
    children: ReactNode;
};

const links = [
    { label: 'Overview', routeName: 'distribution.overview' },
    { label: 'Availability reports', routeName: 'distribution.reports' },
    { label: 'Notifications', routeName: 'distribution.notifications' },
];

export default function DistributionLayout({ user, title, description, children }: DistributionLayoutProps) {
    return (
        <div className="min-h-screen bg-slate-50 font-sans">
            <Navbar user={user} station="North Hub - Zone A" />
            <div className="border-b border-slate-200 bg-white">
                <nav className="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-6 py-3 md:px-8" aria-label="Distribution navigation">
                    {links.map((link) => (
                        <Link
                            key={link.routeName}
                            href={route(link.routeName)}
                            className="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>
            </div>
            <main className="mx-auto max-w-7xl p-6 md:p-8">
                <header className="mb-6">
                    <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                </header>
                <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    Read-only distribution view. Availability, risks, and notices come from current inventory and procurement records.
                </div>
                {children}
            </main>
        </div>
    );
}
