<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA PARAMETRO ID
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: /brands/search-influencers.php");
    exit();
}

$influencer_id = intval($_GET['id']);

// =============================================
// MAPPA CATEGORIE PER VISUALIZZAZIONE
// =============================================
$category_mapping = [
    'lifestyle' => 'Lifestyle',
    'fashion' => 'Fashion',
    'beauty' => 'Beauty & Makeup',
    'fitness' => 'Fitness & Wellness',
    'travel' => 'Travel',
    'food' => 'Food',
    'tech' => 'Tech',
    'gaming' => 'Gaming'
];

// =============================================
// RECUPERO DATI INFLUENCER E INCREMENTO VISUALIZZAZIONI
// =============================================
$influencer = null;
$error = '';

try {
    // Prima incrementa le visualizzazioni
    $update_views_stmt = $pdo->prepare("
        UPDATE influencers 
        SET profile_views = COALESCE(profile_views, 0) + 1 
        WHERE id = ?
    ");
    $update_views_stmt->execute([$influencer_id]);
    
    // Poi recupera i dati dell'influencer
    $stmt = $pdo->prepare("
        SELECT id, user_id, full_name, bio, niche, 
               instagram_handle, tiktok_handle, youtube_handle, 
               website, rate, profile_image, profile_views, rating,
               created_at, updated_at, nationality 
        FROM influencers 
        WHERE id = ?
    ");
    $stmt->execute([$influencer_id]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        $error = "Influencer non trovato!";
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <!-- Pulsante Torna alla Ricerca -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Profilo Influencer</h2>
            <a href="/brands/search-influencers.php" class="btn btn-outline-primary">
                ← Torna alla Ricerca
            </a>
        </div>

        <!-- Messaggi di errore -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$influencer): ?>
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h4 class="card-title text-danger">Profilo Non Trovato</h4>
                    <p class="card-text">
                        L'influencer che stai cercando non esiste o è stato rimosso.
                    </p>
                    <a href="/brands/search-influencers.php" class="btn btn-danger">
                        Torna alla Ricerca
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- SEZIONE PRINCIPALE PROFILO -->
            <div class="row">
                <!-- Colonna Sinistra: Immagine e Info Base -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            // Determina quale immagine mostrare
                            $profile_image_src = '';
                            $profile_image_alt = htmlspecialchars($influencer['full_name']);
                            
                            if (!empty($influencer['profile_image'])) {
                                // Se l'influencer ha caricato un'immagine personalizzata
                                $profile_image_src = "/uploads/" . htmlspecialchars($influencer['profile_image']);
                            } else {
                                // Se NON ha un'immagine personalizzata, mostra il placeholder
                                $profile_image_src = "/uploads/placeholder/influencer_admin_edit.png";
                                $profile_image_alt = "Placeholder - " . $profile_image_alt;
                            }
                            ?>
                            
                            <img src="<?php echo $profile_image_src; ?>" 
                                 class="rounded-circle mb-3" 
                                 alt="<?php echo $profile_image_alt; ?>" 
                                 style="width: 200px; height: 200px; object-fit: cover;">
                            
                            <h4><?php echo htmlspecialchars($influencer['full_name']); ?></h4>
                            
                            <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand'): ?>
        <?php
        // Verifica se l'influencer è nei preferiti del brand
        $is_favorite = false;
        try {
            $stmt_brand = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
            $stmt_brand->execute([$_SESSION['user_id']]);
            $brand_data = $stmt_brand->fetch(PDO::FETCH_ASSOC);
            
            if ($brand_data) {
                $brand_id = $brand_data['id'];
                $stmt_fav = $pdo->prepare("SELECT id FROM favorite_influencers WHERE brand_id = ? AND influencer_id = ?");
                $stmt_fav->execute([$brand_id, $influencer_id]);
                $is_favorite = $stmt_fav->fetch() !== false;
            }
        } catch (PDOException $e) {
            // Silenzioso in caso di errore
        }
        ?>
        <!-- Pulsante Preferiti -->
        <button type="button" 
                class="btn btn-sm favorite-btn-profile"
                data-influencer-id="<?php echo $influencer_id; ?>"
                data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>"
                style="padding: 0.25rem 0.5rem; border-radius: 50%;"
                title="<?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
            <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-muted'; ?>" 
               style="font-size: 1.2rem;"></i>
        </button>
        
        <!-- Pulsante Segnala Utente -->
        <button type="button" 
        class="btn btn-sm report-btn-profile ms-2"
        data-bs-toggle="modal"
        data-bs-target="#reportModal"
        style="padding: 0.25rem 0.5rem; border-radius: 50%;"
        title="Segnala utente">
    <i class="fas fa-flag text-warning" style="font-size: 1.2rem;"></i>
</button>
    <?php endif; ?>
</div>
                        </div>
                    </div>

                    <!-- Informazioni -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Informazioni</h5>
                        </div>
                        <div class="card-body">
                            <!-- Aggiunto: Categoria sopra Visualizzazioni Profilo -->
                            <div class="mb-3">
                                <strong>Categoria:</strong>
                                <span class="float-end">
                                    <?php 
                                    if (!empty($influencer['niche'])) {
                                        $display_niche = $influencer['niche'];
                                        if (isset($category_mapping[$influencer['niche']])) {
                                            $display_niche = $category_mapping[$influencer['niche']];
                                        }
                                        // FIX: Decodifica le entità HTML per visualizzare correttamente &
                                        echo htmlspecialchars_decode(htmlspecialchars($display_niche));
                                    } else {
                                        echo '<span class="text-muted">Non specificata</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <!-- Aggiunto: Nazionalità -->
                            <div class="mb-3">
                                <strong>Nazionalità:</strong>
                                <span class="float-end">
                                    <?php 
                                    if (!empty($influencer['nationality'])) {
                                        echo htmlspecialchars($influencer['nationality']);
                                    } else {
                                        echo '<span class="text-muted">N/D</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Visualizzazioni Profilo:</strong>
                                <span class="float-end"><?php echo number_format($influencer['profile_views'] ?? 0); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Rating:</strong>
                                <span class="float-end">
                                    <?php if (!empty($influencer['rating']) && $influencer['rating'] > 0): ?>
                                        <span class="text-warning">
                                            ★ <?php echo number_format($influencer['rating'], 1); ?>/5
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Nessun rating</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Membro dal:</strong>
                                <span class="float-end">
                                    <?php echo !empty($influencer['created_at']) ? date('d/m/Y', strtotime($influencer['created_at'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Contatta Influencer -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">Contatta</h5>
                        </div>
                        <div class="card-body text-center">
                            <p class="card-text">Interessato a collaborare?</p>
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand'): ?>
                                <button class="btn btn-warning btn-lg w-100" data-bs-toggle="modal" data-bs-target="#contactModal">
                                    📧 Contatta Influencer
                                </button>
                            <?php else: ?>
                                <a href="/auth/login.php" class="btn btn-outline-warning w-100">
                                    🔐 Accedi come Brand per Contattare
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonna Destra: Dettagli Completi -->
                <div class="col-md-8">
                     <!-- Tariffa e Info Principali -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">Informazioni Collaborazione</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Tariffa per Collaborazione:</strong>
                                        <span class="float-end fs-5 text-success">
                                            €<?php echo !empty($influencer['rate']) ? number_format($influencer['rate'], 2) : '0.00'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Disponibilità:</strong>
                                        <span class="float-end text-success">
                                            ✅ Disponibile per collaborazioni
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sponsor Recenti -->
<?php
// Recupera gli sponsor recenti dell'influencer
$recent_sponsors = [];
if ($influencer) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, image_url, budget, currency, created_at
            FROM sponsors 
            WHERE influencer_id = ? 
            AND status = 'active'
            AND deleted_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$influencer['id']]);
        $recent_sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silenzioso in caso di errore
    }
}
?>

<?php if (!empty($recent_sponsors)): ?>
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="card-title mb-0">Sponsor recenti</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($recent_sponsors as $sponsor): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <?php if (!empty($sponsor['image_url'])): ?>
    <img src="/uploads/sponsor/<?php echo htmlspecialchars($sponsor['image_url']); ?>" 
         class="rounded mb-3" 
         alt="<?php echo htmlspecialchars($sponsor['title']); ?>"
         style="width: 100%; height: 120px; object-fit: cover;">
<?php else: ?>
    <img src="/uploads/placeholder/sponsor_influencer_profile.png" 
         class="rounded mb-3" 
         alt="Placeholder sponsor"
         style="width: 100%; height: 120px; object-fit: cover;">
<?php endif; ?>
                                
                                <h6 class="card-title"><?php echo htmlspecialchars($sponsor['title']); ?></h6>
                                <p class="card-text text-success fw-bold">
                                    €<?php echo number_format($sponsor['budget'], 2); ?>
                                </p>
                                <a href="/influencers/sponsors/view.php?id=<?php echo $sponsor['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    Visualizza dettagli
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
                        </div>
                    </div>
<?php endif; ?>

                    <!-- Biografia -->
                    <?php if (!empty($influencer['bio'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Biografia</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text fs-6"><?php echo nl2br(htmlspecialchars($influencer['bio'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Social Media -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Social Media</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($influencer['instagram_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📷</span>
                                            <div>
                                                <strong>Instagram</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['instagram_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['tiktok_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">🎵</span>
                                            <div>
                                                <strong>TikTok</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['tiktok_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['youtube_handle'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📺</span>
                                            <div>
                                                <strong>YouTube</strong>
                                                <div class="text-muted">@<?php echo htmlspecialchars($influencer['youtube_handle']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($influencer['website'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">🌐</span>
                                            <div>
                                                <strong>Sito Web</strong>
                                                <div class="text-muted">
                                                    <a href="<?php echo htmlspecialchars($influencer['website']); ?>" target="_blank">
                                                        Visita Sito
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($influencer['instagram_handle']) && empty($influencer['tiktok_handle']) && empty($influencer['youtube_handle'])): ?>
                                <p class="text-muted text-center">Nessun social media specificato</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Metri di Performance (Placeholder per future implementazioni) -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Metriche di Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-primary">📊</div>
                                        <div class="fw-bold">Engagement Rate</div>
                                        <div class="text-muted">Disponibile su richiesta</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-success">👥</div>
                                        <div class="fw-bold">Follower</div>
                                        <div class="text-muted">Dettagli su contatto</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-warning">🎯</div>
                                        <div class="fw-bold">Audience</div>
                                        <div class="text-muted">Analisi disponibile</div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Contatta l'influencer per ottenere metriche dettagliate e report completi
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Contatto (Placeholder per future implementazioni) -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Contatta <?php echo htmlspecialchars($influencer['full_name'] ?? ''); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Funzionalità di contatto in fase di sviluppo.</p>
                <p>Per ora, puoi contattare l'influencer tramite i suoi social media elencati sopra.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Segnala Utente -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reportForm" method="POST" action="/includes/report_user.php">
                <input type="hidden" name="reported_user_id" value="<?php echo $influencer['user_id']; ?>">
                <?php if (isset($_SESSION['csrf_token'])): ?>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <?php endif; ?>
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="reportModalLabel">
                        Segnala utente
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Stai per segnalare <strong><?php echo htmlspecialchars($influencer['full_name']); ?></strong>.
                        La tua segnalazione verrà esaminata dal supporto Kibbiz.
                    </p>
                    
                    <div class="mb-3">
                        <label for="reportReason" class="form-label">
                            <strong>Motivo della segnalazione:</strong>
                        </label>
                        <textarea class="form-control" 
                                id="reportReason" 
                                name="reason" 
                                rows="5" 
                                placeholder="Descrivi il motivo della segnalazione..." 
                                required
                                maxlength="1000"></textarea>
                        
                        <!-- Prima riga: Contatore caratteri allineato a destra -->
                        <div class="text-end mt-1">
                            <div class="text-muted small">
                                <span id="charCount">1000</span> caratteri rimanenti
                            </div>
                        </div>
                        
                        <!-- Seconda riga: Testo informativo -->
                        <div class="form-text mt-1">
                            Fornisci più dettagli possibili per aiutare il nostro supporto a valutare la segnalazione.
                        </div>
                    </div>
                    
                    <div id="reportMessage" class="alert" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-warning">
                        Invia segnalazione
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestione Preferiti - Versione minimale senza tooltip Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    // Trova tutti i pulsanti preferiti
    const favoriteButtons = document.querySelectorAll('.favorite-btn-profile');
    
    favoriteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const influencerId = this.getAttribute('data-influencer-id');
            const isFavorite = this.getAttribute('data-is-favorite') === '1';
            const icon = this.querySelector('i');
            
            // Stato nuovo
            const newIsFavorite = !isFavorite;
            
            // Cambia immediatamente l'icona per feedback visivo
            if (newIsFavorite) {
                icon.className = 'fas fa-heart text-danger';
            } else {
                icon.className = 'far fa-heart text-muted';
            }
            this.setAttribute('data-is-favorite', newIsFavorite ? '1' : '0');
            this.title = newIsFavorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
            
            // Invia richiesta AJAX con XMLHttpRequest (più compatibile)
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/brands/toggle-favorite.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        
                        if (data.success) {
                            // Mostra messaggio di successo semplice
                            showMessage(data.message || 'Operazione completata!', 'success');
                        } else {
                            // Ripristina stato originale in caso di errore
                            if (isFavorite) {
                                icon.className = 'fas fa-heart text-danger';
                            } else {
                                icon.className = 'far fa-heart text-muted';
                            }
                            button.setAttribute('data-is-favorite', isFavorite ? '1' : '0');
                            button.title = isFavorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
                            
                            showMessage('Errore: ' + (data.message || 'Operazione fallita'), 'danger');
                        }
                    } catch (e) {
                        // Errore nel parsing JSON
                        if (isFavorite) {
                            icon.className = 'fas fa-heart text-danger';
                        } else {
                            icon.className = 'far fa-heart text-muted';
                        }
                        button.setAttribute('data-is-favorite', isFavorite ? '1' : '0');
                        button.title = isFavorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
                        
                        showMessage('Errore nella risposta del server', 'danger');
                    }
                } else {
                    // Errore HTTP
                    if (isFavorite) {
                        icon.className = 'fas fa-heart text-danger';
                    } else {
                        icon.className = 'far fa-heart text-muted';
                    }
                    button.setAttribute('data-is-favorite', isFavorite ? '1' : '0');
                    button.title = isFavorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
                    
                    showMessage('Errore di connessione: ' + xhr.status, 'danger');
                }
            };
            
            xhr.onerror = function() {
                // Errore di rete
                if (isFavorite) {
                    icon.className = 'fas fa-heart text-danger';
                } else {
                    icon.className = 'far fa-heart text-muted';
                }
                button.setAttribute('data-is-favorite', isFavorite ? '1' : '0');
                button.title = isFavorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
                
                showMessage('Errore di rete', 'danger');
            };
            
            xhr.send('influencer_id=' + encodeURIComponent(influencerId) + 
                     '&action=' + encodeURIComponent(newIsFavorite ? 'add' : 'remove'));
        });
    });
    
    // Funzione per mostrare messaggi semplici (senza Bootstrap)
    function showMessage(message, type) {
        // Rimuovi messaggi precedenti
        const oldMessages = document.querySelectorAll('.favorite-message');
        oldMessages.forEach(msg => msg.remove());
        
        // Crea nuovo messaggio
        const messageDiv = document.createElement('div');
        messageDiv.className = 'favorite-message alert alert-' + type;
        messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 10px 15px; border-radius: 4px;';
        
        if (type === 'success') {
            messageDiv.style.backgroundColor = '#d4edda';
            messageDiv.style.color = '#155724';
            messageDiv.style.border = '1px solid #c3e6cb';
        } else {
            messageDiv.style.backgroundColor = '#f8d7da';
            messageDiv.style.color = '#721c24';
            messageDiv.style.border = '1px solid #f5c6cb';
        }
        
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);
        
        // Rimuovi dopo 3 secondi
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 500);
            }
        }, 3000);
    }
    
    // Aggiungi stili per i messaggi
    const style = document.createElement('style');
    style.textContent = `
        .favorite-message {
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    // Gestione Segnalazione Utente
    const reportForm = document.getElementById('reportForm');
    const reportMessage = document.getElementById('reportMessage');
    
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = reportForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disabilita pulsante durante l'invio
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Invio in corso...';
            
            // Prepara i dati del form
            const formData = new FormData(reportForm);
            
            // Invia richiesta AJAX
            fetch('/includes/report_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Successo
                    reportMessage.className = 'alert alert-success';
                    reportMessage.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message || 'Segnalazione inviata con successo!'}
                    `;
                    reportMessage.style.display = 'block';
                    
                    // Resetta form
                    reportForm.reset();
                    
                    // Chiudi modale dopo 5 secondi
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
                        if (modal) modal.hide();
                        
                        // NOTA: Rimossa la chiamata a showMessage() che mostrava l'alert globale
                        // showMessage('Segnalazione inviata con successo! Gli amministratori la esamineranno.', 'success');
                    }, 5000);
                    
                } else {
                    // Errore
                    reportMessage.className = 'alert alert-danger';
                    reportMessage.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Si è verificato un errore durante l\'invio della segnalazione.'}
                    `;
                    reportMessage.style.display = 'block';
                }
            })
            .catch(error => {
                reportMessage.className = 'alert alert-danger';
                reportMessage.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Errore di connessione. Riprova più tardi.
                `;
                reportMessage.style.display = 'block';
            })
            .finally(() => {
                // Ripristina pulsante
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
    
    // =============================================
    // CODICE CONTATTERE CARATTERI
    // =============================================
    
    // Gestione contatore caratteri per la segnalazione
    const reportReasonTextarea = document.getElementById('reportReason');
    const charCountElement = document.getElementById('charCount');
    
    if (reportReasonTextarea && charCountElement) {
        // Funzione per aggiornare il contatore
        const updateCharCount = () => {
            const currentLength = reportReasonTextarea.value.length;
            const remaining = 1000 - currentLength;
            charCountElement.textContent = remaining;
            
            // Cambia colore quando rimangono pochi caratteri
            if (remaining <= 100) {
                charCountElement.style.color = remaining <= 20 ? '#dc3545' : '#ffc107';
            } else {
                charCountElement.style.color = '';
            }
        };
        
        // Aggiorna al caricamento iniziale
        updateCharCount();
        
        // Aggiorna ad ogni input
        reportReasonTextarea.addEventListener('input', updateCharCount);
        
        // Aggiorna anche quando il modal viene aperto (per sicurezza)
        const reportModal = document.getElementById('reportModal');
        if (reportModal) {
            reportModal.addEventListener('shown.bs.modal', updateCharCount);
        }
    }
});
</script>

<?php
// =============================================
// INCLUSIONE FOOTER CON PERCORSO ASSOLUTO
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>