// API Integration JavaScript

const API_CONFIG = {
    baseURL: '/api/v1',
    endpoints: {
        auth: {
            login: '/auth/login',
            register: '/auth/register',
            logout: '/auth/logout',
            verify: '/auth/verify'
        },
        user: {
            profile: '/user/profile',
            update: '/user/update',
            settings: '/user/settings'
        },
        applications: {
            list: '/applications',
            create: '/applications/create',
            update: '/applications/update',
            delete: '/applications/delete',
            track: '/applications/track'
        },
        payments: {
            create: '/payments/create',
            verify: '/payments/verify',
            history: '/payments/history'
        },
        appointments: {
            list: '/appointments',
            create: '/appointments/create',
            update: '/appointments/update',
            cancel: '/appointments/cancel'
        },
        notifications: {
            list: '/notifications',
            markRead: '/notifications/read',
            markAllRead: '/notifications/read-all'
        },
        admin: {
            users: '/admin/users',
            employees: '/admin/employees',
            services: '/admin/services',
            fees: '/admin/fees',
            reports: '/admin/reports',
            settings: '/admin/settings'
        }
    }
};

// HTTP Client
class ApiClient {
    constructor() {
        this.baseURL = API_CONFIG.baseURL;
        this.token = sessionStorage.getItem('token') || null;
    }

    setToken(token) {
        this.token = token;
        if (token) {
            sessionStorage.setItem('token', token);
        } else {
            sessionStorage.removeItem('token');
        }
    }

    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        return headers;
    }

    async request(endpoint, method = 'GET', data = null) {
        const url = `${this.baseURL}${endpoint}`;
        const options = {
            method,
            headers: this.getHeaders()
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'حدث خطأ في الطلب');
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Auth Methods
    async login(username, password) {
        const response = await this.request(API_CONFIG.endpoints.auth.login, 'POST', {
            username,
            password
        });
        if (response.token) {
            this.setToken(response.token);
        }
        return response;
    }

    async register(userData) {
        return this.request(API_CONFIG.endpoints.auth.register, 'POST', userData);
    }

    async logout() {
        await this.request(API_CONFIG.endpoints.auth.logout, 'POST');
        this.setToken(null);
    }

    async verifyToken() {
        return this.request(API_CONFIG.endpoints.auth.verify, 'GET');
    }

    // User Methods
    async getUserProfile() {
        return this.request(API_CONFIG.endpoints.user.profile, 'GET');
    }

    async updateUserProfile(data) {
        return this.request(API_CONFIG.endpoints.user.update, 'PUT', data);
    }

    // Application Methods
    async getApplications(params = {}) {
        const query = new URLSearchParams(params).toString();
        const endpoint = `${API_CONFIG.endpoints.applications.list}${query ? `?${query}` : ''}`;
        return this.request(endpoint, 'GET');
    }

    async createApplication(data) {
        return this.request(API_CONFIG.endpoints.applications.create, 'POST', data);
    }

    async updateApplication(id, data) {
        return this.request(`${API_CONFIG.endpoints.applications.update}/${id}`, 'PUT', data);
    }

    async deleteApplication(id) {
        return this.request(`${API_CONFIG.endpoints.applications.delete}/${id}`, 'DELETE');
    }

    async trackApplication(id) {
        return this.request(`${API_CONFIG.endpoints.applications.track}/${id}`, 'GET');
    }

    // Payment Methods
    async createPayment(data) {
        return this.request(API_CONFIG.endpoints.payments.create, 'POST', data);
    }

    async verifyPayment(id) {
        return this.request(`${API_CONFIG.endpoints.payments.verify}/${id}`, 'GET');
    }

    async getPaymentHistory() {
        return this.request(API_CONFIG.endpoints.payments.history, 'GET');
    }

    // Appointment Methods
    async getAppointments() {
        return this.request(API_CONFIG.endpoints.appointments.list, 'GET');
    }

    async createAppointment(data) {
        return this.request(API_CONFIG.endpoints.appointments.create, 'POST', data);
    }

    async updateAppointment(id, data) {
        return this.request(`${API_CONFIG.endpoints.appointments.update}/${id}`, 'PUT', data);
    }

    async cancelAppointment(id) {
        return this.request(`${API_CONFIG.endpoints.appointments.cancel}/${id}`, 'POST');
    }

    // Notification Methods
    async getNotifications() {
        return this.request(API_CONFIG.endpoints.notifications.list, 'GET');
    }

    async markNotificationRead(id) {
        return this.request(`${API_CONFIG.endpoints.notifications.markRead}/${id}`, 'POST');
    }

    async markAllNotificationsRead() {
        return this.request(API_CONFIG.endpoints.notifications.markAllRead, 'POST');
    }

    // Admin Methods
    async getUsers() {
        return this.request(API_CONFIG.endpoints.admin.users, 'GET');
    }

    async getEmployees() {
        return this.request(API_CONFIG.endpoints.admin.employees, 'GET');
    }

    async getServices() {
        return this.request(API_CONFIG.endpoints.admin.services, 'GET');
    }

    async updateSettings(data) {
        return this.request(API_CONFIG.endpoints.admin.settings, 'PUT', data);
    }
}

// Create and export API instance
const api = new ApiClient();

// Helper function for making API calls with error handling
async function apiCall(fn, ...args) {
    try {
        const result = await fn(...args);
        return { success: true, data: result };
    } catch (error) {
        showNotification(error.message || 'حدث خطأ في الاتصال', 'error');
        return { success: false, error: error.message };
    }
}

// Export
window.api = api;
window.apiCall = apiCall;