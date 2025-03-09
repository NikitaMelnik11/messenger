<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) . ' - ' : '' ?>Vegan Messenger</title>
    <meta name="description" content="<?= isset($description) ? e($description) : 'Vegan Messenger - Social Network for Vegans' ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="<?= asset('favicon.ico') ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>" type="image/x-icon">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    
    <!-- Custom CSS -->
    <?php if (isset($styles)): ?>
        <?= $styles ?>
    <?php endif; ?>
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">
    <!-- Header -->
    <?php if (!isset($hideHeader) || !$hideHeader): ?>
        <header class="navbar navbar-expand-lg navbar-dark bg-success fixed-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="<?= url() ?>">
                    <img src="<?= asset('img/logo.png') ?>" alt="Vegan Messenger" width="30" height="30" class="me-2">
                    <span>Vegan Messenger</span>
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <!-- Search Form -->
                        <form class="d-flex ms-2 me-auto" action="<?= url('search') ?>" method="get">
                            <div class="input-group">
                                <input class="form-control" type="search" name="q" placeholder="Search" aria-label="Search" value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>">
                                <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
                            </div>
                        </form>
                        
                        <!-- Navigation Links -->
                        <ul class="navbar-nav mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link <?= $request['uri'] === '/feed' ? 'active' : '' ?>" href="<?= url('feed') ?>">
                                    <i class="bi bi-house-door"></i> Home
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= str_starts_with($request['uri'], '/messages') ? 'active' : '' ?>" href="<?= url('messages') ?>">
                                    <i class="bi bi-chat"></i> Messages
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= str_starts_with($request['uri'], '/groups') ? 'active' : '' ?>" href="<?= url('groups') ?>">
                                    <i class="bi bi-people"></i> Groups
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= str_starts_with($request['uri'], '/events') ? 'active' : '' ?>" href="<?= url('events') ?>">
                                    <i class="bi bi-calendar-event"></i> Events
                                </a>
                            </li>
                            
                            <!-- Notifications Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-bell"></i>
                                    <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                                        0
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notifications-list">
                                    <li class="dropdown-item text-center">No new notifications</li>
                                </ul>
                            </li>
                            
                            <!-- User Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="<?= $user['profile_picture'] ? asset($user['profile_picture']) : asset('img/default-avatar.png') ?>" alt="<?= e($user['username']) ?>" class="rounded-circle me-1" width="24" height="24">
                                    <span class="d-none d-lg-inline-block"><?= e($user['username']) ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li>
                                        <a class="dropdown-item" href="<?= url('profile/' . $user['username']) ?>">
                                            <i class="bi bi-person"></i> Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= url('settings') ?>">
                                            <i class="bi bi-gear"></i> Settings
                                        </a>
                                    </li>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('admin') ?>">
                                                <i class="bi bi-shield"></i> Admin Panel
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?= url('logout') ?>">
                                            <i class="bi bi-box-arrow-right"></i> Logout
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="ms-auto">
                        <a href="<?= url('login') ?>" class="btn btn-outline-light me-2">Login</a>
                        <a href="<?= url('register') ?>" class="btn btn-light">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="<?= (!isset($hideHeader) || !$hideHeader) ? 'mt-5 pt-3' : '' ?>">
        <?php if ($flash = flash()): ?>
            <div class="container mt-3">
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <?= $content ?>
    </main>
    
    <!-- Footer -->
    <?php if (!isset($hideFooter) || !$hideFooter): ?>
        <footer class="bg-dark text-white py-4 mt-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <h5>Vegan Messenger</h5>
                        <p class="text-muted">Connect with vegans around the world.</p>
                    </div>
                    <div class="col-md-2 mb-3">
                        <h6>Links</h6>
                        <ul class="list-unstyled">
                            <li><a href="<?= url('about') ?>" class="text-decoration-none text-white">About</a></li>
                            <li><a href="<?= url('terms') ?>" class="text-decoration-none text-white">Terms</a></li>
                            <li><a href="<?= url('privacy') ?>" class="text-decoration-none text-white">Privacy</a></li>
                            <li><a href="<?= url('contact') ?>" class="text-decoration-none text-white">Contact</a></li>
                        </ul>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h6>Community</h6>
                        <ul class="list-unstyled">
                            <li><a href="<?= url('groups') ?>" class="text-decoration-none text-white">Groups</a></li>
                            <li><a href="<?= url('events') ?>" class="text-decoration-none text-white">Events</a></li>
                            <li><a href="<?= url('blog') ?>" class="text-decoration-none text-white">Blog</a></li>
                            <li><a href="<?= url('help') ?>" class="text-decoration-none text-white">Help Center</a></li>
                        </ul>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h6>Follow Us</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="text-white fs-5"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="text-white fs-5"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-white fs-5"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="text-white fs-5"><i class="bi bi-youtube"></i></a>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="text-center text-muted">
                    <small>&copy; <?= date('Y') ?> Vegan Messenger. All rights reserved.</small>
                </div>
            </div>
        </footer>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    
    <?php if ($isLoggedIn): ?>
        <script src="<?= asset('js/notifications.js') ?>"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize notifications
                initNotifications(<?= $user['user_id'] ?>);
            });
        </script>
    <?php endif; ?>
    
    <!-- Custom JavaScript -->
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html> 