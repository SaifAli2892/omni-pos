    </main>

    <!-- Global Toast Notifications -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Global Thermal Receipt Container (Hidden on screen, visible only when printing) -->
    <div id="thermal-receipt" class="hidden"></div>

    <script>
        // Simple Clean Toast Notification Utility
        window.showToast = function(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-slate-900 text-white border-slate-800' :
                           (type === 'error' ? 'bg-rose-600 text-white border-rose-700' : 'bg-slate-900 text-white border-slate-800');

            toast.className = `px-4 py-3 rounded-xl shadow-lg border text-xs font-semibold flex items-center gap-2 transform transition-all duration-200 translate-y-3 opacity-0 pointer-events-auto ${bgClass}`;
            toast.innerText = message;

            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            }, 10);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 250);
            }, 3000);
        };
    </script>
</body>
</html>
