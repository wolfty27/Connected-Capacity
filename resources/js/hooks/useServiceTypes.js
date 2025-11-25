import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

/**
 * useServiceTypes - Hook to fetch service types from API
 *
 * Per SC-003: Replaces hardcoded careBundleConstants.js with API data
 *
 * @param {Object} options - Configuration options
 * @param {boolean} options.activeOnly - Only fetch active service types
 * @param {string} options.category - Filter by category
 * @returns {Object} { serviceTypes, byCategory, categories, loading, error, refetch }
 */
const useServiceTypes = (options = {}) => {
    const { activeOnly = true, category = null } = options;

    const [serviceTypes, setServiceTypes] = useState([]);
    const [byCategory, setByCategory] = useState({});
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchServiceTypes = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams();
            if (activeOnly) params.append('active', 'true');
            if (category) params.append('category', category);

            const [typesRes, categoriesRes] = await Promise.all([
                axios.get(`/api/v2/service-types?${params}`),
                axios.get('/api/v2/service-types/categories'),
            ]);

            // Transform API data to match expected format for CareBundleWizard
            const transformedTypes = (typesRes.data.data || []).map((st) => ({
                id: String(st.id),
                category: mapCategory(st.category),
                name: st.name,
                code: st.code,
                description: st.description || '',
                defaultFrequency: st.default_frequency || 1,
                defaultDuration: st.default_duration_weeks || 12,
                currentFrequency: 0,
                currentDuration: st.default_duration_weeks || 12,
                provider: '',
                costPerVisit: st.cost_per_visit || 0,
                costCode: st.cost_code || `COST-${st.code}`,
                costDriver: st.cost_driver || 'Per Visit Rate',
                // Keep original API data for reference
                _api: st,
            }));

            setServiceTypes(transformedTypes);

            // Group by category
            const grouped = transformedTypes.reduce((acc, service) => {
                const cat = service.category;
                if (!acc[cat]) acc[cat] = [];
                acc[cat].push(service);
                return acc;
            }, {});
            setByCategory(grouped);

            setCategories(categoriesRes.data.data || []);
        } catch (err) {
            console.error('Failed to fetch service types:', err);
            setError(err.response?.data?.message || 'Failed to load service types');

            // Fallback to empty arrays
            setServiceTypes([]);
            setByCategory({});
            setCategories([]);
        } finally {
            setLoading(false);
        }
    }, [activeOnly, category]);

    useEffect(() => {
        fetchServiceTypes();
    }, [fetchServiceTypes]);

    return {
        serviceTypes,
        byCategory,
        categories,
        loading,
        error,
        refetch: fetchServiceTypes,
    };
};

/**
 * Map API category names to internal category keys
 * Maps database category names (from CoreDataSeeder) to frontend category keys
 *
 * Categories from CoreDataSeeder:
 * - CLINICAL: "Clinical Services"
 * - PERSONAL: "Personal Support & Daily Living"
 * - SAFETY: "Safety, Monitoring & Technology"
 * - LOGISTICS: "Logistics & Access Services"
 */
function mapCategory(apiCategory) {
    const mapping = {
        // Database category names from CoreDataSeeder
        'Clinical Services': 'CLINICAL',
        'Personal Support & Daily Living': 'PERSONAL_SUPPORT',
        'Safety, Monitoring & Technology': 'SAFETY_TECH',
        'Logistics & Access Services': 'LOGISTICS',
        // Handle category codes from seeder
        'CLINICAL': 'CLINICAL',
        'PERSONAL': 'PERSONAL_SUPPORT',
        'SAFETY': 'SAFETY_TECH',
        'LOGISTICS': 'LOGISTICS',
        // Handle already-mapped internal keys
        'PERSONAL_SUPPORT': 'PERSONAL_SUPPORT',
        'SAFETY_TECH': 'SAFETY_TECH',
    };
    return mapping[apiCategory] || 'CLINICAL';
}

/**
 * Transform services back to API format for submission
 */
export function transformServicesForApi(services) {
    return services
        .filter((s) => (s.currentFrequency || 0) > 0)
        .map((s) => ({
            service_type_id: parseInt(s.id, 10) || s._api?.id,
            frequency_per_week: s.currentFrequency,
            duration_weeks: s.currentDuration,
            provider_organization_id: s.provider || null,
            notes: s.notes || null,
        }));
}

/**
 * Calculate monthly cost from services array
 */
export function calculateMonthlyCost(services) {
    return services.reduce((total, service) => {
        const frequency = service.currentFrequency || 0;
        const costPerVisit = service.costPerVisit || 0;
        // Monthly = frequency per week * 4 weeks * cost per visit
        return total + frequency * 4 * costPerVisit;
    }, 0);
}

export default useServiceTypes;
