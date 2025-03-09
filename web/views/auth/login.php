<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="card-title text-center mb-4">Login</h1>
                    
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> Please correct the errors below.
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= url('login') ?>" method="post">
                        <?= csrf_field() ?>
                        
                        <!-- Username or Email -->
                        <div class="mb-3">
                            <label for="username_or_email" class="form-label">Username or Email</label>
                            <input type="text" class="form-control <?= isset($errors['username_or_email']) ? 'is-invalid' : '' ?>" 
                                id="username_or_email" name="username_or_email" 
                                value="<?= isset($input['username_or_email']) ? e($input['username_or_email']) : '' ?>" 
                                autofocus required>
                            <?php if (isset($errors['username_or_email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['username_or_email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                id="password" name="password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Remember Me -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1" 
                                <?= isset($input['remember']) && $input['remember'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <!-- Hidden Redirect URL -->
                        <input type="hidden" name="redirect" value="<?= isset($redirectUrl) ? e($redirectUrl) : 'feed' ?>">
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                        
                        <!-- Links -->
                        <div class="text-center">
                            <a href="<?= url('forgot-password') ?>" class="text-decoration-none">Forgot password?</a>
                            <div class="mt-3">
                                Don't have an account? <a href="<?= url('register') ?>" class="text-decoration-none">Sign up</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Social Login (optional) -->
            <div class="card shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-3">Or login with</h5>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary">
                            <i class="bi bi-facebook me-2"></i> Facebook
                        </button>
                        <button type="button" class="btn btn-outline-danger">
                            <i class="bi bi-google me-2"></i> Google
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 