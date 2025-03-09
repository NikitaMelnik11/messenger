<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="card-title text-center mb-2">Reset Password</h1>
                    <p class="text-center text-muted mb-4">Enter your new password below.</p>
                    
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> Please correct the errors below.
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= url('reset-password') ?>" method="post">
                        <?= csrf_field() ?>
                        
                        <!-- Hidden Token -->
                        <input type="hidden" name="token" value="<?= isset($token) ? e($token) : '' ?>">
                        
                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                id="password" name="password" 
                                autofocus required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                            <?php else: ?>
                                <div class="form-text text-muted">
                                    Must be at least 8 characters long.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" 
                                id="password_confirm" name="password_confirm" 
                                required>
                            <?php if (isset($errors['password_confirm'])): ?>
                                <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                        </div>
                        
                        <!-- Back to Login Link -->
                        <div class="text-center mt-3">
                            <a href="<?= url('login') ?>" class="text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div> 