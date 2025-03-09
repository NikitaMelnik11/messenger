<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="card-title text-center mb-4">Create Account</h1>
                    
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> Please correct the errors below.
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= url('register') ?>" method="post">
                        <?= csrf_field() ?>
                        
                        <div class="row">
                            <!-- Full Name -->
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                    id="full_name" name="full_name" 
                                    value="<?= isset($input['full_name']) ? e($input['full_name']) : '' ?>" 
                                    autofocus required>
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Username -->
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                                        id="username" name="username" 
                                        value="<?= isset($input['username']) ? e($input['username']) : '' ?>" 
                                        required>
                                    <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['username']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text text-muted">
                                    Only letters, numbers, and underscores allowed.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                id="email" name="email" 
                                value="<?= isset($input['email']) ? e($input['email']) : '' ?>" 
                                required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <!-- Password -->
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                    id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                                <?php else: ?>
                                    <div class="form-text text-muted">
                                        Must be at least 8 characters long.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="col-md-6 mb-3">
                                <label for="password_confirm" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" 
                                    id="password_confirm" name="password_confirm" required>
                                <?php if (isset($errors['password_confirm'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Terms & Conditions -->
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>" 
                                id="terms" name="terms" value="1" 
                                <?= isset($input['terms']) && $input['terms'] ? 'checked' : '' ?>
                                required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="<?= url('terms') ?>" target="_blank">Terms of Service</a> and <a href="<?= url('privacy') ?>" target="_blank">Privacy Policy</a>
                            </label>
                            <?php if (isset($errors['terms'])): ?>
                                <div class="invalid-feedback"><?= e($errors['terms']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                        </div>
                        
                        <!-- Login Link -->
                        <div class="text-center mt-3">
                            Already have an account? <a href="<?= url('login') ?>" class="text-decoration-none">Login</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Social Registration (optional) -->
            <div class="card shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-3">Or sign up with</h5>
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