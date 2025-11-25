import React, { useState, useMemo } from 'react';

/**
 * DataTable - Reusable table component with sort, filter, and pagination
 *
 * Per FE-001: Shared component for all tabular data displays
 *
 * @param {Array} columns - Column definitions with header, accessor, sortable, render
 * @param {Array} data - Table data array
 * @param {Function} onRowClick - Optional row click handler
 * @param {boolean} loading - Show loading state
 * @param {string} emptyMessage - Message when no data
 * @param {boolean} pagination - Enable pagination
 * @param {number} pageSize - Rows per page (default 10)
 * @param {boolean} searchable - Enable global search
 * @param {string} searchPlaceholder - Search input placeholder
 */
const DataTable = ({
    columns,
    data,
    onRowClick,
    loading = false,
    emptyMessage = 'No data available.',
    pagination = false,
    pageSize = 10,
    searchable = false,
    searchPlaceholder = 'Search...',
    className = '',
}) => {
    const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });
    const [currentPage, setCurrentPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState('');

    // Handle sorting
    const handleSort = (accessor) => {
        const column = columns.find(col => col.accessor === accessor);
        if (!column?.sortable) return;

        setSortConfig(prev => ({
            key: accessor,
            direction: prev.key === accessor && prev.direction === 'asc' ? 'desc' : 'asc',
        }));
    };

    // Filter and sort data
    const processedData = useMemo(() => {
        let result = [...(data || [])];

        // Apply search filter
        if (searchTerm && searchable) {
            const term = searchTerm.toLowerCase();
            result = result.filter(row =>
                columns.some(col => {
                    const value = row[col.accessor];
                    return value && String(value).toLowerCase().includes(term);
                })
            );
        }

        // Apply sorting
        if (sortConfig.key) {
            result.sort((a, b) => {
                const aVal = a[sortConfig.key];
                const bVal = b[sortConfig.key];

                if (aVal === bVal) return 0;
                if (aVal === null || aVal === undefined) return 1;
                if (bVal === null || bVal === undefined) return -1;

                const comparison = aVal < bVal ? -1 : 1;
                return sortConfig.direction === 'asc' ? comparison : -comparison;
            });
        }

        return result;
    }, [data, sortConfig, searchTerm, searchable, columns]);

    // Pagination
    const totalPages = Math.ceil(processedData.length / pageSize);
    const paginatedData = pagination
        ? processedData.slice((currentPage - 1) * pageSize, currentPage * pageSize)
        : processedData;

    // Reset to first page when data changes
    React.useEffect(() => {
        setCurrentPage(1);
    }, [data, searchTerm]);

    // Sort indicator
    const SortIcon = ({ accessor }) => {
        if (sortConfig.key !== accessor) {
            return (
                <svg className="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
            );
        }
        return sortConfig.direction === 'asc' ? (
            <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
            </svg>
        ) : (
            <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
            </svg>
        );
    };

    // Loading state
    if (loading) {
        return (
            <div className="p-8 border border-slate-200 rounded-xl bg-white text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                <p className="text-slate-500 mt-3">Loading...</p>
            </div>
        );
    }

    // Empty state
    if (!data || data.length === 0) {
        return (
            <div className="p-8 border border-dashed border-slate-300 rounded-xl bg-slate-50 text-center">
                <svg className="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p className="text-slate-500 font-medium">{emptyMessage}</p>
            </div>
        );
    }

    return (
        <div className={`bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden ${className}`}>
            {/* Search bar */}
            {searchable && (
                <div className="p-4 border-b border-slate-100">
                    <div className="relative">
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="text"
                            placeholder={searchPlaceholder}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        {searchTerm && (
                            <button
                                onClick={() => setSearchTerm('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="w-full text-sm text-left text-slate-600">
                    <thead className="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-200">
                        <tr>
                            {columns.map((col, index) => (
                                <th
                                    key={index}
                                    scope="col"
                                    className={`py-4 px-6 font-semibold tracking-wider ${col.sortable ? 'cursor-pointer select-none hover:bg-slate-100' : ''}`}
                                    onClick={() => col.sortable && handleSort(col.accessor)}
                                >
                                    <div className="flex items-center gap-2">
                                        {col.header}
                                        {col.sortable && <SortIcon accessor={col.accessor} />}
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {paginatedData.map((row, rowIndex) => (
                            <tr
                                key={row.id || rowIndex}
                                className={`bg-white hover:bg-slate-50 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
                                onClick={() => onRowClick && onRowClick(row)}
                            >
                                {columns.map((col, colIndex) => (
                                    <td key={colIndex} className="py-4 px-6 whitespace-nowrap">
                                        {col.render ? col.render(row) : row[col.accessor]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {pagination && totalPages > 1 && (
                <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                    <div className="text-sm text-slate-500">
                        Showing {((currentPage - 1) * pageSize) + 1} to {Math.min(currentPage * pageSize, processedData.length)} of {processedData.length} results
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                            disabled={currentPage === 1}
                            className="px-3 py-1.5 text-sm border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <div className="flex items-center gap-1">
                            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                                let page;
                                if (totalPages <= 5) {
                                    page = i + 1;
                                } else if (currentPage <= 3) {
                                    page = i + 1;
                                } else if (currentPage >= totalPages - 2) {
                                    page = totalPages - 4 + i;
                                } else {
                                    page = currentPage - 2 + i;
                                }
                                return (
                                    <button
                                        key={page}
                                        onClick={() => setCurrentPage(page)}
                                        className={`w-8 h-8 text-sm rounded-lg ${currentPage === page
                                            ? 'bg-blue-500 text-white'
                                            : 'hover:bg-slate-100 text-slate-600'
                                            }`}
                                    >
                                        {page}
                                    </button>
                                );
                            })}
                        </div>
                        <button
                            onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                            disabled={currentPage === totalPages}
                            className="px-3 py-1.5 text-sm border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default DataTable;
