<?php
// includes/footer.php

// Carica le impostazioni del footer B&I
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/page_functions.php';

$footer_bi_settings = get_footer_bi_settings();
?>
    </main> <!-- Chiude il <main> aperto in header.php -->

    <!-- Footer Dinamico B&I -->
    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <!-- Colonna 1: Titolo e Descrizione -->
                <div class="col-md-3">
                    <h5><?php echo htmlspecialchars($footer_bi_settings['title'] ?? 'Influencer Marketplace'); ?></h5>
                    <p class="mb-2"><?php echo htmlspecialchars($footer_bi_settings['description'] ?? 'La piattaforma per connettere influencer e brand.'); ?></p>
                </div>
                
                <!-- Colonna 2: Link Utili -->
                <div class="col-md-3">
                    <h6>Link Utili</h6>
                    <ul class="list-unstyled">
                        <?php foreach ($footer_bi_settings['useful_links'] ?? [] as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                   class="text-light text-decoration-none"
                                   <?php echo !empty($link['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php echo htmlspecialchars($link['label']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Colonna 3: Nuova Colonna -->
                <div class="col-md-3">
                    <h6><?php echo htmlspecialchars($footer_bi_settings['new_column_title'] ?? 'Nuova Colonna'); ?></h6>
                    <ul class="list-unstyled">
                        <?php foreach ($footer_bi_settings['new_column_links'] ?? [] as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                   class="text-light text-decoration-none"
                                   <?php echo !empty($link['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php echo htmlspecialchars($link['label']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Colonna 4: Seguici su - Social Media -->
<div class="col-md-3">
    <h6><?php echo htmlspecialchars($footer_bi_settings['social_column_title'] ?? 'Seguici su'); ?></h6>
    <div class="social-icons">
        <?php foreach ($footer_bi_settings['social_links'] ?? [] as $social): ?>
            <a href="<?php echo htmlspecialchars($social['url']); ?>" 
               class="text-light me-2 social-link d-inline-block"
               aria-label="<?php echo htmlspecialchars($social['platform']); ?>"
               target="_blank" rel="noopener noreferrer">
                <i class="<?php echo htmlspecialchars($social['icon']); ?> fa-lg"></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>
            </div>
            <hr class="my-3 bg-light">
<div class="text-center">
    <?php
    // Gestione testo copyright dinamico
    $copyright_text = $footer_bi_settings['copyright'] ?? '© 2025 Influencer Marketplace. Tutti i diritti riservati.';
    $copyright_text = str_replace('{year}', date('Y'), $copyright_text);
    ?>
    <p class="mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
</div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>