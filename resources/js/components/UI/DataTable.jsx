import React from 'react';

const DataTable = ({ columns, data }) => {
    if (!data || data.length === 0) {
        return (
            <div className="p-4 border border-gray-200 rounded-lg bg-gray-50 text-center">
                <p className="text-gray-500 italic">No data available.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto relative shadow-md sm:rounded-lg">
            <table className="w-full text-sm text-left text-gray-500">
                <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        {columns.map((col, index) => (
                            <th key={index} scope="col" className="py-3 px-6">
                                {col.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, rowIndex) => (
                        <tr key={rowIndex} className="bg-white border-b hover:bg-gray-50">
                            {columns.map((col, colIndex) => (
                                <td key={colIndex} className="py-4 px-6">
                                    {row[col.accessor]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default DataTable;