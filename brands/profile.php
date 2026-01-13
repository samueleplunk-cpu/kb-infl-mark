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
    header("Location: /influencers/search-brands.php");
    exit();
}

$brand_id = intval($_GET['id']);

// =============================================
// RECUPERO DATI BRAND E INCREMENTO VISUALIZZAZIONI
// =============================================
$brand = null;
$error = '';

try {
    // Prima incrementa le visualizzazioni (se esiste il campo)
    try {
        $update_views_stmt = $pdo->prepare("
            UPDATE brands 
            SET profile_views = COALESCE(profile_views, 0) + 1 
            WHERE id = ?
        ");
        $update_views_stmt->execute([$brand_id]);
    } catch (PDOException $e) {
        // Il campo potrebbe non esistere, ignoriamo l'errore
        error_log("Nota: campo profile_views non trovato: " . $e->getMessage());
    }
    
    // Recupera i dati del brand
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            u.email,
            u.created_at as user_created_at
        FROM brands b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$brand_id]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        $error = "Brand non trovato!";
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// RECUPERO CATEGORIE PER MAPPA
// =============================================
require_once dirname(__DIR__) . '/includes/category_functions.php';
$active_categories = get_active_categories($pdo);

// Crea mappatura slug -> nome per visualizzazione
$category_mapping = [];
foreach ($active_categories as $category) {
    $category_mapping[$category['slug']] = $category['name'];
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
            <h2>Profilo Brand</h2>
            <a href="/influencers/search-brands.php" class="btn btn-outline-primary">
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

        <?php if (!$brand): ?>
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h4 class="card-title text-danger">Profilo Non Trovato</h4>
                    <p class="card-text">
                        Il brand che stai cercando non esiste o è stato rimosso.
                    </p>
                    <a href="/influencers/search-brands.php" class="btn btn-danger">
                        Torna alla Ricerca
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- SEZIONE PRINCIPALE PROFILO -->
            <div class="row">
                <!-- Colonna Sinistra: Logo e Info Base -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo Azienda</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            // Determina quale immagine mostrare
                            $logo_src = '';
                            $logo_alt = htmlspecialchars($brand['company_name']);
                            
                            if (!empty($brand['logo'])) {
                                // Se il brand ha caricato un logo personalizzato
                                $logo_src = "/uploads/brands/" . htmlspecialchars($brand['logo']);
                            } else {
                                // Se NON ha un logo personalizzato, mostra il placeholder
                                $logo_src = "/uploads/placeholder/brand-placeholder.png";
                                $logo_alt = "Placeholder - " . $logo_alt;
                            }
                            ?>
                            
                            <img src="<?php echo $logo_src; ?>" 
                                 class="rounded mb-3" 
                                 alt="<?php echo $logo_alt; ?>" 
                                 style="width: 200px; height: 200px; object-fit: contain; background-color: #f8f9fa;">
                            
                            <h4><?php echo htmlspecialchars($brand['company_name']); ?></h4>
                            
                            <!-- Categoria/Settore -->
                            <?php if (!empty($brand['industry'])): ?>
                                <?php
                                $display_industry = $category_mapping[$brand['industry']] ?? $brand['industry'];
                                ?>
                                <div class="mt-2 mb-3">
                                    <span class="badge bg-info fs-6">
                                        <?php echo htmlspecialchars($display_industry); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
    <!-- Pulsante Preferiti (solo per influencer) -->
    <?php 
    // Recupera l'ID dell'influencer se l'utente è loggato come influencer
    $influencer_id = null;
    $is_favorite = false;
    
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer' && isset($_SESSION['user_id'])) {
        try {
            // Recupera l'ID dell'influencer dalla tabella influencers
            $stmt_inf = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
            $stmt_inf->execute([$_SESSION['user_id']]);
            $influencer_data = $stmt_inf->fetch(PDO::FETCH_ASSOC);
            
            if ($influencer_data) {
                $influencer_id = $influencer_data['id'];
                
                // Verifica se il brand è nei preferiti dell'influencer
                $stmt_fav = $pdo->prepare("SELECT id FROM favorite_brands WHERE influencer_id = ? AND brand_id = ?");
                $stmt_fav->execute([$influencer_id, $brand_id]);
                $is_favorite = $stmt_fav->fetch() !== false;
            }
        } catch (PDOException $e) {
            // Silenzioso in caso di errore
            error_log("Errore recupero influencer/preferiti: " . $e->getMessage());
        }
    }
    ?>
    
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer' && $influencer_id): ?>
        <button type="button" 
                class="btn btn-sm favorite-btn-brand"
                data-brand-id="<?php echo $brand_id; ?>"
                data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>"
                style="padding: 0.25rem 0.5rem; border-radius: 50%; cursor: pointer;"
                title="<?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
            <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-muted'; ?>" 
               style="font-size: 1.2rem;"></i>
        </button>
    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand'): ?>
        <!-- Pulsante Preferiti per brand (disabilitato) -->
        <button type="button" 
                class="btn btn-sm"
                disabled
                style="padding: 0.25rem 0.5rem; border-radius: 50%; cursor: not-allowed;"
                title="Disponibile solo per influencer">
            <i class="far fa-heart text-muted" style="font-size: 1.2rem;"></i>
        </button>
    <?php else: ?>
        <!-- Pulsante Preferiti per utenti non autenticati o altri tipi -->
        <button type="button" 
                class="btn btn-sm"
                disabled
                style="padding: 0.25rem 0.5rem; border-radius: 50%; cursor: not-allowed;"
                title="Accedi come influencer per aggiungere ai preferiti">
            <i class="far fa-heart text-muted" style="font-size: 1.2rem;"></i>
        </button>
    <?php endif; ?>
    
    <!-- Pulsante Segnala Utente -->
    <button type="button" 
            class="btn btn-sm report-btn-brand ms-2"
            data-bs-toggle="modal"
            data-bs-target="#reportModal"
            style="padding: 0.25rem 0.5rem; border-radius: 50%; cursor: pointer;"
            title="Segnala utente">
        <i class="fas fa-flag text-warning" style="font-size: 1.2rem;"></i>
    </button>
</div>
                        </div>
                    </div>

                    <!-- Informazioni -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Informazioni</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($brand['contact_person'])): ?>
                                <div class="mb-3">
                                    <strong>Persona di Contatto:</strong>
                                    <span class="float-end"><?php echo htmlspecialchars($brand['contact_person']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($brand['phone'])): ?>
                                <div class="mb-3">
                                    <strong>Telefono:</strong>
                                    <span class="float-end"><?php echo htmlspecialchars($brand['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <strong>Membro dal:</strong>
                                <span class="float-end">
                                    <?php echo !empty($brand['user_created_at']) ? date('d/m/Y', strtotime($brand['user_created_at'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Contatta Brand -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">Contatta</h5>
                        </div>
                        <div class="card-body text-center">
                            <p class="card-text">Interessato a collaborare?</p>
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer' && isset($influencer_id)): ?>
                                <button class="btn btn-warning btn-lg w-100" data-bs-toggle="modal" data-bs-target="#contactModal">
                                    📧 Contatta Brand
                                </button>
                            <?php else: ?>
                                <a href="/auth/login.php" class="btn btn-outline-warning w-100">
                                    🔐 Accedi come Influencer per Contattare
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonna Destra: Dettagli Completi -->
                <div class="col-md-8">
                    <!-- Descrizione -->
                    <?php if (!empty($brand['description'])): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">Descrizione Azienda</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text fs-6"><?php echo nl2br(htmlspecialchars($brand['description'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Contatti -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Contatti</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($brand['email'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📧</span>
                                            <div>
                                                <strong>Email</strong>
                                                <div class="text-muted"><?php echo htmlspecialchars($brand['email']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($brand['website'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">🌐</span>
                                            <div>
                                                <strong>Sito Web</strong>
                                                <div class="text-muted">
                                                    <a href="<?php echo htmlspecialchars($brand['website']); ?>" target="_blank" class="text-decoration-none">
                                                        Visita Sito
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($brand['phone'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">📞</span>
                                            <div>
                                                <strong>Telefono</strong>
                                                <div class="text-muted"><?php echo htmlspecialchars($brand['phone']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($brand['contact_person'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-4 me-3">👤</span>
                                            <div>
                                                <strong>Referente</strong>
                                                <div class="text-muted"><?php echo htmlspecialchars($brand['contact_person']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Informazioni Aggiuntive (Placeholder per future implementazioni) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informazioni Aggiuntive</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-primary">🏢</div>
                                        <div class="fw-bold">Tipo Azienda</div>
                                        <div class="text-muted">
                                            <?php echo !empty($brand['company_type']) ? htmlspecialchars($brand['company_type']) : 'Non specificato'; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-success">📍</div>
                                        <div class="fw-bold">Sede</div>
                                        <div class="text-muted">
                                            <?php echo !empty($brand['location']) ? htmlspecialchars($brand['location']) : 'Non specificata'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collaborazioni (Placeholder per future implementazioni) -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Collaborazioni</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-primary">🤝</div>
                                        <div class="fw-bold">Collaborazioni Attive</div>
                                        <div class="text-muted">Disponibile su richiesta</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-success">⭐</div>
                                        <div class="fw-bold">Rating</div>
                                        <div class="text-muted">Dettagli su contatto</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="fs-2 text-warning">🎯</div>
                                        <div class="fw-bold">Settori di Interesse</div>
                                        <div class="text-muted">Analisi disponibile</div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Contatta il brand per ottenere informazioni dettagliate sulle collaborazioni
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Contatto -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Contatta <?php echo htmlspecialchars($brand['company_name'] ?? ''); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Funzionalità di contatto in fase di sviluppo.</p>
                <p>Per ora, puoi contattare il brand tramite i contatti elencati sopra.</p>
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
                <input type="hidden" name="reported_user_id" value="<?php echo $brand['user_id']; ?>">
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
                        Stai per segnalare <strong><?php echo htmlspecialchars($brand['company_name']); ?></strong>.
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
                        
                        <div class="text-end mt-1">
                            <div class="text-muted small">
                                <span id="charCount">1000</span> caratteri rimanenti
                            </div>
                        </div>
                        
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
// Gestione Preferiti per Brand
document.addEventListener('DOMContentLoaded', function() {
    // Trova tutti i pulsanti preferiti per brand
    const favoriteButtons = document.querySelectorAll('.favorite-btn-brand');
    
    favoriteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const brandId = this.getAttribute('data-brand-id');
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
            
            // Invia richiesta AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/influencers/toggle-favorite-brand.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        
                        if (data.success) {
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
            
            xhr.send('brand_id=' + encodeURIComponent(brandId) + 
                     '&action=' + encodeURIComponent(newIsFavorite ? 'add' : 'remove'));
        });
    });
    
    // Funzione per mostrare messaggi
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
        
        // Aggiorna anche quando il modal viene aperto
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