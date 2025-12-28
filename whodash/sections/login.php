<div class="container">
    <section id="authSection" class="tab-content">
        <!-- Login card -->
        <div id="loginCard">
            <h1>Login</h1>
            <form id="loginForm">
                <input type="text" name="username" required placeholder="Username" />
                <input type="password" name="password" required placeholder="Password" />
                <button type="submit">Login</button>
            </form>
            <div id="loginError" class="muted"></div>
            <p>
                <button type="button" data-action="show-register">Create Account</button>
            </p>
        </div>
        <!-- Register card (hidden by default) -->
        <div id="registerCard" class="hidden">
            <h1>Register</h1>
            <form id="registerForm">
                <input type="text" name="username" required placeholder="Username" />
                <input type="password" name="password" required placeholder="Password" />
                <input type="password" name="confirm_password" required placeholder="Confirm Password" />
                <button type="submit">Register</button>
                <button type="button" data-action="show-login">Back</button>
            </form>
            <div id="registerError" class="muted"></div>
        </div>
    </section>
</div>