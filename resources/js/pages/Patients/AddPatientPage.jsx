import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../services/api';
import Card from '../../components/UI/Card';
import Button from '../../components/UI/Button';
import Section from '../../components/UI/Section';
import GooglePlacesAutocomplete from '../../components/GooglePlacesAutocomplete';
import { UserPlus, ArrowLeft, AlertCircle, MapPin } from 'lucide-react';

/**
 * AddPatientPage - Form to create a new patient
 *
 * Creates a new patient with minimal required fields and
 * optionally adds them to the intake queue.
 *
 * Address field uses Google Places Autocomplete to:
 * - Provide address suggestions
 * - Extract postal code for region auto-assignment
 * - Get lat/lng for travel time calculations
 */
const AddPatientPage = () => {
    const navigate = useNavigate();
    const [formData, setFormData] = useState({
        first_name: '',
        last_name: '',
        email: '',
        gender: '',
        date_of_birth: '',
        ohip: '',
        // Address fields for travel time and region assignment
        address: '',
        city: '',
        postal_code: '',
        lat: null,
        lng: null,
        add_to_queue: true,
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(null);
    const [addressDisplay, setAddressDisplay] = useState('');

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        // Clear error for this field
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: null }));
        }
    };

    // Handle Google Places selection
    const handlePlaceSelect = (placeData) => {
        if (!placeData) {
            // Clear address fields
            setFormData(prev => ({
                ...prev,
                address: '',
                city: '',
                postal_code: '',
                lat: null,
                lng: null,
            }));
            setAddressDisplay('');
            return;
        }

        setFormData(prev => ({
            ...prev,
            address: placeData.address || placeData.formatted_address || '',
            city: placeData.city || '',
            postal_code: placeData.postal_code || '',
            lat: placeData.lat || null,
            lng: placeData.lng || null,
        }));
        setAddressDisplay(placeData.formatted_address || '');

        // Clear address error if any
        if (errors.address) {
            setErrors(prev => ({ ...prev, address: null }));
        }
    };

    const validateForm = () => {
        const newErrors = {};

        if (!formData.first_name.trim()) {
            newErrors.first_name = 'First name is required';
        }
        if (!formData.last_name.trim()) {
            newErrors.last_name = 'Last name is required';
        }
        if (!formData.email.trim()) {
            newErrors.email = 'Email is required';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Please enter a valid email address';
        }
        if (!formData.gender) {
            newErrors.gender = 'Gender is required';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitError(null);

        if (!validateForm()) {
            return;
        }

        try {
            setSubmitting(true);

            const response = await api.post('/patients', {
                name: `${formData.first_name} ${formData.last_name}`,
                email: formData.email,
                gender: formData.gender,
                date_of_birth: formData.date_of_birth || null,
                ohip: formData.ohip || null,
                // Include address data for region assignment and travel time
                address: formData.address || null,
                city: formData.city || null,
                postal_code: formData.postal_code || null,
                lat: formData.lat,
                lng: formData.lng,
                add_to_queue: formData.add_to_queue,
            });

            // Navigate to the new patient's detail page
            navigate(`/patients/${response.data.data.id}`);
        } catch (error) {
            console.error('Failed to create patient:', error);
            const errorMessage = error.response?.data?.error ||
                error.response?.data?.message ||
                'Failed to create patient. Please try again.';
            setSubmitError(errorMessage);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Section
            title="Add New Patient"
            description="Enter patient information to create a new record"
            actions={
                <Button variant="outline" onClick={() => navigate('/patients')}>
                    <ArrowLeft className="w-4 h-4 mr-2" />
                    Back to Patients
                </Button>
            }
        >
            <div className="max-w-2xl">
                <Card>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Error Banner */}
                        {submitError && (
                            <div className="p-4 bg-rose-50 border border-rose-200 rounded-lg flex items-start gap-3">
                                <AlertCircle className="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-rose-800">Error creating patient</p>
                                    <p className="text-sm text-rose-600 mt-1">{submitError}</p>
                                </div>
                            </div>
                        )}

                        {/* Name Fields */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="first_name" className="block text-sm font-medium text-slate-700 mb-1">
                                    First Name <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    value={formData.first_name}
                                    onChange={handleChange}
                                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400 ${
                                        errors.first_name ? 'border-rose-300' : 'border-slate-300'
                                    }`}
                                    placeholder="Enter first name"
                                />
                                {errors.first_name && (
                                    <p className="mt-1 text-sm text-rose-600">{errors.first_name}</p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="last_name" className="block text-sm font-medium text-slate-700 mb-1">
                                    Last Name <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    value={formData.last_name}
                                    onChange={handleChange}
                                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400 ${
                                        errors.last_name ? 'border-rose-300' : 'border-slate-300'
                                    }`}
                                    placeholder="Enter last name"
                                />
                                {errors.last_name && (
                                    <p className="mt-1 text-sm text-rose-600">{errors.last_name}</p>
                                )}
                            </div>
                        </div>

                        {/* Email */}
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1">
                                Email <span className="text-rose-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value={formData.email}
                                onChange={handleChange}
                                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400 ${
                                    errors.email ? 'border-rose-300' : 'border-slate-300'
                                }`}
                                placeholder="patient@example.com"
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-rose-600">{errors.email}</p>
                            )}
                        </div>

                        {/* Address Section */}
                        <div className="border-t border-slate-200 pt-6">
                            <h3 className="text-sm font-medium text-slate-700 mb-4 flex items-center gap-2">
                                <MapPin className="w-4 h-4" />
                                Home Address
                                <span className="text-xs font-normal text-slate-500">(for care scheduling)</span>
                            </h3>

                            {/* Google Places Autocomplete */}
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Search Address
                                </label>
                                <GooglePlacesAutocomplete
                                    value={addressDisplay}
                                    onChange={(e) => setAddressDisplay(e.target.value)}
                                    onPlaceSelect={handlePlaceSelect}
                                    placeholder="Start typing address..."
                                    error={errors.address}
                                />
                            </div>

                            {/* Manual address fields (shown when address is selected or for fallback) */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="col-span-2">
                                    <label htmlFor="address" className="block text-sm font-medium text-slate-700 mb-1">
                                        Street Address
                                    </label>
                                    <input
                                        type="text"
                                        id="address"
                                        name="address"
                                        value={formData.address}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400"
                                        placeholder="Enter street address"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="city" className="block text-sm font-medium text-slate-700 mb-1">
                                        City
                                    </label>
                                    <input
                                        type="text"
                                        id="city"
                                        name="city"
                                        value={formData.city}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400"
                                        placeholder="City"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="postal_code" className="block text-sm font-medium text-slate-700 mb-1">
                                        Postal Code
                                    </label>
                                    <input
                                        type="text"
                                        id="postal_code"
                                        name="postal_code"
                                        value={formData.postal_code}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400"
                                        placeholder="M5G 1X8"
                                    />
                                </div>
                            </div>

                            {/* Show coordinates if available */}
                            {formData.lat && formData.lng && (
                                <p className="mt-2 text-xs text-slate-500">
                                    Coordinates: {formData.lat.toFixed(4)}, {formData.lng.toFixed(4)}
                                </p>
                            )}
                        </div>

                        {/* Gender */}
                        <div>
                            <label htmlFor="gender" className="block text-sm font-medium text-slate-700 mb-1">
                                Gender <span className="text-rose-500">*</span>
                            </label>
                            <select
                                id="gender"
                                name="gender"
                                value={formData.gender}
                                onChange={handleChange}
                                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400 bg-white ${
                                    errors.gender ? 'border-rose-300' : 'border-slate-300'
                                }`}
                            >
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            {errors.gender && (
                                <p className="mt-1 text-sm text-rose-600">{errors.gender}</p>
                            )}
                        </div>

                        {/* Date of Birth */}
                        <div>
                            <label htmlFor="date_of_birth" className="block text-sm font-medium text-slate-700 mb-1">
                                Date of Birth
                            </label>
                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                value={formData.date_of_birth}
                                onChange={handleChange}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400"
                            />
                        </div>

                        {/* OHIP */}
                        <div>
                            <label htmlFor="ohip" className="block text-sm font-medium text-slate-700 mb-1">
                                OHIP Number
                            </label>
                            <input
                                type="text"
                                id="ohip"
                                name="ohip"
                                value={formData.ohip}
                                onChange={handleChange}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-200 focus:border-teal-400"
                                placeholder="Enter OHIP number (optional)"
                            />
                        </div>

                        {/* Add to Queue Checkbox */}
                        <div className="pt-2">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="add_to_queue"
                                    checked={formData.add_to_queue}
                                    onChange={handleChange}
                                    className="mt-1 w-4 h-4 text-teal-600 border-slate-300 rounded focus:ring-teal-500"
                                />
                                <div>
                                    <span className="text-sm font-medium text-slate-700">Add to Intake Queue</span>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        When enabled, the patient will be added to the intake queue for InterRAI HC assessment.
                                        If disabled, the patient will be created as an active patient.
                                    </p>
                                </div>
                            </label>
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => navigate('/patients')}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={submitting}
                                className="flex items-center gap-2"
                            >
                                <UserPlus className="w-4 h-4" />
                                {submitting ? 'Creating...' : 'Create Patient'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </Section>
    );
};

export default AddPatientPage;
