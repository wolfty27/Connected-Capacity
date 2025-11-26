import React, { useEffect, useState } from 'react';
import api from '../../services/api';
import Section from '../../components/UI/Section';
import Card from '../../components/UI/Card';
import Input from '../../components/UI/Input';
import Button from '../../components/UI/Button';
import Select from '../../components/UI/Select';
import Spinner from '../../components/UI/Spinner';

const ProfilePage = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [org, setOrg] = useState({
        name: '',
        type: 'se_health',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
        regions: '',
        capabilities: []
    });
    const [capabilityOptions, setCapabilityOptions] = useState({});
    const [message, setMessage] = useState(null);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        fetchOrg();
    }, []);

    const fetchOrg = async () => {
        try {
            const response = await api.get('/api/organization');
            const data = response.data.organization;
            const options = response.data.capabilityOptions;
            
            setCapabilityOptions(options);
            setOrg({
                name: data.name || '',
                type: data.type || 'se_health',
                contact_name: data.contact_name || '',
                contact_email: data.contact_email || '',
                contact_phone: data.contact_phone || '',
                regions: Array.isArray(data.regions) ? data.regions.join('\n') : (data.regions || ''),
                capabilities: data.capabilities || []
            });
        } catch (error) {
            console.error('Failed to fetch organization:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setOrg(prev => ({ ...prev, [name]: value }));
    };

    const handleCapabilityChange = (key) => {
        setOrg(prev => {
            const caps = prev.capabilities.includes(key)
                ? prev.capabilities.filter(c => c !== key)
                : [...prev.capabilities, key];
            return { ...prev, capabilities: caps };
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);
        setErrors({});

        try {
            await api.put('/api/organization', org);
            setMessage({ type: 'success', text: 'Profile updated successfully.' });
        } catch (error) {
            if (error.response && error.response.status === 422) {
                setErrors(error.response.data.errors);
            } else {
                setMessage({ type: 'error', text: 'Failed to update profile.' });
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="p-12 flex justify-center"><Spinner /></div>;

    return (
        <Section title="Organization Profile">
            <Card>
                {message && (
                    <div className={`p-4 mb-4 rounded ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {message.text}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Input
                            label="Organization Name"
                            name="name"
                            value={org.name}
                            onChange={handleChange}
                            error={errors.name}
                            required
                        />

                        <div className="flex flex-col">
                            <label className="mb-1 text-sm font-medium text-gray-700">Type</label>
                            <select
                                name="type"
                                value={org.type}
                                onChange={handleChange}
                                className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="se_health">SE Health</option>
                                <option value="partner">Partner</option>
                                <option value="external">External</option>
                            </select>
                            {errors.type && <span className="text-red-500 text-sm mt-1">{errors.type[0]}</span>}
                        </div>

                        <Input
                            label="Contact Name"
                            name="contact_name"
                            value={org.contact_name}
                            onChange={handleChange}
                            error={errors.contact_name}
                        />

                        <Input
                            label="Contact Email"
                            name="contact_email"
                            type="email"
                            value={org.contact_email}
                            onChange={handleChange}
                            error={errors.contact_email}
                        />

                        <Input
                            label="Contact Phone"
                            name="contact_phone"
                            value={org.contact_phone}
                            onChange={handleChange}
                            error={errors.contact_phone}
                        />
                    </div>

                    <div>
                        <label className="block mb-1 text-sm font-medium text-gray-700">Regions (One per line)</label>
                        <textarea
                            name="regions"
                            rows="4"
                            value={org.regions}
                            onChange={handleChange}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                        {errors.regions && <span className="text-red-500 text-sm mt-1">{errors.regions[0]}</span>}
                    </div>

                    <div>
                        <label className="block mb-2 text-sm font-medium text-gray-700">Capabilities</label>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {Object.entries(capabilityOptions).map(([key, label]) => (
                                <div key={key} className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id={`cap-${key}`}
                                        checked={org.capabilities.includes(key)}
                                        onChange={() => handleCapabilityChange(key)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor={`cap-${key}`} className="ml-2 block text-sm text-gray-900">
                                        {label}
                                    </label>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={saving}>
                            {saving ? 'Saving...' : 'Save Profile'}
                        </Button>
                    </div>
                </form>
            </Card>
        </Section>
    );
};

export default ProfilePage;
