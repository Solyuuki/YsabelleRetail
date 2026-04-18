<div class="fixed bottom-5 right-5 z-[60]" data-chat-shell>
    <div class="hidden w-80 overflow-hidden rounded-[1.6rem] border border-white/8 bg-ys-panel shadow-[0_24px_70px_rgba(0,0,0,0.55)]" data-chat-panel>
        <div class="flex items-center justify-between border-b border-white/6 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-ys-ivory">Concierge chat</p>
                <p class="text-xs text-ys-ivory/45">Visual shell only for now</p>
            </div>
            <button type="button" class="ys-icon-button h-9 w-9" data-chat-close aria-label="Close chat">
                <span class="text-lg leading-none">&times;</span>
            </button>
        </div>
        <div class="space-y-4 px-5 py-5 text-sm leading-7 text-ys-ivory/60">
            <p>Need sizing help, availability support, or premium care guidance?</p>
            <div class="rounded-2xl border border-white/6 bg-white/[0.02] px-4 py-4">
                <p class="font-medium text-ys-ivory">Chat backend not connected yet.</p>
                <p class="mt-1 text-xs text-ys-ivory/45">This shell is implemented to match the reference system and can be wired to support later.</p>
            </div>
        </div>
    </div>

    <button type="button" class="ys-chat-trigger" data-chat-toggle aria-label="Open chat">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M7 17.5 4 20V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8A2.5 2.5 0 0 1 17.5 17H7Z" stroke-linejoin="round" />
        </svg>
    </button>
</div>
