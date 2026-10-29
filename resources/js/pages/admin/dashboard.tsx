import AdminLayout from '@/layouts/admin-layout';
import { LayoutDashboard } from 'lucide-react';

export default function Dashboard() {
    return (
        <AdminLayout title="Dashboard">
            {/* Empty state until admin sections (catalogue, orders, settings) are built. */}
            <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                <LayoutDashboard className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                <p className="mt-3 text-sm text-muted">Admin tools will appear here as they're added.</p>
            </div>
        </AdminLayout>
    );
}
