function renderAccountOptions() {
    return Object.keys(rolePermissions)
        .map(role => `<option value="${escapeHTML(role)}">${escapeHTML(role)}</option>`)
        .join("");
}

function renderAuthMessage(message, className = "auth-message") {
    return message ? `<p class="${className}" role="status">${escapeHTML(message)}</p>` : "";
}

function renderSignupPage(message = "") {
    return `
        <div class="login-shell">
            <section class="login-card" aria-labelledby="authTitle">
                <div class="login-header">
                    <p class="eyebrow">CREATE YOUR WORKSPACE</p>
                    <h2 id="authTitle">Sign up</h2>
                    <p>Create an account and choose your role. Your role determines the workspace you see after signing in.</p>
                </div>
                ${renderAuthMessage(message)}
                <p id="authError" class="auth-error" role="alert" hidden></p>
                <form id="signupForm" class="login-form">
                    <label>
                        <span>Full name</span>
                        <input name="fullName" type="text" autocomplete="name" minlength="2" maxlength="80" required>
                    </label>
                    <label>
                        <span>Email address</span>
                        <input name="email" type="email" autocomplete="email" maxlength="255" required>
                    </label>
                    <label>
                        <span>Choose your role</span>
                        <select name="role" autocomplete="organization-title" required>
                            <option value="" selected disabled>Select a role</option>
                            ${renderAccountOptions()}
                        </select>
                    </label>
                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label>
                        <span>Confirm password</span>
                        <input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button type="submit" class="primary-button login-button">Create account</button>
                </form>
                <p class="auth-switch">Already have an account? <button type="button" data-auth-mode="login">Sign in</button></p>
                <p class="auth-storage-note">Your account and role are saved in the warehouse database.</p>
            </section>
        </div>
    `;
}

function renderLoginPage(message = "", email = "") {
    return `
        <div class="login-shell">
            <section class="login-card" aria-labelledby="authTitle">
                <div class="login-header">
                    <p class="eyebrow">SECURE ACCESS</p>
                    <h2 id="authTitle">Welcome back</h2>
                    <p>Sign in to continue to your assigned role workspace.</p>
                </div>
                ${renderAuthMessage(message)}
                <p id="authError" class="auth-error" role="alert" hidden></p>
                <form id="loginForm" class="login-form">
                    <label>
                        <span>Email address</span>
                        <input name="email" type="email" value="${escapeHTML(email)}" autocomplete="email" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <button type="submit" class="primary-button login-button">Sign in</button>
                </form>
                <p class="auth-switch">New to the workspace? <button type="button" data-auth-mode="signup">Create an account</button></p>
                <p class="auth-storage-note">Sign in with the email and password saved to your warehouse account.</p>
            </section>
        </div>
    `;
}

export function renderSignupScreen(message = '') {
    return renderSignupPage(message);
}

export function renderLoginScreen(message = '', email = '') {
    return renderLoginPage(message, email);
}
