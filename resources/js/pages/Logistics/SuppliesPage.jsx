import React, { useState } from 'react';
import Section from '../../components/UI/Section';
import Button from '../../components/UI/Button';
import Card from '../../components/UI/Card';

const SuppliesPage = () => {
    const [orders, setOrders] = useState([]);
    const [selectedSupply, setSelectedSupply] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [fundingType, setFundingType] = useState('SPO'); // 'SPO' or 'LHIN'

    const availableSupplies = [
        { id: 'dressing', name: 'Wound Dressing Kit', spoCost: 15, lhinCost: 0 },
        { id: 'gloves', name: 'Examination Gloves (Box)', spoCost: 20, lhinCost: 0 },
        { id: 'catheter', name: 'Foley Catheter Kit', spoCost: 50, lhinCost: 25 },
        { id: 'bp_monitor', name: 'Blood Pressure Monitor', spoCost: 80, lhinCost: 0 },
        { id: 'wheelchair', name: 'Lightweight Wheelchair', spoCost: 300, lhinCost: 150 },
    ];

    const handlePlaceOrder = () => {
        if (!selectedSupply || quantity <= 0) {
            alert('Please select a supply and quantity.');
            return;
        }

        const supply = availableSupplies.find(s => s.id === selectedSupply);
        if (supply) {
            const newOrder = {
                id: orders.length + 1,
                supply: supply.name,
                quantity,
                funding: fundingType,
                cost: fundingType === 'SPO' ? supply.spoCost * quantity : supply.lhinCost * quantity,
                status: 'Pending',
                orderDate: new Date().toLocaleDateString(),
            };
            setOrders([...orders, newOrder]);
            alert(`Order placed for ${quantity} x ${supply.name}.`);
            setSelectedSupply('');
            setQuantity(1);
            setFundingType('SPO');
        }
    };

    return (
        <div className="space-y-6 max-w-4xl mx-auto py-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Equipment & Supplies Management</h1>
                    <p className="text-slate-500 text-sm">Order and track medical equipment and supplies for patients.</p>
                </div>
            </div>

            <Card title="Place New Order">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="supply" className="block text-sm font-medium text-slate-700">Select Supply</label>
                        <select
                            id="supply"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring focus:ring-teal-500 focus:ring-opacity-50"
                            value={selectedSupply}
                            onChange={(e) => setSelectedSupply(e.target.value)}
                        >
                            <option value="">-- Select --</option>
                            {availableSupplies.map(supply => (
                                <option key={supply.id} value={supply.id}>{supply.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="quantity" className="block text-sm font-medium text-slate-700">Quantity</label>
                        <input
                            type="number"
                            id="quantity"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring focus:ring-teal-500 focus:ring-opacity-50"
                            value={quantity}
                            onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                            min="1"
                        />
                    </div>
                </div>

                <div className="mt-4">
                    <label className="block text-sm font-medium text-slate-700">Funding Type</label>
                    <div className="mt-1 flex items-center space-x-4">
                        <label className="inline-flex items-center">
                            <input
                                type="radio"
                                className="form-radio text-teal-600 focus:ring-teal-500"
                                name="fundingType"
                                value="SPO"
                                checked={fundingType === 'SPO'}
                                onChange={() => setFundingType('SPO')}
                            />
                            <span className="ml-2 text-slate-700">SPO-Funded</span>
                        </label>
                        <label className="inline-flex items-center">
                            <input
                                type="radio"
                                className="form-radio text-teal-600 focus:ring-teal-500"
                                name="fundingType"
                                value="LHIN"
                                checked={fundingType === 'LHIN'}
                                onChange={() => setFundingType('LHIN')}
                            />
                            <span className="ml-2 text-slate-700">LHIN-Funded</span>
                        </label>
                    </div>
                </div>

                <div className="mt-6 flex justify-end">
                    <Button onClick={handlePlaceOrder} disabled={!selectedSupply || quantity <= 0}>
                        Place Order
                    </Button>
                </div>
            </Card>

            <Section title="Pending Orders">
                {orders.length === 0 ? (
                    <p className="text-sm text-slate-500">No pending orders.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Order ID</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Supply</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Qty</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Funding</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Cost</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Order Date</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-slate-200">
                                {orders.map(order => (
                                    <tr key={order.id}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">#{order.id}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">{order.supply}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">{order.quantity}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                                order.funding === 'SPO' ? 'bg-indigo-100 text-indigo-800' : 'bg-green-100 text-green-800'
                                            }`}>
                                                {order.funding}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">${order.cost.toFixed(2)}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                {order.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{order.orderDate}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Section>
        </div>
    );
};

export default SuppliesPage;