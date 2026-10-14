import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Archive,
    BellRing,
    Boxes,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    Database,
    FileClock,
    KeyRound,
    LayoutDashboard,
    MapPin,
    PackageSearch,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Settings2,
    ShieldCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Screen = 'dashboard' | 'users' | 'roles' | 'materials' | 'locations' | 'alerts' | 'audit' | 'settings';

interface NavigationItem {
    id: Screen;
    label: string;
    icon: LucideIcon;
    description: string;
}

const navigationItems: NavigationItem[] = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, description: 'System health and activity' },
    { id: 'users', label: 'Users', icon: Users, description: 'Accounts and access status' },
    { id: 'roles', label: 'Roles & permissions', icon: ShieldCheck, description: 'Screen and action access' },
    { id: 'materials', label: 'Material master data', icon: Boxes, description: 'Materials and stock rules' },
    { id: 'locations', label: 'Warehouses & categories', icon: MapPin, description: 'Locations, categories, and units' },
    { id: 'alerts', label: 'Alert settings', icon: BellRing, description: 'Expiration and low-stock rules' },
    { id: 'audit', label: 'Audit logs', icon: FileClock, description: 'Recorded sensitive actions' },
    { id: 'settings', label: 'System settings', icon: Settings2, description: 'Application preferences and status' },
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Administration', href: '/admin' }];

const users = [
    {
        name: 'Maria Santos',
        initials: 'MS',
        email: 'maria.santos@example.test',
        role: 'Warehouse Staff',
        department: 'Warehouse',
        status: 'Active',
        created: 'Sep 14, 2026',
    },
    {
        name: 'Daniel Cruz',
        initials: 'DC',
        email: 'daniel.cruz@example.test',
        role: 'Quality Assurance',
        department: 'Quality',
        status: 'Active',
        created: 'Sep 8, 2026',
    },
    {
        name: 'Aisha Malik',
        initials: 'AM',
        email: 'aisha.malik@example.test',
        role: 'System Administrator',
        department: 'IT',
        status: 'Active',
        created: 'Aug 29, 2026',
    },
    {
        name: 'Leo Tan',
        initials: 'LT',
        email: 'leo.tan@example.test',
        role: 'Production Staff',
        department: 'Production',
        status: 'Inactive',
        created: 'Aug 19, 2026',
    },
];

const materials = [
    { code: 'RM-001', name: 'Citric Acid', category: 'Food Additives', unit: 'kg', minimum: '250', shelfLife: '24 months', status: 'Active' },
    { code: 'RM-014', name: 'Sodium Benzoate', category: 'Preservatives', unit: 'kg', minimum: '100', shelfLife: '36 months', status: 'Active' },
    { code: 'RM-027', name: 'Natural Mango Flavour', category: 'Flavourings', unit: 'L', minimum: '50', shelfLife: '12 months', status: 'Active' },
    { code: 'RM-033', name: 'Tartrazine', category: 'Food Colouring', unit: 'kg', minimum: '30', shelfLife: '24 months', status: 'Inactive' },
];

const auditEntries = [
    { action: 'Updated expiration rule', record: 'Expiring-soon threshold', user: 'Aisha Malik', time: 'Today, 09:42', outcome: 'Success' },
    { action: 'Deactivated user account', record: 'Leo Tan', user: 'Aisha Malik', time: 'Yesterday, 16:18', outcome: 'Success' },
    { action: 'Failed sign-in attempt', record: 'Account: finance@example.test', user: 'Unknown', time: 'Yesterday, 15:36', outcome: 'Blocked' },
    { action: 'Added material category', record: 'Packaging Materials', user: 'Aisha Malik', time: 'Oct 7, 2026, 11:05', outcome: 'Success' },
];

function StatusBadge({ status }: { status: string }) {
    const variant = status === 'Active' || status === 'Success' ? 'default' : status === 'Inactive' || status === 'Blocked' ? 'secondary' : 'outline';

    return <Badge variant={variant}>{status}</Badge>;
}

function SectionHeading({ title, description, action }: { title: string; description: string; action?: React.ReactNode }) {
    return (
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                <p className="text-muted-foreground mt-1 text-sm">{description}</p>
            </div>
            {action}
        </div>
    );
}

function MetricCard({
    label,
    value,
    detail,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: string;
    detail: string;
    icon: LucideIcon;
    tone?: 'default' | 'warning' | 'success';
}) {
    const toneClasses = {
        default: 'bg-primary/10 text-primary',
        warning: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    };

    return (
        <Card>
            <CardContent className="p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-sm font-medium">{label}</p>
                        <p className="mt-2 text-3xl font-semibold tracking-tight">{value}</p>
                        <p className="text-muted-foreground mt-1 text-xs">{detail}</p>
                    </div>
                    <div className={`rounded-lg p-2.5 ${toneClasses[tone]}`}>
                        <Icon className="size-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function DashboardScreen() {
    return (
        <div className="space-y-6">
            <SectionHeading title="Administrator dashboard" description="Monitor account access, system activity, and prototype services." />

            <div className="border-primary/30 bg-primary/5 text-muted-foreground rounded-lg border border-dashed px-4 py-3 text-sm">
                <span className="text-foreground font-medium">Simulated prototype data.</span> Counts, activity, and service status below are
                fictional and for the capstone demonstration only.
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Registered users" value="48" detail="44 active accounts" icon={Users} />
                <MetricCard label="Failed sign-ins" value="3" detail="In the past 24 hours" icon={CircleAlert} tone="warning" />
                <MetricCard label="System activity" value="126" detail="Events recorded today" icon={Activity} tone="success" />
                <MetricCard label="Data services" value="Operational" detail="API and database status" icon={Database} tone="success" />
            </div>

            <div className="grid gap-6 xl:grid-cols-5">
                <Card className="xl:col-span-3">
                    <CardHeader>
                        <CardTitle className="text-base">Recent system activity</CardTitle>
                        <CardDescription>Important account and configuration events.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {auditEntries.map((entry) => (
                            <div key={`${entry.action}-${entry.time}`} className="flex items-center gap-3 border-b pb-4 last:border-0 last:pb-0">
                                <div className="bg-muted rounded-full p-2">
                                    <FileClock className="text-muted-foreground size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">{entry.action}</p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {entry.record} · {entry.user}
                                    </p>
                                </div>
                                <span className="text-muted-foreground text-xs whitespace-nowrap">{entry.time}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base">Security attention</CardTitle>
                        <CardDescription>Items that should be reviewed by an administrator.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <div className="flex gap-2">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300" />
                                <div>
                                    <p className="text-sm font-medium">Three failed sign-in attempts</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Review the audit log before taking account action.</p>
                                </div>
                            </div>
                        </div>
                        <div className="rounded-lg border p-3">
                            <div className="flex gap-2">
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                <div>
                                    <p className="text-sm font-medium">Last alert check completed</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Today at 09:30 · next scheduled check at 10:00.</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function UsersScreen() {
    const [searchTerm, setSearchTerm] = useState('');
    const filteredUsers = useMemo(
        () => users.filter((user) => `${user.name} ${user.email} ${user.role}`.toLowerCase().includes(searchTerm.toLowerCase())),
        [searchTerm],
    );

    return (
        <div className="space-y-6">
            <SectionHeading
                title="User management"
                description="Create, update, activate, and deactivate administrator-approved accounts. Passwords are never shown here."
                action={
                    <Button>
                        <Plus /> Add user
                    </Button>
                }
            />
            <Card>
                <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="text-base">User accounts</CardTitle>
                        <CardDescription>48 registered users · 44 currently active</CardDescription>
                    </div>
                    <div className="relative w-full sm:w-72">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search users"
                            className="pl-9"
                        />
                    </div>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full min-w-[55rem] text-left text-sm">
                        <thead className="bg-muted/40 text-muted-foreground border-y text-xs tracking-wide uppercase">
                            <tr>
                                <th className="px-3 py-3 font-medium">User</th>
                                <th className="px-3 py-3 font-medium">Role</th>
                                <th className="px-3 py-3 font-medium">Department</th>
                                <th className="px-3 py-3 font-medium">Status</th>
                                <th className="px-3 py-3 font-medium">Created</th>
                                <th className="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {filteredUsers.map((user) => (
                                <tr key={user.email} className="border-b last:border-0">
                                    <td className="px-3 py-4">
                                        <div className="flex items-center gap-3">
                                            <span className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-full text-xs font-semibold">
                                                {user.initials}
                                            </span>
                                            <div>
                                                <p className="font-medium">{user.name}</p>
                                                <p className="text-muted-foreground text-xs">{user.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-3 py-4">{user.role}</td>
                                    <td className="px-3 py-4">{user.department}</td>
                                    <td className="px-3 py-4">
                                        <StatusBadge status={user.status} />
                                    </td>
                                    <td className="text-muted-foreground px-3 py-4">{user.created}</td>
                                    <td className="px-3 py-4 text-right">
                                        <Button variant="ghost" size="sm">
                                            <Pencil /> Edit
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    );
}

function RolesScreen() {
    const roles = [
        'Warehouse Staff',
        'Procurement Staff',
        'Production Staff',
        'Quality Assurance',
        'Finance Staff',
        'Management',
        'System Administrator',
    ];
    const permissions = [
        'View inventory',
        'Record receipts',
        'Manage purchases',
        'Inspect / release',
        'Reports / analytics',
        'Manage users / roles',
        'Configure alert rules',
    ];

    return (
        <div className="space-y-6">
            <SectionHeading
                title="Role & permission management"
                description="Configure which role can view, create, edit, approve, delete, or export information."
                action={
                    <Button>
                        <Plus /> Add role
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Permission matrix</CardTitle>
                    <CardDescription>Baseline access follows the inventory age and expiration prototype plan.</CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full min-w-[62rem] text-sm">
                        <thead className="bg-muted/40 text-muted-foreground border-y text-xs tracking-wide uppercase">
                            <tr>
                                <th className="bg-muted/40 sticky left-0 px-3 py-3 text-left font-medium">Feature</th>
                                {roles.map((role) => (
                                    <th key={role} className="px-3 py-3 text-center font-medium">
                                        {role}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {permissions.map((permission) => (
                                <tr key={permission} className="border-b last:border-0">
                                    <td className="bg-background sticky left-0 px-3 py-3 font-medium">{permission}</td>
                                    {roles.map((role) => (
                                        <td key={role} className="px-3 py-3 text-center">
                                            <Checkbox
                                                defaultChecked={
                                                    role === 'System Administrator' ||
                                                    (permission === 'View inventory' && role !== 'System Administrator') ||
                                                    (permission === 'Reports / analytics' && role !== 'System Administrator') ||
                                                    (permission === 'Record receipts' && role === 'Warehouse Staff') ||
                                                    (permission === 'Manage purchases' && role === 'Procurement Staff') ||
                                                    (permission === 'Inspect / release' && role === 'Quality Assurance')
                                                }
                                                aria-label={`${role}: ${permission}`}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
            <div className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                <KeyRound className="mr-2 inline size-4" />
                Changes to high-risk access should be recorded in audit logs and require your configured approval policy.
            </div>
        </div>
    );
}

function MaterialsScreen() {
    return (
        <div className="space-y-6">
            <SectionHeading
                title="Material master data"
                description="Maintain the master record separately from inventory batches and their shelf-life information."
                action={
                    <Button>
                        <Plus /> Add material
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Materials</CardTitle>
                    <CardDescription>All figures are simulated prototype data.</CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full min-w-[58rem] text-left text-sm">
                        <thead className="bg-muted/40 text-muted-foreground border-y text-xs tracking-wide uppercase">
                            <tr>
                                <th className="px-3 py-3 font-medium">Code</th>
                                <th className="px-3 py-3 font-medium">Material</th>
                                <th className="px-3 py-3 font-medium">Category</th>
                                <th className="px-3 py-3 font-medium">Unit</th>
                                <th className="px-3 py-3 font-medium">Minimum stock</th>
                                <th className="px-3 py-3 font-medium">Default shelf life</th>
                                <th className="px-3 py-3 font-medium">Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {materials.map((material) => (
                                <tr key={material.code} className="border-b last:border-0">
                                    <td className="px-3 py-4 font-mono text-xs">{material.code}</td>
                                    <td className="px-3 py-4 font-medium">{material.name}</td>
                                    <td className="px-3 py-4">{material.category}</td>
                                    <td className="px-3 py-4">{material.unit}</td>
                                    <td className="px-3 py-4">
                                        {material.minimum} {material.unit}
                                    </td>
                                    <td className="px-3 py-4">{material.shelfLife}</td>
                                    <td className="px-3 py-4">
                                        <StatusBadge status={material.status} />
                                    </td>
                                    <td className="px-3 py-4 text-right">
                                        <Button variant="ghost" size="sm">
                                            <Pencil /> Edit
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    );
}

function LocationsScreen() {
    return (
        <div className="space-y-6">
            <SectionHeading
                title="Warehouse & category settings"
                description="Set up warehouses, storage locations, material categories, and units of measurement."
            />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader className="flex-row items-start justify-between">
                        <div>
                            <CardTitle className="text-base">Warehouses & storage locations</CardTitle>
                            <CardDescription>Locations available when recording inventory batches.</CardDescription>
                        </div>
                        <Button size="sm">
                            <Plus /> Add location
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {[
                            ['Main warehouse', 'WH-01', 'Active'],
                            ['Dry storage', 'WH-01-DS', 'Active'],
                            ['Quality hold area', 'WH-01-QH', 'Active'],
                            ['Overflow bay', 'WH-02-OB', 'Inactive'],
                        ].map(([name, code, status]) => (
                            <div key={code} className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">{name}</p>
                                    <p className="text-muted-foreground text-xs">{code}</p>
                                </div>
                                <StatusBadge status={status} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-start justify-between">
                        <div>
                            <CardTitle className="text-base">Categories & units</CardTitle>
                            <CardDescription>Reusable values that keep master data consistent.</CardDescription>
                        </div>
                        <Button size="sm">
                            <Plus /> Add category
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div>
                            <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">Material categories</p>
                            <div className="flex flex-wrap gap-2">
                                {['Food Additives', 'Preservatives', 'Flavourings', 'Food Colouring', 'Packaging Materials'].map((category) => (
                                    <Badge key={category} variant="secondary">
                                        {category}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">Units of measurement</p>
                            <div className="flex flex-wrap gap-2">
                                {['kg', 'g', 'L', 'mL', 'pcs'].map((unit) => (
                                    <Badge key={unit} variant="outline">
                                        {unit}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function AlertsScreen() {
    const [expiringSoonEnabled, setExpiringSoonEnabled] = useState(true);
    const [lowStockEnabled, setLowStockEnabled] = useState(true);

    return (
        <div className="space-y-6">
            <SectionHeading
                title="Expiration & alert settings"
                description="Set prototype thresholds, recipients, notification channels, and alert-check timing."
                action={<Button>Save changes</Button>}
            />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Expiration rules</CardTitle>
                        <CardDescription>Thresholds are project settings, not company policy.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <Label htmlFor="expiration-alert">Expiring-soon alerts</Label>
                                <p className="text-muted-foreground text-xs">Notify when a batch reaches the configured period.</p>
                            </div>
                            <Checkbox
                                id="expiration-alert"
                                checked={expiringSoonEnabled}
                                onCheckedChange={(checked) => setExpiringSoonEnabled(checked === true)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="expiration-days">Expiring-soon threshold</Label>
                            <div className="flex items-center gap-2">
                                <Input id="expiration-days" defaultValue="30" disabled={!expiringSoonEnabled} />
                                <span className="text-muted-foreground text-sm">days before expiry</span>
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="check-frequency">Alert-check frequency</Label>
                            <Input id="check-frequency" defaultValue="Every 30 minutes" />
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Low-stock & notifications</CardTitle>
                        <CardDescription>Use each material’s minimum stock level unless a specific override is needed.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <Label htmlFor="low-stock-alert">Low-stock alerts</Label>
                                <p className="text-muted-foreground text-xs">Notify relevant users when usable stock is below minimum.</p>
                            </div>
                            <Checkbox
                                id="low-stock-alert"
                                checked={lowStockEnabled}
                                onCheckedChange={(checked) => setLowStockEnabled(checked === true)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="alert-recipients">Alert recipients</Label>
                            <Input id="alert-recipients" defaultValue="Warehouse, QA, Management" />
                        </div>
                        <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                            <Checkbox defaultChecked /> Send in-app notifications
                        </label>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function AuditScreen() {
    const [searchTerm, setSearchTerm] = useState('');
    const entries = useMemo(
        () => auditEntries.filter((entry) => `${entry.action} ${entry.record} ${entry.user}`.toLowerCase().includes(searchTerm.toLowerCase())),
        [searchTerm],
    );

    return (
        <div className="space-y-6">
            <SectionHeading
                title="Audit logs"
                description="Review recorded sensitive actions. Audit history should not be silently altered or erased."
                action={
                    <Button variant="outline">
                        <Archive /> Export log
                    </Button>
                }
            />
            <Card>
                <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="text-base">Activity history</CardTitle>
                        <CardDescription>User, action, affected record, time, and outcome.</CardDescription>
                    </div>
                    <div className="relative w-full sm:w-72">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search audit logs"
                            className="pl-9"
                        />
                    </div>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full min-w-200 text-left text-sm">
                        <thead className="bg-muted/40 text-muted-foreground border-y text-xs tracking-wide uppercase">
                            <tr>
                                <th className="px-3 py-3 font-medium">Action</th>
                                <th className="px-3 py-3 font-medium">Affected record</th>
                                <th className="px-3 py-3 font-medium">User</th>
                                <th className="px-3 py-3 font-medium">Date / time</th>
                                <th className="px-3 py-3 font-medium">Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.map((entry) => (
                                <tr key={`${entry.action}-${entry.time}`} className="border-b last:border-0">
                                    <td className="px-3 py-4 font-medium">{entry.action}</td>
                                    <td className="px-3 py-4">{entry.record}</td>
                                    <td className="px-3 py-4">{entry.user}</td>
                                    <td className="text-muted-foreground px-3 py-4">{entry.time}</td>
                                    <td className="px-3 py-4">
                                        <StatusBadge status={entry.outcome} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    );
}

function SettingsScreen() {
    return (
        <div className="space-y-6">
            <SectionHeading
                title="System settings"
                description="Configure general application preferences and review the prototype service status."
                action={<Button>Save changes</Button>}
            />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">General preferences</CardTitle>
                        <CardDescription>These values govern how dates, notifications, and exports are presented.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="application-name">Application name</Label>
                            <Input id="application-name" defaultValue="Inventory Ageing & Expiration Monitor" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="date-format">Date format</Label>
                            <Input id="date-format" defaultValue="DD MMM YYYY" />
                        </div>
                        <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                            <Checkbox defaultChecked /> Include a simulated-data label in prototype exports
                        </label>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Service & data status</CardTitle>
                        <CardDescription>Read-only operational information for this prototype.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {[
                            [Database, 'Database', 'Connected'],
                            [RefreshCw, 'Last backup check', 'Today, 08:00'],
                            [PackageSearch, 'Application version', 'Prototype v0.1'],
                        ].map(([Icon, label, value]) => {
                            const ServiceIcon = Icon as LucideIcon;
                            return (
                                <div key={label as string} className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <ServiceIcon className="text-muted-foreground size-4" />
                                        <span className="text-sm font-medium">{label as string}</span>
                                    </div>
                                    <span className="text-muted-foreground text-sm">{value as string}</span>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export default function Administration() {
    const [currentScreen, setCurrentScreen] = useState<Screen>('dashboard');
    const selectedNavigation = navigationItems.find((item) => item.id === currentScreen) ?? navigationItems[0];

    const screens: Record<Screen, React.ReactNode> = {
        dashboard: <DashboardScreen />,
        users: <UsersScreen />,
        roles: <RolesScreen />,
        materials: <MaterialsScreen />,
        locations: <LocationsScreen />,
        alerts: <AlertsScreen />,
        audit: <AuditScreen />,
        settings: <SettingsScreen />,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Administration" />
            <div className="bg-muted/20 flex min-h-full flex-1 flex-col lg:flex-row">
                <aside className="bg-background border-b lg:w-70 lg:shrink-0 lg:border-r lg:border-b-0">
                    <div className="border-b px-4 py-4">
                        <div className="flex items-center gap-2">
                            <span className="bg-primary text-primary-foreground rounded-lg p-2">
                                <ShieldCheck className="size-4" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold">Administration</p>
                                <p className="text-muted-foreground text-xs">System control center</p>
                            </div>
                        </div>
                    </div>
                    <nav className="flex gap-1 overflow-x-auto p-3 lg:flex-col" aria-label="Administrator screens">
                        {navigationItems.map((item) => {
                            const Icon = item.icon;
                            const isActive = item.id === currentScreen;
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => setCurrentScreen(item.id)}
                                    className={`flex min-w-max items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm transition-colors lg:min-w-0 ${isActive ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                >
                                    <Icon className="size-4 shrink-0" />
                                    <span>{item.label}</span>
                                    {isActive && <ChevronRight className="ml-auto hidden size-4 lg:block" />}
                                </button>
                            );
                        })}
                    </nav>
                </aside>
                <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                    <div className="mx-auto max-w-7xl">{screens[selectedNavigation.id]}</div>
                </main>
            </div>
        </AppLayout>
    );
}
