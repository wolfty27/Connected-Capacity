import React from 'react';

const DataTable = ({ columns, data, onRowClick }) => {
    if (!data || data.length === 0) {
        return (
            <div className="p-8 border border-dashed border-slate-300 rounded-xl bg-slate-50 text-center">
                <p className="text-slate-500 font-medium">No data available.</p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden border border-slate-200 rounded-xl shadow-sm bg-white">
            <div className="overflow-x-auto">
                <table className="w-full text-sm text-left text-slate-600">
                    <thead className="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-200">
                        <tr>
                            {columns.map((col, index) => (
                                <th key={index} scope="col" className="py-4 px-6 font-semibold tracking-wider">
                                    {col.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {data.map((row, rowIndex) => (
                            <tr
                                key={rowIndex}
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
        </div>
    );
};

export default DataTable;