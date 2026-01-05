    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> GameHub. All rights reserved.</p>
            <p>Current Server Time: <span id="server-time"><?php echo date('Y-m-d H:i:s'); ?></span></p>
        </div>
    </footer>
    
    <script src="<?php echo isset($base_url) ? $base_url : '../'; ?>assets/js/main.js"></script>
</body>
</html>
