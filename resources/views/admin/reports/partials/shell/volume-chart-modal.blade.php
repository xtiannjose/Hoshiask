<dialog id="volume-chart-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-950/45 backdrop-blur-[1px]" data-modal-overlay="volume-chart"></div>
    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-6xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 id="volume-chart-modal-title" class="font-display text-lg font-semibold text-slate-900">Volume Chart</h3>
                <button type="button" class="btn-secondary px-3 py-1.5 text-xs" data-modal-close="volume-chart">Close</button>
            </div>
            <div id="volume-chart-modal-content" class="max-h-[78vh] overflow-auto p-5"></div>
        </div>
    </div>
</dialog>
