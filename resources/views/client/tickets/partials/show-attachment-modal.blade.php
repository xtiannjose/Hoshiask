<dialog id="attachment-modal" class="app-modal-root">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-close="true"></div>
    <div class="relative z-10 min-h-dvh flex items-center justify-center p-4 pointer-events-none">
        <div class="app-modal-panel pointer-events-auto w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="attachment-modal-title" class="text-sm font-medium text-slate-900">Attachment Preview</h3>
                <button type="button" id="attachment-modal-close" aria-label="Close" class="text-xl leading-none text-slate-500 hover:text-slate-700">&times;</button>
            </div>
            <div class="bg-slate-50 p-4">
                <img id="attachment-modal-image" class="hidden w-auto max-w-full h-auto max-h-[75vh] mx-auto" alt="Attachment preview">
                <iframe id="attachment-modal-frame" class="hidden w-full h-[75vh] border-0 bg-white"></iframe>
            </div>
        </div>
    </div>
</dialog>
