<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_functions.php';

// === CONTROLLO MANUTENZIONE - AGGIUNTA IMPORTANTE ===
require_once __DIR__ . '/includes/maintenance.php';
check_maintenance_mode($pdo);

$is_logged_in = is_logged_in();
$user_name = $is_logged_in ? ($_SESSION['user_name'] ?? 'Utente') : '';

// Determina il percorso della dashboard in base al tipo di utente
$dashboard_url = "/influencers/dashboard.php"; // default
$create_profile_url = "/influencers/create-profile.php"; // default

if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'brand') {
        $dashboard_url = "/brands/dashboard.php";
        $create_profile_url = "#"; // I brand potrebbero non avere un create-profile
    } elseif ($_SESSION['user_type'] === 'influencer') {
        $dashboard_url = "/influencers/dashboard.php";
        $create_profile_url = "/influencers/create-profile.php";
    } elseif ($_SESSION['user_type'] === 'admin') {
        $dashboard_url = "/admin/dashboard.php";
        $create_profile_url = "#";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kibbiz - Connetti Brand e Influencer</title>
    <!-- Aggiungi Font Awesome per le icone social -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Header & Navigation */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline {
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            border: 2px solid #667eea;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            border-color: #5a6fd8;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        .btn-white {
            background: white;
            color: #667eea;
            border: 2px solid white;
        }

        .btn-white:hover {
            background: transparent;
            color: white;
        }

        /* Features Section */
        .features {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* How It Works */
        .how-it-works {
            padding: 80px 0;
            background: white;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .step {
            text-align: center;
            padding: 2rem;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        .step h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            padding: 40px 0 20px;
        }

        .footer-content {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
         gap: 2rem;
         margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: #667eea;
        }

        .footer-section a {
            color: #ccc;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: #667eea;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #444;
            color: #ccc;
        }

        /* Footer - Stili per le icone social */
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .social-link {
            color: #ccc;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .social-link:hover {
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-direction: column;
                gap: 1rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .social-icons {
                justify-content: center;
            }
        }

        /* Banner Manutenzione (solo per admin) */
        .maintenance-banner {
            background: #ffc107;
            color: #856404;
            padding: 10px 0;
            text-align: center;
            font-weight: bold;
            border-bottom: 2px solid #ffab00;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1001;
        }
    </style>
</head>
<body>
    <?php
    // Mostra banner manutenzione solo per gli admin
    if (is_user_admin() && is_maintenance_mode($pdo)) {
        echo '<div class="maintenance-banner">⚠️ MODALITÀ MANUTENZIONE ATTIVA - Gli utenti normali vedranno la pagina di manutenzione</div>';
    }
    ?>

    <!-- Header Dinamico -->
<?php
// Includi le funzioni per le pagine - controlla se il file esiste
$page_functions_file = __DIR__ . '/includes/page_functions.php';
if (file_exists($page_functions_file)) {
    require_once $page_functions_file;
    
    // Verifica che la funzione esista prima di chiamarla
    if (function_exists('render_dynamic_header')) {
        echo render_dynamic_header();
    } else {
        // Fallback all'header statico se la funzione non esiste
        render_static_header_fallback();
    }
} else {
    // Fallback all'header statico se il file non esiste
    render_static_header_fallback();
}

// Funzione di fallback per l'header statico
function render_static_header_fallback() {
    $is_logged_in = is_logged_in();
    $user_name = $is_logged_in ? ($_SESSION['user_name'] ?? 'Utente') : '';
    
    // Determina il percorso della dashboard in base al tipo di utente
    $dashboard_url = "/influencers/dashboard.php"; // default
    if (isset($_SESSION['user_type'])) {
        if ($_SESSION['user_type'] === 'brand') {
            $dashboard_url = "/brands/dashboard.php";
        } elseif ($_SESSION['user_type'] === 'admin') {
            $dashboard_url = "/admin/dashboard.php";
        }
    }
    ?>
    <!-- Navigation -->
    <nav class="navbar" style="<?php echo (is_user_admin() && is_maintenance_mode($GLOBALS['pdo'])) ? 'margin-top: 40px;' : ''; ?>">
        <div class="nav-container">
            <a href="/" class="logo">Kibbiz</a>
            <div class="nav-links">
                <a href="#features">Funzionalità</a>
                <a href="#how-it-works">Come Funziona</a>
                <a href="#about">Chi Siamo</a>
                
                <?php if ($is_logged_in): ?>
                    <div class="auth-buttons">
                        <span>Ciao, <?php echo htmlspecialchars($user_name); ?>!</span>
                        <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">Dashboard</a>
                        <a href="/auth/logout.php" class="btn btn-outline">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="/auth/login.php" class="btn btn-outline">Login</a>
                        <a href="/auth/register.php" class="btn btn-primary">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php
}
?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Connettiamo Brand e Influencer</h1>
            <p>La piattaforma perfetta per trovare collaborazioni autentiche e crescere insieme</p>
            <div class="hero-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary btn-large">Vai alla Dashboard</a>
                    <?php if ($_SESSION['user_type'] === 'influencer'): ?>
                        <a href="<?php echo $create_profile_url; ?>" class="btn btn-white btn-large">Crea Profilo</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/auth/register.php" class="btn btn-primary btn-large">Inizia Ora</a>
                    <a href="/auth/login.php" class="btn btn-white btn-large">Accedi</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title">
                <h2>Funzionalità Principali</h2>
                <p>Tutto ciò di cui hai bisogno per far crescere la tua presenza online</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🤝</div>
                    <h3>Collaborazioni</h3>
                    <p>Connettiti con brand affini al tuo contenuto e trova partnership autentiche</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Analisi Dettagliate</h3>
                    <p>Monitora le performance delle tue campagne e ottimizza le strategie</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💼</div>
                    <h3>Gestione Contratti</h3>
                    <p>Gestisci facilmente accordi, pagamenti e comunicazioni in un unico posto</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>Come Funziona</h2>
                <p>Inizia in pochi semplici passaggi</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Crea il Profilo</h3>
                    <p>Registrati e compila il tuo profilo influencer con tutte le informazioni</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Connetti con i Brand</h3>
                    <p>I brand ti troveranno in base alla tua niche e follower</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Collabora e Guadagna</h3>
                    <p>Accetta collaborazioni e inizia a monetizzare la tua influenza</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Pronto a Iniziare?</h2>
            <p>Unisciti a migliaia di influencer e brand che già usano la nostra piattaforma</p>
            <?php if ($is_logged_in): ?>
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-white btn-large">Vai alla Dashboard</a>
            <?php else: ?>
                <a href="/auth/register.php" class="btn btn-white btn-large">Registrati Gratis</a>
            <?php endif; ?>
        </div>
    </section>

     <!-- Footer Dinamico -->
    <?php
    // Includi le funzioni per le pagine - controlla se il file esiste
    $page_functions_file = __DIR__ . '/includes/page_functions.php';
    if (file_exists($page_functions_file)) {
        require_once $page_functions_file;
        
        // Verifica che la funzione esista prima di chiamarla
        if (function_exists('render_dynamic_footer')) {
            echo render_dynamic_footer();
        } else {
            // Fallback al footer statico se la funzione non esiste
            render_static_footer_fallback();
        }
    } else {
        // Fallback al footer statico se il file non esiste
        render_static_footer_fallback();
    }
    
    // Funzione di fallback per il footer statico
    function render_static_footer_fallback() {
        ?>
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-section">
                        <h3>Kibbiz</h3>
                        <p>Uniamo Brand e Influencer per crescere insieme.</p>
                    </div>
                    <div class="footer-section">
                        <h3>Link Veloci</h3>
                        <a href="/">Home</a>
                        <a href="#features">Funzionalità</a>
                        <a href="#how-it-works">Come Funziona</a>
                        <a href="/auth/login.php">Login</a>
                        <a href="/auth/register.php">Registrati</a>
                    </div>
                    <div class="footer-section">
                        <h3>Supporto</h3>
                        <a href="#">Contattaci</a>
                        <a href="#">FAQ</a>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Termini di Servizio</a>
                    </div>
                    <div class="footer-section">
                        <h3>Seguici su</h3>
                        <div class="social-icons">
                            <a href="#" class="social-link" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="TikTok">
                                <i class="fab fa-tiktok"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                <p>&copy; Kibbiz <?php echo date('Y'); ?>. Tutti i diritti riservati.</p>
            </div>
            </div>
        </footer>
        <?php
    }
    ?>

    <script>
        // Smooth scroll per i link interni
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Aggiungi classe active alla navigazione durante lo scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });

        // Debug info per admin (solo in sviluppo)
        <?php if (is_user_admin()): ?>
        console.log('🔧 Info Admin:');
        console.log('- Manutenzione attiva:', <?php echo is_maintenance_mode($pdo) ? 'true' : 'false'; ?>);
        console.log('- Tipo utente:', '<?php echo $_SESSION['user_type'] ?? 'guest'; ?>');
        <?php endif; ?>
    </script>
</body>
</html>