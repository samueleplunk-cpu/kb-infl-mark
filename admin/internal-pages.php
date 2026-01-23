<?php
require_once '../includes/admin_header.php';

// Titolo della pagina
$page_title = "Gestione Pagine Interne";

// Funzioni per la gestione delle pagine
require_once '../includes/internal_pages_functions.php';

// Gestione azioni
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        handleAddPage();
        break;
    case 'edit':
        handleEditPage();
        break;
    case 'delete':
        handleDeletePage();
        break;
    case 'toggle_status':
        handleTogglePageStatus();
        break;
    case 'preview':
        // L'anteprima è gestita separatamente
        break;
}

// Ottieni lista pagine
$pages = getAllPages();

// Ottieni l'URL base del sito
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>Gestione Pagine Interne
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">
            <i class="fas fa-plus me-1"></i> Nuova Pagina
        </button>
    </div>
</div>

<!-- Lista Pagine -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list me-2"></i>Lista Pagine
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($pages)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nessuna pagina trovata. Crea la tua prima pagina!
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Titolo</th>
                            <th>Slug/URL</th>
                            <th>Meta Title</th>
                            <th>Stato</th>
                            <th>Ultima Modifica</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($page['title']); ?></strong>
                                </td>
                                <td>
                                    <code>/page/<?php echo htmlspecialchars($page['slug']); ?></code>
                                    <br>
                                    <small class="text-muted">
                                        <a href="<?php echo $base_url; ?>/page/<?php echo htmlspecialchars($page['slug']); ?>" target="_blank">
                                            <i class="fas fa-external-link-alt me-1"></i>Vedi nel sito
                                        </a>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($page['meta_title'] ?: '-'); ?>
                                </td>
                                <td>
                                    <?php if ($page['is_active']): ?>
                                        <span class="badge bg-success">Attiva</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disattivata</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $page['updated_at'] ? date('d/m/Y H:i', strtotime($page['updated_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Anteprima -->
                                        <a href="<?php echo $base_url; ?>/page/<?php echo htmlspecialchars($page['slug']); ?>" 
                                           class="btn btn-outline-info" 
                                           target="_blank"
                                           title="Anteprima">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- Modifica -->
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPageModal"
                                                data-page-id="<?php echo $page['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($page['title']); ?>"
                                                data-slug="<?php echo htmlspecialchars($page['slug']); ?>"
                                                data-content='<?php echo htmlspecialchars($page['content'], ENT_QUOTES); ?>'
                                                data-meta-title="<?php echo htmlspecialchars($page['meta_title'] ?? ''); ?>"
                                                data-meta-description="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>"
                                                data-is-active="<?php echo $page['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Attiva/Disattiva -->
                                        <?php if ($page['is_active']): ?>
                                            <button type="button" class="btn btn-outline-warning toggle-status"
                                                    data-page-id="<?php echo $page['id']; ?>"
                                                    data-action="deactivate"
                                                    title="Disattiva">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-success toggle-status"
                                                    data-page-id="<?php echo $page['id']; ?>"
                                                    data-action="activate"
                                                    title="Attiva">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Elimina -->
                                        <button type="button" class="btn btn-outline-danger delete-page"
                                                data-page-id="<?php echo $page['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($page['title']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Aggiungi Pagina -->
<div class="modal fade" id="addPageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="addPageForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Nuova Pagina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Titolo Pagina *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="slug" class="form-label">Slug/URL *</label>
                            <div class="input-group">
                                <span class="input-group-text">/page/</span>
                                <input type="text" class="form-control" id="slug" name="slug" required>
                            </div>
                            <div class="form-text">Solo lettere minuscole, numeri e trattini (es: termini-e-condizioni)</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Contenuto *</label>
                        <textarea class="form-control summernote" id="content" name="content" rows="10" required></textarea>
                        <div class="form-text">Supporta HTML. Per il testo semplice, usa &lt;p&gt;paragrafo&lt;/p&gt;</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="meta_title" class="form-label">Meta Title (SEO)</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="60">
                            <div class="form-text">Massimo 60 caratteri</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">
                                    Pagina attiva
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meta_description" class="form-label">Meta Description (SEO)</label>
                        <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="160"></textarea>
                        <div class="form-text">Massimo 160 caratteri</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea Pagina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Pagina -->
<div class="modal fade" id="editPageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="editPageForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_page_id" name="page_id">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Pagina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_title" class="form-label">Titolo Pagina *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_slug" class="form-label">Slug/URL *</label>
                            <div class="input-group">
                                <span class="input-group-text">/page/</span>
                                <input type="text" class="form-control" id="edit_slug" name="slug" required>
                            </div>
                            <div class="form-text">Solo lettere minuscole, numeri e trattini</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Contenuto *</label>
                        <textarea class="form-control summernote-edit" id="edit_content" name="content" rows="10" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_meta_title" class="form-label">Meta Title (SEO)</label>
                            <input type="text" class="form-control" id="edit_meta_title" name="meta_title" maxlength="60">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                                <label class="form-check-label" for="edit_is_active">
                                    Pagina attiva
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_meta_description" class="form-label">Meta Description (SEO)</label>
                        <textarea class="form-control" id="edit_meta_description" name="meta_description" rows="3" maxlength="160"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Includi jQuery e Summernote (leggero e gratuito) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<!-- Summernote Italiano -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-it-IT.min.js"></script>

<script>
// Inizializza Summernote
$(document).ready(function() {
    $('.summernote').summernote({
        height: 300,
        lang: 'it-IT',
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']],
            ['insert', ['link', 'picture', 'video', 'table', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        placeholder: 'Scrivi il contenuto della pagina qui...'
    });
    
    // Inizializza Summernote per la modifica
    $('.summernote-edit').summernote({
        height: 300,
        lang: 'it-IT',
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']],
            ['insert', ['link', 'picture', 'video', 'table', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});

// Genera automaticamente lo slug dal titolo
document.getElementById('title').addEventListener('input', function() {
    const title = this.value;
    const slugInput = document.getElementById('slug');
    
    if (slugInput.value === '') {
        // Genera slug solo se il campo è vuoto
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '') // Rimuove caratteri speciali
            .replace(/\s+/g, '-')     // Sostituisce spazi con trattini
            .replace(/[àáâãäå]/g, 'a')
            .replace(/[èéêë]/g, 'e')
            .replace(/[ìíîï]/g, 'i')
            .replace(/[òóôõö]/g, 'o')
            .replace(/[ùúûü]/g, 'u')
            .replace(/ç/g, 'c')
            .replace(/--+/g, '-')     // Rimuove trattini multipli
            .trim();
        
        slugInput.value = slug;
    }
});

// Gestione modali
document.addEventListener('DOMContentLoaded', function() {
    // Modal modifica pagina
    const editPageModal = document.getElementById('editPageModal');
    if (editPageModal) {
        editPageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const pageId = button.getAttribute('data-page-id');
            const title = button.getAttribute('data-title');
            const slug = button.getAttribute('data-slug');
            const content = button.getAttribute('data-content');
            const metaTitle = button.getAttribute('data-meta-title');
            const metaDescription = button.getAttribute('data-meta-description');
            const isActive = button.getAttribute('data-is-active') === '1';
            
            document.getElementById('edit_page_id').value = pageId;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_slug').value = slug;
            document.getElementById('edit_meta_title').value = metaTitle;
            document.getElementById('edit_meta_description').value = metaDescription;
            document.getElementById('edit_is_active').checked = isActive;
            
            // Imposta il contenuto in Summernote
            $('#edit_content').summernote('code', content);
        });
    }
    
    // Pulisci Summernote quando il modal viene chiuso
    $('#addPageModal').on('hidden.bs.modal', function() {
        $('#content').summernote('reset');
    });
    
    $('#editPageModal').on('hidden.bs.modal', function() {
        $('#edit_content').summernote('reset');
    });
    
    // Conferma eliminazione
    document.querySelectorAll('.delete-page').forEach(button => {
        button.addEventListener('click', function() {
            const pageId = this.getAttribute('data-page-id');
            const title = this.getAttribute('data-title');
            
            if (confirm(`Sei sicuro di voler eliminare la pagina "${title}"?\n\nQuesta azione è irreversibile.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'page_id';
                idInput.value = pageId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Attiva/Disattiva pagina
    document.querySelectorAll('.toggle-status').forEach(button => {
        button.addEventListener('click', function() {
            const pageId = this.getAttribute('data-page-id');
            const action = this.getAttribute('data-action');
            const statusText = action === 'activate' ? 'attivare' : 'disattivare';
            const title = this.closest('tr').querySelector('td:first-child strong').textContent;
            
            if (confirm(`Sei sicuro di voler ${statusText} la pagina "${title}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'toggle_status';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'page_id';
                idInput.value = pageId;
                form.appendChild(idInput);
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status_action';
                statusInput.value = action;
                form.appendChild(statusInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Validazione slug
    function validateSlug(slug) {
        return /^[a-z0-9-]+$/.test(slug);
    }
    
    // Validazione form aggiunta
    const addPageForm = document.getElementById('addPageForm');
    if (addPageForm) {
        addPageForm.addEventListener('submit', function(e) {
            const slug = document.getElementById('slug').value;
            const title = document.getElementById('title').value;
            
            if (!title.trim()) {
                e.preventDefault();
                alert('Il titolo della pagina è obbligatorio!');
                document.getElementById('title').focus();
                return;
            }
            
            if (!validateSlug(slug)) {
                e.preventDefault();
                alert('Lo slug può contenere solo lettere minuscole, numeri e trattini!\nEsempio: termini-e-condizioni');
                document.getElementById('slug').focus();
            }
        });
    }
    
    // Validazione form modifica
    const editPageForm = document.getElementById('editPageForm');
    if (editPageForm) {
        editPageForm.addEventListener('submit', function(e) {
            const slug = document.getElementById('edit_slug').value;
            
            if (!validateSlug(slug)) {
                e.preventDefault();
                alert('Lo slug può contenere solo lettere minuscole, numeri e trattini!\nEsempio: termini-e-condizioni');
                document.getElementById('edit_slug').focus();
            }
        });
    }
});
</script>

<?php
require_once '../includes/admin_footer.php';