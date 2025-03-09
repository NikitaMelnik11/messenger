<?php
// Set variables for layout
$hideHeader = true;
$hideFooter = false;
$bodyClass = 'landing-page';
$title = 'Welcome';
$description = 'Connect with vegans around the world, share vegan recipes, find vegan events, and join vegan communities.';
?>

<!-- Hero Section -->
<section class="hero bg-success text-white">
    <div class="container py-5">
        <div class="row align-items-center py-5">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h1 class="display-3 fw-bold mb-3">Welcome to Vegan Messenger</h1>
                <p class="lead mb-4">Connect with vegans around the world, share plant-based recipes, find vegan events, and join communities that support a compassionate lifestyle.</p>
                <div class="d-grid d-md-flex gap-3">
                    <a href="<?= url('register') ?>" class="btn btn-light btn-lg px-4">Join Now</a>
                    <a href="<?= url('login') ?>" class="btn btn-outline-light btn-lg px-4">Sign In</a>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="<?= asset('img/hero-image.png') ?>" alt="Vegan Messenger" class="img-fluid rounded shadow-lg">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features py-5">
    <div class="container py-5">
        <h2 class="text-center mb-5">What We Offer</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-gradient text-white rounded-circle mb-3">
                            <i class="bi bi-people-fill fs-2"></i>
                        </div>
                        <h3>Connect</h3>
                        <p class="text-muted">Connect with like-minded individuals who share your passion for veganism and sustainable living.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-gradient text-white rounded-circle mb-3">
                            <i class="bi bi-chat-dots-fill fs-2"></i>
                        </div>
                        <h3>Share</h3>
                        <p class="text-muted">Share your vegan journey, recipes, tips, and experiences with a supportive community.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-gradient text-white rounded-circle mb-3">
                            <i class="bi bi-calendar-event-fill fs-2"></i>
                        </div>
                        <h3>Discover</h3>
                        <p class="text-muted">Discover vegan events, restaurants, products, and resources in your area and around the world.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Community Section -->
<section class="community bg-light py-5">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="<?= asset('img/community.png') ?>" alt="Vegan Community" class="img-fluid rounded shadow">
            </div>
            <div class="col-lg-6">
                <h2 class="mb-4">Join Our Global Community</h2>
                <p class="lead mb-4">Connect with thousands of vegans worldwide who are making a difference every day through their lifestyle choices.</p>
                <ul class="list-unstyled">
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Join interesting groups and discussions</li>
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Share and discover delicious vegan recipes</li>
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Find vegan events and meetups near you</li>
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Get support on your vegan journey</li>
                </ul>
                <a href="<?= url('register') ?>" class="btn btn-success btn-lg mt-3">Get Started</a>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials py-5">
    <div class="container py-5">
        <h2 class="text-center mb-5">What Our Users Say</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <img src="<?= asset('img/testimonial-1.jpg') ?>" alt="User" class="rounded-circle me-3" width="60" height="60">
                            <div>
                                <h5 class="mb-1">Sarah Johnson</h5>
                                <p class="text-muted mb-0">Vegan for 3 years</p>
                            </div>
                        </div>
                        <p class="mb-0">"Vegan Messenger has been an incredible resource for me. I've found amazing recipes, connected with wonderful people, and feel supported in my vegan journey."</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <img src="<?= asset('img/testimonial-2.jpg') ?>" alt="User" class="rounded-circle me-3" width="60" height="60">
                            <div>
                                <h5 class="mb-1">Michael Chen</h5>
                                <p class="text-muted mb-0">Vegan since birth</p>
                            </div>
                        </div>
                        <p class="mb-0">"I love the sense of community here. It's so easy to connect with other vegans and share experiences. The events feature has helped me find local meetups I wouldn't have known about otherwise."</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <img src="<?= asset('img/testimonial-3.jpg') ?>" alt="User" class="rounded-circle me-3" width="60" height="60">
                            <div>
                                <h5 class="mb-1">Emma Rodriguez</h5>
                                <p class="text-muted mb-0">New to veganism</p>
                            </div>
                        </div>
                        <p class="mb-0">"As someone who recently transitioned to veganism, this platform has been a lifesaver! The support and resources I've found here have made my journey so much easier."</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta bg-success text-white py-5">
    <div class="container py-5 text-center">
        <h2 class="display-5 fw-bold mb-3">Ready to Join Our Community?</h2>
        <p class="lead mb-4">Connect with like-minded individuals and start your journey today.</p>
        <div class="d-grid d-md-flex justify-content-center gap-3">
            <a href="<?= url('register') ?>" class="btn btn-light btn-lg px-4">Sign Up Now</a>
            <a href="<?= url('login') ?>" class="btn btn-outline-light btn-lg px-4">Login</a>
        </div>
    </div>
</section>

<!-- CSS Styles for Landing Page -->
<?php ob_start(); ?>
<style>
    .hero {
        padding-top: 80px;
        padding-bottom: 80px;
    }
    
    .feature-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 70px;
        height: 70px;
    }
    
    @media (min-width: 992px) {
        .hero {
            padding-top: 120px;
            padding-bottom: 120px;
        }
    }
</style>
<?php $styles = ob_get_clean(); ?> 