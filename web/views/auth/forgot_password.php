<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="card-title text-center mb-2">Forgot Password</h1>
                    <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>
                    
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> Please correct the errors below.
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= url('forgot-password') ?>" method="post">
                        <?= csrf_field() ?>
                        
                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                id="email" name="email" 
                                value="<?= isset($input['email']) ? e($input['email']) : '' ?>" 
                                autofocus required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
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