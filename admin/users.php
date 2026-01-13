<?php
require_once '../includes/admin_header.php';

// Titolo della pagina
$page_title = "Gestione Amministratori";

// Verifica permessi super admin
if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    $_SESSION['error_message'] = "Accesso negato. Solo i Super Admin possono gestire gli amministratori.";
    header("Location: dashboard.php");
    exit();
}

// Funzioni per la gestione degli admin
require_once '../includes/admin_users_functions.php';

// Gestione azioni
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        handleAddAdmin();
        break;
    case 'edit':
        handleEditAdmin();
        break;
    case 'delete':
        handleDeleteAdmin();
        break;
    case 'toggle_status':
        handleToggleAdminStatus();
        break;
}

// Ottieni lista amministratori
$admins = getAllAdmins();
$current_admin_id = $_SESSION['admin_id'];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>Gestione Amministratori
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-plus me-1"></i> Nuovo Admin
        </button>
    </div>
</div>

<!-- Lista Amministratori -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list me-2"></i>Lista Amministratori
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($admins)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nessun amministratore trovato.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Nome Completo</th>
                            <th>Tipo</th>
                            <th>Ultimo Login</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                                    <?php if ($admin['id'] == $current_admin_id): ?>
                                        <span class="badge bg-info ms-1">Tu</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['full_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($admin['is_super_admin']): ?>
                                        <span class="badge bg-danger">Super Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $admin['last_login'] ? date('d/m/Y H:i', strtotime($admin['last_login'])) : 'Mai'; ?>
                                </td>
                                <td>
                                    <?php if ($admin['is_active']): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disattivato</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modifica -->
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAdminModal"
                                                data-admin-id="<?php echo $admin['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($admin['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($admin['email']); ?>"
                                                data-full-name="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                                                data-is-super-admin="<?php echo $admin['is_super_admin'] ? '1' : '0'; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Cambia Password -->
                                        <button type="button" class="btn btn-outline-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#changePasswordModal"
                                                data-admin-id="<?php echo $admin['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($admin['username']); ?>">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        
                                        <!-- Attiva/Disattiva -->
                                        <?php if ($admin['id'] != $current_admin_id): ?>
                                            <?php if ($admin['is_active']): ?>
                                                <button type="button" class="btn btn-outline-warning toggle-status"
                                                        data-admin-id="<?php echo $admin['id']; ?>"
                                                        data-action="deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success toggle-status"
                                                        data-admin-id="<?php echo $admin['id']; ?>"
                                                        data-action="activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Elimina -->
                                            <button type="button" class="btn btn-outline-danger delete-admin"
                                                    data-admin-id="<?php echo $admin['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($admin['username']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="btn btn-outline-secondary disabled">
                                                <i class="fas fa-user"></i>
                                            </span>
                                        <?php endif; ?>
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

<!-- Modal Aggiungi Admin -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Nuovo Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="form-text">Username univoco per il login</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <div class="form-text">Minimo 6 caratteri</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Conferma Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_super_admin" name="is_super_admin" value="1">
                            <label class="form-check-label" for="is_super_admin">
                                Super Admin (accesso completo a tutte le funzionalità)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Admin -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_admin_id" name="admin_id">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_super_admin" name="is_super_admin" value="1">
                            <label class="form-check-label" for="edit_is_super_admin">
                                Super Admin (accesso completo a tutte le funzionalità)
                            </label>
                        </div>
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

<!-- Modal Cambia Password -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" id="password_admin_id" name="admin_id">
                <div class="modal-header">
                    <h5 class="modal-title">Cambia Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nuova Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="form-text">Minimo 6 caratteri</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Conferma Nuova Password *</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Cambia Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestione modali
document.addEventListener('DOMContentLoaded', function() {
    // Modal modifica admin
    const editAdminModal = document.getElementById('editAdminModal');
    if (editAdminModal) {
        editAdminModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const adminId = button.getAttribute('data-admin-id');
            const username = button.getAttribute('data-username');
            const email = button.getAttribute('data-email');
            const fullName = button.getAttribute('data-full-name');
            const isSuperAdmin = button.getAttribute('data-is-super-admin');
            
            document.getElementById('edit_admin_id').value = adminId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_is_super_admin').checked = isSuperAdmin === '1';
        });
    }
    
    // Modal cambia password
    const changePasswordModal = document.getElementById('changePasswordModal');
    if (changePasswordModal) {
        changePasswordModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const adminId = button.getAttribute('data-admin-id');
            const username = button.getAttribute('data-username');
            
            document.getElementById('password_admin_id').value = adminId;
            document.querySelector('#changePasswordModal .modal-title').textContent = 
                `Cambia Password - ${username}`;
        });
    }
    
    // Conferma eliminazione DEFINITIVA
    document.querySelectorAll('.delete-admin').forEach(button => {
        button.addEventListener('click', function() {
            const adminId = this.getAttribute('data-admin-id');
            const username = this.getAttribute('data-username');
            
            if (confirm(`⚠️ ELIMINAZIONE DEFINITIVA ⚠️\n\nSei sicuro di voler ELIMINARE DEFINITIVAMENTE l'admin "${username}"?\n\nQuesta azione è IRREVERSIBILE e cancellerà completamente l'admin dal database.\n\nNon sarà possibile recuperare i dati.`)) {
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
                idInput.name = 'admin_id';
                idInput.value = adminId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Attiva/Disattiva admin
    document.querySelectorAll('.toggle-status').forEach(button => {
        button.addEventListener('click', function() {
            const adminId = this.getAttribute('data-admin-id');
            const action = this.getAttribute('data-action');
            const statusText = action === 'activate' ? 'attivare' : 'disattivare';
            const username = this.closest('tr').querySelector('td:first-child strong').textContent;
            
            if (confirm(`Sei sicuro di voler ${statusText} l'admin "${username}"?`)) {
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
                idInput.name = 'admin_id';
                idInput.value = adminId;
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

    // Validazione form aggiunta admin - controllo password
    const addAdminForm = document.querySelector('#addAdminModal form');
    if (addAdminForm) {
        addAdminForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Le password non coincidono!');
                document.getElementById('password').focus();
            }
        });
    }

    // Validazione form cambio password
    const changePasswordForm = document.querySelector('#changePasswordModal form');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmNewPassword = document.getElementById('confirm_new_password').value;
            
            if (newPassword !== confirmNewPassword) {
                e.preventDefault();
                alert('Le password non coincidono!');
                document.getElementById('new_password').focus();
            }
        });
    }
});
</script>

<?php
require_once '../includes/admin_footer.php';
?>