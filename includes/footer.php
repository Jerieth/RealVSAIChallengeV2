    </div> <!-- End of main content container -->
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <div class="footer-text mb-0">
                <span>Real VS AI &copy; <?= date('Y') ?></span>
                <span class="footer-links">
                   | <a href="/terms.php" class="footer-link">Terms of Use</a> |
                    <a href="/privacy.php" class="footer-link">Privacy Policy</a> |
                    <a href="/contribute.php" class="footer-link">Contribute</a> |
                    <a href="https://www.kemptongames.com/" target="_blank" class="footer-link">More Games</a> |
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                        <a href="/admin.php#upload-section" class="text-warning" onclick="switchToBulkUpload()">
                            <i class="fas fa-cloud-upload-alt me-1"></i> Bulk Upload Images
                        </a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </footer>
    
    <!-- Script to switch to bulk upload tab -->
    <script>
        function switchToBulkUpload() {
            // When the link is clicked, set a sessionStorage flag
            sessionStorage.setItem('openBulkUpload', 'true');
        }
        
        // Check if we need to open the bulk upload tab
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash === '#upload-section' || sessionStorage.getItem('openBulkUpload') === 'true') {
                const bulkTab = document.getElementById('bulk-tab');
                if (bulkTab) {
                    bulkTab.click();
                    // Scroll to the upload section
                    const uploadSection = document.getElementById('upload-section');
                    if (uploadSection) {
                        uploadSection.scrollIntoView({ behavior: 'smooth' });
                    }
                    // Clear the flag
                    sessionStorage.removeItem('openBulkUpload');
                }
            }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Debug Logger - Must be included before other JS files -->
    <script src="/static/js/debugLogger.js"></script>
    
    <!-- Custom JS (only loaded if file exists) -->
    <?php if (file_exists(__DIR__ . '/../static/js/' . basename($_SERVER['PHP_SELF'], '.php') . '.js')): ?>
        <script src="/static/js/<?= basename($_SERVER['PHP_SELF'], '.php') ?>.js"></script>
    <?php endif; ?>
    
    <!-- Load profile.js specifically for profile page -->
    <?php if (basename($_SERVER['PHP_SELF']) === 'profile.php'): ?>
        <!-- Confetti temporarily disabled for troubleshooting -->
        <!-- <script src="/static/js/confetti.min.js"></script> -->
        <!-- Then load profile.js -->
        <script src="/static/js/profile.js"></script>
    <?php endif; ?>
    
    <!-- Common validation JS -->
    <script src="/static/js/validation.js"></script>
    
    <!-- Achievement notifications system -->
    <script src="/static/js/achievements.js"></script>
</body>
</html>