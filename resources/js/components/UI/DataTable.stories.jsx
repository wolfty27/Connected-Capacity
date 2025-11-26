import DataTable from './DataTable';

/**
 * DataTable component for displaying tabular data.
 * Features sorting, pagination, search, and custom cell rendering.
 */
export default {
    title: 'UI/DataTable',
    component: DataTable,
    tags: ['autodocs'],
    argTypes: {
        pagination: {
            control: 'boolean',
            description: 'Enable pagination',
        },
        searchable: {
            control: 'boolean',
            description: 'Enable search',
        },
        loading: {
            control: 'boolean',
            description: 'Show loading state',
        },
        onRowClick: {
            action: 'row clicked',
            description: 'Row click handler',
        },
    },
};

const sampleColumns = [
    { header: 'Name', accessor: 'name', sortable: true },
    { header: 'Status', accessor: 'status', sortable: true },
    { header: 'Role', accessor: 'role', sortable: true },
    { header: 'Date', accessor: 'date', sortable: true },
];

const sampleData = [
    { id: 1, name: 'John Smith', status: 'Active', role: 'Nurse', date: '2025-01-15' },
    { id: 2, name: 'Mary Johnson', status: 'Active', role: 'PSW', date: '2025-01-14' },
    { id: 3, name: 'Robert Brown', status: 'On Leave', role: 'Coordinator', date: '2025-01-13' },
    { id: 4, name: 'Lisa Davis', status: 'Active', role: 'Nurse', date: '2025-01-12' },
    { id: 5, name: 'Michael Wilson', status: 'Inactive', role: 'PSW', date: '2025-01-11' },
];

const largeData = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    name: `User ${i + 1}`,
    status: ['Active', 'Inactive', 'On Leave'][i % 3],
    role: ['Nurse', 'PSW', 'Coordinator', 'Admin'][i % 4],
    date: `2025-01-${String(15 - (i % 15)).padStart(2, '0')}`,
}));

export const Basic = {
    args: {
        columns: sampleColumns,
        data: sampleData,
    },
};

export const WithPagination = {
    args: {
        columns: sampleColumns,
        data: largeData,
        pagination: true,
        pageSize: 10,
    },
};

export const WithSearch = {
    args: {
        columns: sampleColumns,
        data: sampleData,
        searchable: true,
        searchPlaceholder: 'Search by name, status, or role...',
    },
};

export const FullFeatured = {
    args: {
        columns: sampleColumns,
        data: largeData,
        pagination: true,
        pageSize: 10,
        searchable: true,
        searchPlaceholder: 'Search...',
    },
};

export const Loading = {
    args: {
        columns: sampleColumns,
        data: [],
        loading: true,
    },
};

export const Empty = {
    args: {
        columns: sampleColumns,
        data: [],
        emptyMessage: 'No patients found matching your criteria.',
    },
};

export const CustomRendering = {
    args: {
        columns: [
            { header: 'Name', accessor: 'name', sortable: true },
            {
                header: 'Status',
                accessor: 'status',
                sortable: true,
                render: (row) => {
                    const colors = {
                        Active: 'bg-emerald-100 text-emerald-800',
                        Inactive: 'bg-slate-100 text-slate-800',
                        'On Leave': 'bg-amber-100 text-amber-800',
                    };
                    return (
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${colors[row.status]}`}>
                            {row.status}
                        </span>
                    );
                },
            },
            { header: 'Role', accessor: 'role', sortable: true },
            {
                header: 'Actions',
                accessor: 'actions',
                render: (row) => (
                    <div className="flex gap-2">
                        <button className="text-blue-600 hover:underline text-sm">Edit</button>
                        <button className="text-rose-600 hover:underline text-sm">Delete</button>
                    </div>
                ),
            },
        ],
        data: sampleData,
    },
};

export const PatientTable = {
    args: {
        columns: [
            { header: 'Patient', accessor: 'name', sortable: true },
            {
                header: 'MAPLe',
                accessor: 'maple',
                sortable: true,
                render: (row) => {
                    const colors = {
                        1: 'text-emerald-600',
                        2: 'text-emerald-500',
                        3: 'text-amber-500',
                        4: 'text-orange-500',
                        5: 'text-rose-600',
                    };
                    return (
                        <span className={`font-bold ${colors[row.maple]}`}>
                            {row.maple}
                        </span>
                    );
                },
            },
            {
                header: 'Status',
                accessor: 'status',
                sortable: true,
                render: (row) => (
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                        row.status === 'Active' ? 'bg-emerald-100 text-emerald-800' :
                        row.status === 'Bundle Building' ? 'bg-amber-100 text-amber-800' :
                        'bg-slate-100 text-slate-800'
                    }`}>
                        {row.status}
                    </span>
                ),
            },
            { header: 'Care Bundle', accessor: 'bundle', sortable: true },
            { header: 'Last Visit', accessor: 'lastVisit', sortable: true },
        ],
        data: [
            { id: 1, name: 'Margaret Thompson', maple: 4, status: 'Active', bundle: 'High Intensity', lastVisit: '2025-01-20' },
            { id: 2, name: 'William Chen', maple: 3, status: 'Active', bundle: 'Standard', lastVisit: '2025-01-19' },
            { id: 3, name: 'Dorothy Miller', maple: 5, status: 'Active', bundle: 'Dementia Care', lastVisit: '2025-01-20' },
            { id: 4, name: 'James Wilson', maple: 2, status: 'Bundle Building', bundle: '-', lastVisit: '-' },
            { id: 5, name: 'Helen Davis', maple: 4, status: 'Active', bundle: 'High Intensity', lastVisit: '2025-01-18' },
        ],
        searchable: true,
        searchPlaceholder: 'Search patients...',
    },
};
