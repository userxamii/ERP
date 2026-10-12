export function renderSignupScreen(message = '') {
    if (typeof window.renderSignupPage === 'function') {
        return window.renderSignupPage(message);
    }

    return '';
}

export function renderLoginScreen(message = '', email = '') {
    if (typeof window.renderLoginPage === 'function') {
        return window.renderLoginPage(message, email);
    }

    return '';
}
