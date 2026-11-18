</main>
</div>
<!-- Footer -->
<footer>
    <div class="px-4 text-center text-gray-500">
        <?php if (isset($_SESSION['user'])): ?>
            <p>
                Anda login sebagai: 
                <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong> 
            </p>
        <?php endif; ?>
        <p class="mt-2">&copy; 2025 Sistem Logbook PKL - Semua Hak Dilindungi</p>
    </div>
</footer>

</body>
</html>
