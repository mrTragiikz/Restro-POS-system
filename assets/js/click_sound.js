(function () {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;


    let audioCtx = null;
    let unlocked = false;

    function initAudio() {
        if (!audioCtx) {
            audioCtx = new AudioContext();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    function playPopSound() {
        initAudio();
        if (!audioCtx) return;

        // Force resume again on every attempt just to be sure
        if (audioCtx.state === 'suspended') audioCtx.resume();

        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        // High-pitched notification "Blip" - more audible on phone speakers
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(200, audioCtx.currentTime + 0.1);

        gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime); // Louder for feedback
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.12);
    }

    let lastSoundTime = 0;

    // Main event handler for sound
    const handleSoundTrigger = function (e) {
        const now = Date.now();
        // Prevent double-firing (debounce) for touchstart->mousedown->click legacy chains
        if (now - lastSoundTime < 150) return;

        initAudio();

        // Targeted interactive elements
        let selectors = [
            'button', 'a', '.btn', '.table-spot', '.big-spot',
            '.cabin-spot', '.qty-btn', '.action-btn', '.nav-item',
            '.add-btn-3d', '.spot-content', '.zone-icon', '.mini-code',
            '.mini-status'
        ];

        // Specific rule for Waiter: ONLY the "+" button
        if (window.CURRENT_PORTAL === 'WAITER') {
            selectors = ['.add-btn-3d'];
        }

        const interactiveSelectors = selectors.join(', ');

        // Check for direct target or parent
        let target = e.target.closest(interactiveSelectors);

        // Fallback checks (Only for Admin/Other portals to preserve original behavior)
        if (!target && window.CURRENT_PORTAL !== 'WAITER') {
            target = (e.target.onclick ? e.target : null) ||
                (e.target.dataset.clickBound ? e.target : null) ||
                (e.target.classList.contains('table-spot') ? e.target : null);
        }

        if (target) {
            lastSoundTime = now;
            playPopSound();
        }
    };

    // Listen for BOTH click and touchstart for full coverage
    // Use capture phase (true) to trigger BEFORE page navigation or other handlers
    document.addEventListener('mousedown', handleSoundTrigger, true);
    document.addEventListener('touchstart', handleSoundTrigger, { passive: true, capture: true });

    // Explicitly handle Enter key on focused elements for accessibility
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            handleSoundTrigger(e);
        }
    }, true);

    // We removed the generic 'click' listener to avoid the ghost click ambiguity
    // mousedown handles mouse, touchstart handles touch, keydown handles keyboard.

})();
