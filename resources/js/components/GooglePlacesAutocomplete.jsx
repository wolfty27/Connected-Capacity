import React, { useEffect, useRef, useState, useCallback } from 'react';
import { MapPin, X, AlertCircle } from 'lucide-react';

/**
 * GooglePlacesAutocomplete - Address input with Google Places Autocomplete
 *
 * Uses the Google Maps Places API to provide address suggestions.
 * On selection, extracts:
 * - formatted_address (full address string)
 * - postal_code
 * - city
 * - lat/lng coordinates
 *
 * Environment: Requires VITE_GOOGLE_MAPS_API_KEY to be set.
 *
 * @param {Object} props
 * @param {function} props.onPlaceSelect - Callback when a place is selected: (placeData) => void
 * @param {string} props.value - Current address value for controlled input
 * @param {function} props.onChange - Called when input value changes (controlled)
 * @param {string} props.placeholder - Input placeholder text
 * @param {boolean} props.disabled - Whether input is disabled
 * @param {string} props.error - Error message to display
 * @param {string} props.className - Additional CSS classes
 */
const GooglePlacesAutocomplete = ({
    onPlaceSelect,
    value = '',
    onChange,
    placeholder = 'Start typing an address...',
    disabled = false,
    error = null,
    className = '',
}) => {
    const inputRef = useRef(null);
    const autocompleteRef = useRef(null);
    const [isLoaded, setIsLoaded] = useState(false);
    const [loadError, setLoadError] = useState(null);

    // Load Google Maps script
    useEffect(() => {
        const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

        // Skip if no API key (will use manual entry mode)
        if (!apiKey) {
            console.warn('VITE_GOOGLE_MAPS_API_KEY not set. Address autocomplete disabled.');
            setLoadError('Google Maps API key not configured. Please enter address manually.');
            return;
        }

        // Check if already loaded
        if (window.google?.maps?.places) {
            setIsLoaded(true);
            return;
        }

        // Check if script is already being loaded
        const existingScript = document.querySelector('script[src*="maps.googleapis.com"]');
        if (existingScript) {
            existingScript.addEventListener('load', () => setIsLoaded(true));
            return;
        }

        // Load the script
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
        script.async = true;
        script.defer = true;

        script.onload = () => setIsLoaded(true);
        script.onerror = () => setLoadError('Failed to load Google Maps. Please enter address manually.');

        document.head.appendChild(script);

        return () => {
            // Cleanup if needed
        };
    }, []);

    // Initialize autocomplete when Google Maps is loaded
    useEffect(() => {
        if (!isLoaded || !inputRef.current || autocompleteRef.current) return;

        try {
            autocompleteRef.current = new window.google.maps.places.Autocomplete(inputRef.current, {
                // Restrict to Canadian addresses
                componentRestrictions: { country: 'ca' },
                // Request specific fields to minimize billing
                fields: [
                    'formatted_address',
                    'geometry',
                    'address_components',
                    'name',
                ],
                // Focus on addresses (not businesses)
                types: ['address'],
            });

            // Listen for place selection
            autocompleteRef.current.addListener('place_changed', handlePlaceChanged);
        } catch (err) {
            console.error('Error initializing Places Autocomplete:', err);
            setLoadError('Error initializing address autocomplete.');
        }
    }, [isLoaded]);

    // Handle place selection
    const handlePlaceChanged = useCallback(() => {
        if (!autocompleteRef.current) return;

        const place = autocompleteRef.current.getPlace();

        if (!place || !place.geometry) {
            console.warn('No geometry data for selected place');
            return;
        }

        // Extract address components
        const addressData = {
            formatted_address: place.formatted_address || '',
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng(),
            city: '',
            postal_code: '',
            street_number: '',
            street_name: '',
            province: '',
        };

        // Parse address components
        if (place.address_components) {
            for (const component of place.address_components) {
                const types = component.types;

                if (types.includes('street_number')) {
                    addressData.street_number = component.long_name;
                }
                if (types.includes('route')) {
                    addressData.street_name = component.long_name;
                }
                if (types.includes('locality')) {
                    addressData.city = component.long_name;
                }
                if (types.includes('administrative_area_level_1')) {
                    addressData.province = component.short_name;
                }
                if (types.includes('postal_code')) {
                    addressData.postal_code = component.long_name;
                }
            }
        }

        // Construct street address
        addressData.address = [addressData.street_number, addressData.street_name]
            .filter(Boolean)
            .join(' ');

        // Call callback with extracted data
        if (onPlaceSelect) {
            onPlaceSelect(addressData);
        }

        // Update controlled input value
        if (onChange) {
            onChange({ target: { value: place.formatted_address || '' } });
        }
    }, [onPlaceSelect, onChange]);

    // Handle manual input changes
    const handleInputChange = (e) => {
        if (onChange) {
            onChange(e);
        }
    };

    // Clear the input
    const handleClear = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }
        if (onChange) {
            onChange({ target: { value: '' } });
        }
        if (onPlaceSelect) {
            onPlaceSelect(null);
        }
    };

    const hasError = error || loadError;
    const showClearButton = value && !disabled;

    return (
        <div className={`relative ${className}`}>
            <div className="relative">
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <MapPin className={`w-5 h-5 ${hasError ? 'text-rose-400' : 'text-slate-400'}`} />
                </div>
                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={handleInputChange}
                    placeholder={placeholder}
                    disabled={disabled}
                    className={`
                        w-full pl-10 pr-10 py-2 border rounded-lg
                        focus:ring-2 focus:ring-teal-200 focus:border-teal-400
                        disabled:bg-slate-100 disabled:cursor-not-allowed
                        ${hasError ? 'border-rose-300' : 'border-slate-300'}
                    `}
                    autoComplete="off"
                />
                {showClearButton && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                    >
                        <X className="w-4 h-4" />
                    </button>
                )}
            </div>

            {/* Error or warning message */}
            {hasError && (
                <div className="mt-1 flex items-center gap-1 text-sm text-rose-600">
                    <AlertCircle className="w-4 h-4" />
                    <span>{error || loadError}</span>
                </div>
            )}

            {/* Hint text when API is available */}
            {isLoaded && !hasError && !value && (
                <p className="mt-1 text-xs text-slate-500">
                    Start typing to see address suggestions
                </p>
            )}
        </div>
    );
};

export default GooglePlacesAutocomplete;
