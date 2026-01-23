<?php
// page.php - Router per le pagine interne

// Percorso assoluto per config
$config_file = __DIR__ . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Prova percorso alternativo
    $config_file_alt = dirname(__DIR__) . '/includes/config.php';
    if (file_exists($config_file_alt)) {
        require_once $config_file_alt;
    } else {
        http_response_code(500);
        echo "<h1>Errore di configurazione</h1>";
        echo "<p>Il file di configurazione non è stato trovato. Contatta l'amministratore.</p>";
        exit();
    }
}

// Includi funzioni per le pagine
$page_functions_file = __DIR__ . '/includes/internal_pages_functions.php';
if (file_exists($page_functions_file)) {
    require_once $page_functions_file;
} else {
    // Prova percorso alternativo
    $page_functions_file_alt = dirname(__DIR__) . '/includes/internal_pages_functions.php';
    if (file_exists($page_functions_file_alt)) {
        require_once $page_functions_file_alt;
    } else {
        die("File funzioni pagine non trovato.");
    }
}

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ottieni lo slug dall'URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    // Reindirizza alla home se non viene specificato uno slug
    header("Location: /");
    exit();
}

// Cerca la pagina
$page = getPageBySlug($slug);

if (!$page) {
    // Pagina non trovata
    http_response_code(404);
    $page_title = "Pagina non trovata";
    
    // Includi header
    $header_file = __DIR__ . '/includes/header.php';
    if (file_exists($header_file)) {
        require_once $header_file;
    } else {
        $header_file_alt = dirname(__DIR__) . '/includes/header.php';
        if (file_exists($header_file_alt)) {
            require_once $header_file_alt;
        }
    }
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1 class="display-1 text-muted">404</h1>
                <h2 class="mb-4">Pagina non trovata</h2>
                <p class="lead mb-4">La pagina che stai cercando non esiste o è stata rimossa.</p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Torna alla Home
                </a>
            </div>
        </div>
    </div>
    <?php
    
    // Includi footer
    $footer_file = __DIR__ . '/includes/footer.php';
    if (file_exists($footer_file)) {
        require_once $footer_file;
    } else {
        $footer_file_alt = dirname(__DIR__) . '/includes/footer.php';
        if (file_exists($footer_file_alt)) {
            require_once $footer_file_alt;
        }
    }
    exit();
}

// Imposta le variabili per l'header
$page_title = !empty($page['meta_title']) ? $page['meta_title'] : $page['title'];
$page_description = !empty($page['meta_description']) ? $page['meta_description'] : '';

// Imposta Open Graph tags (URL aggiornato senza /page/)
$og_tags = '
    <meta property="og:title" content="' . htmlspecialchars($page_title) . '">
    <meta property="og:description" content="' . htmlspecialchars($page_description) . '">
    <meta property="og:type" content="website">
    <meta property="og:url" content="' . htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/' . $slug) . '">
';

// Includi header
$header_file = __DIR__ . '/includes/header.php';
if (file_exists($header_file)) {
    require_once $header_file;
} else {
    $header_file_alt = dirname(__DIR__) . '/includes/header.php';
    if (file_exists($header_file_alt)) {
        require_once $header_file_alt;
    }
}
?>

<!-- Contenuto della pagina -->
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/"><i class="fas fa-home"></i> Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page['title']); ?></li>
                </ol>
            </nav>
            
            <!-- Titolo della pagina -->
            <div class="page-header mb-4">
                <h1 class="mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
                
                <!-- Data di ultima modifica -->
                <?php if (!empty($page['updated_at'])): ?>
                    <div class="text-muted mb-3">
                        <small>
                            <i class="fas fa-calendar-alt me-1"></i>
                            Ultimo aggiornamento: <?php echo date('d/m/Y', strtotime($page['updated_at'])); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Contenuto HTML -->
            <div class="page-content">
                <?php echo $page['content']; ?>
            </div>
            
            <!-- Link per tornare alla home -->
            <div class="mt-5 pt-4 border-top text-center">
                <a href="/" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Home
                </a>
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])): ?>
                    <a href="/<?php echo $_SESSION['user_type']; ?>s/dashboard.php" class="btn btn-primary ms-2">
                        <i class="fas fa-tachometer-alt me-2"></i>Vai alla Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Stili per il contenuto della pagina -->
<style>
.page-header {
    padding-bottom: 1rem;
    border-bottom: 1px solid #eaeaea;
}

.page-content {
    line-height: 1.8;
    font-size: 1.1rem;
    color: #333;
}

.page-content h1 {
    font-size: 2.2rem;
    margin-top: 2.5rem;
    margin-bottom: 1.5rem;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 0.5rem;
}

.page-content h2 {
    font-size: 1.8rem;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #34495e;
    padding-bottom: 0.3rem;
}

.page-content h3 {
    font-size: 1.5rem;
    margin-top: 1.8rem;
    margin-bottom: 0.8rem;
    color: #2c3e50;
}

.page-content h4 {
    font-size: 1.3rem;
    margin-top: 1.5rem;
    margin-bottom: 0.7rem;
    color: #34495e;
}

.page-content p {
    margin-bottom: 1.2rem;
    text-align: justify;
}

.page-content ul, .page-content ol {
    margin-bottom: 1.5rem;
    padding-left: 2rem;
}

.page-content ul li, .page-content ol li {
    margin-bottom: 0.5rem;
}

.page-content ul {
    list-style-type: disc;
}

.page-content ol {
    list-style-type: decimal;
}

.page-content blockquote {
    border-left: 4px solid #3498db;
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    background-color: #f8f9fa;
    font-style: italic;
    color: #555;
}

.page-content blockquote p {
    margin-bottom: 0;
}

.page-content table {
    width: 100%;
    margin-bottom: 1.5rem;
    border-collapse: collapse;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.page-content table th,
.page-content table td {
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    text-align: left;
}

.page-content table th {
    background-color: #3498db;
    color: white;
    font-weight: 600;
}

.page-content table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.page-content table tr:hover {
    background-color: #e8f4fc;
}

.page-content img {
    max-width: 100%;
    height: auto;
    margin: 1.5rem 0;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.page-content .img-fluid {
    max-width: 100%;
}

.page-content a {
    color: #3498db;
    text-decoration: none;
    border-bottom: 1px dotted #3498db;
}

.page-content a:hover {
    color: #2980b9;
    border-bottom: 1px solid #2980b9;
}

.page-content pre {
    background-color: #2c3e50;
    color: #ecf0f1;
    padding: 1rem;
    border-radius: 6px;
    overflow-x: auto;
    margin: 1.5rem 0;
}

.page-content code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

.page-content .alert {
    padding: 1rem;
    margin: 1.5rem 0;
    border-radius: 6px;
    border-left: 4px solid;
}

.page-content .alert-info {
    background-color: #d1ecf1;
    border-color: #3498db;
    color: #0c5460;
}

.page-content .alert-warning {
    background-color: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.page-content .alert-success {
    background-color: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.page-content .btn {
    margin: 0.5rem;
    padding: 0.5rem 1.5rem;
}

/* Stili per il contenuto generato da Summernote */
.page-content .note-editable {
    font-family: inherit;
}

.page-content .note-editable p {
    line-height: 1.8;
}
</style>

<?php
// Includi footer
$footer_file = __DIR__ . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    $footer_file_alt = dirname(__DIR__) . '/includes/footer.php';
    if (file_exists($footer_file_alt)) {
        require_once $footer_file_alt;
    }
}