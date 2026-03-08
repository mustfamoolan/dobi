<!-- Begin Footer -->
<footer class="footer d-flex align-items-center text-center">
    <div class="container-fluid">
        <p class="mb-0">
            © {{ date('Y') }} تم تطوير النظام بواسطة <a href="https://almoq3.com" target="_blank">ALMOQ3</a>.
        </p>
    </div>
</footer>
<!-- END Footer -->

<script>
    function hidePreloader() {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.classList.add('hidden');
            // Force hide if class is not enough
            preloader.style.display = 'none';
        }
    }

    document.addEventListener('livewire:init', () => {
        hidePreloader();
    });

    document.addEventListener('livewire:navigated', () => {
        hidePreloader();
        // Re-initialize layout functionality if needed
        if (typeof updateLayout === 'function') {
            updateLayout();
        }
    });

    // Fallback
    window.addEventListener('load', hidePreloader);
</script>