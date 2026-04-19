const trustMarqueeStates = new WeakMap();
const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

const resolveTrustMarqueeWidth = (viewport) => {
    const track = viewport.querySelector('.ys-trust-marquee-track');
    const groups = track ? [...track.querySelectorAll(':scope > .ys-trust-marquee-group')] : [];

    if (!track || groups.length !== 2) {
        return;
    }

    groups.forEach((group) => {
        group.style.width = '';
        group.style.flexBasis = '';
    });

    track.style.width = '';
    track.style.removeProperty('--trust-marquee-distance');

    const widths = groups.map((group) => group.getBoundingClientRect().width);
    const exactWidth = Math.max(...widths);

    if (!Number.isFinite(exactWidth) || exactWidth <= 0) {
        return;
    }

    const widthValue = `${exactWidth.toFixed(3)}px`;
    const trackWidth = `${(exactWidth * groups.length).toFixed(3)}px`;

    groups.forEach((group) => {
        group.style.width = widthValue;
        group.style.flexBasis = widthValue;
    });

    track.style.width = trackWidth;
    track.style.setProperty('--trust-marquee-distance', widthValue);

    return exactWidth;
};

const resolveTrustMarqueeSpeed = (viewportWidth) => {
    if (viewportWidth >= 1024) {
        return 28;
    }

    if (viewportWidth >= 640) {
        return 24;
    }

    return 21;
};

const applyTrustMarqueePhaseOffset = (viewport, exactWidth) => {
    const track = viewport.querySelector('.ys-trust-marquee-track');

    if (!track || !Number.isFinite(exactWidth) || exactWidth <= 0) {
        return;
    }

    const viewportWidth = viewport.getBoundingClientRect().width;
    const overflowSlack = Math.max(exactWidth - viewportWidth, 0);

    const phase = overflowSlack > 0
        ? Math.min(24, overflowSlack)
            : 0;

    const offscreenRight = Math.max(overflowSlack - phase, 0);

    track.style.setProperty('--trust-marquee-phase', `${phase.toFixed(3)}px`);
    track.dataset.phaseOffset = phase.toFixed(3);
    track.dataset.handoffOutsideRight = offscreenRight.toFixed(3);
    track.dataset.phaseOffsetApplied = 'true';
};

const applyTrustMarqueeTransform = (track, x) => {
    track.style.transform = `translate3d(${x.toFixed(3)}px, 0, 0)`;
};

const normalizeTrustMarqueeOffset = (offset, groupWidth) => {
    if (!Number.isFinite(groupWidth) || groupWidth <= 0) {
        return 0;
    }

    return ((offset % groupWidth) + groupWidth) % groupWidth;
};

const stopTrustMarqueeTicker = (viewport) => {
    const state = trustMarqueeStates.get(viewport);

    if (!state) {
        return;
    }

    if (state.frame) {
        cancelAnimationFrame(state.frame);
        state.frame = null;
    }

    state.running = false;
    state.lastTime = null;
};

const startTrustMarqueeTicker = (viewport) => {
    const state = trustMarqueeStates.get(viewport);

    if (!state || state.running || reducedMotionQuery.matches) {
        return;
    }

    state.running = true;

    const step = (timestamp) => {
        if (!state.running) {
            return;
        }

        if (state.lastTime === null) {
            state.lastTime = timestamp;
        } else {
            const deltaSeconds = Math.min((timestamp - state.lastTime) / 1000, 0.05);
            state.lastTime = timestamp;
            state.x -= state.speed * deltaSeconds;

            const wrapBoundary = -(state.phase + state.groupWidth);

            while (state.x <= wrapBoundary) {
                state.x += state.groupWidth;
            }
        }

        applyTrustMarqueeTransform(state.track, state.x);
        state.track.dataset.transport = 'raf-ticker';
        state.track.dataset.speed = state.speed.toFixed(3);
        state.frame = requestAnimationFrame(step);
    };

    state.frame = requestAnimationFrame(step);
};

const syncTrustMarqueeTicker = (viewport, exactWidth) => {
    const track = viewport.querySelector('.ys-trust-marquee-track');

    if (!track || !Number.isFinite(exactWidth) || exactWidth <= 0) {
        return;
    }

    const viewportWidth = viewport.getBoundingClientRect().width;
    const phase = Number.parseFloat(getComputedStyle(track).getPropertyValue('--trust-marquee-phase')) || 0;
    const speed = resolveTrustMarqueeSpeed(viewportWidth);
    const existingState = trustMarqueeStates.get(viewport);
    const nextState = existingState ?? {
        track,
        x: -phase,
        frame: null,
        running: false,
        lastTime: null,
        groupWidth: exactWidth,
        phase,
        speed,
    };

    const progressDistance = existingState
        ? normalizeTrustMarqueeOffset((-existingState.x) - existingState.phase, existingState.groupWidth)
        : 0;
    const progressRatio = existingState && existingState.groupWidth > 0
        ? progressDistance / existingState.groupWidth
        : 0;

    nextState.track = track;
    nextState.groupWidth = exactWidth;
    nextState.phase = phase;
    nextState.speed = speed;
    nextState.x = -(phase + (progressRatio * exactWidth));
    nextState.lastTime = null;

    applyTrustMarqueeTransform(track, nextState.x);
    track.style.animation = 'none';
    track.dataset.transport = 'raf-ticker';
    track.dataset.speed = speed.toFixed(3);
    track._ysTrustTicker = nextState;

    trustMarqueeStates.set(viewport, nextState);

    if (reducedMotionQuery.matches) {
        stopTrustMarqueeTicker(viewport);
        return;
    }

    stopTrustMarqueeTicker(viewport);
    startTrustMarqueeTicker(viewport);
};

const applyTrustItemMotionVariance = (viewport) => {
    const items = [...viewport.querySelectorAll('.ys-trust-item')];

    if (!items.length) {
        return;
    }

    items.forEach((item, index) => {
        if (item.dataset.motionVarianceApplied === 'true') {
            return;
        }

        const wave = index / Math.max(items.length - 1, 1);
        const jitter = () => (Math.random() - 0.5) * 0.8;

        item.style.setProperty('--trust-glint-duration', `${(8.7 + wave * 1.9 + jitter()).toFixed(2)}s`);
        item.style.setProperty('--trust-glint-delay', `${(-6.8 * Math.random()).toFixed(2)}s`);
        item.style.setProperty('--trust-icon-duration', `${(5.8 + wave * 1.4 + jitter()).toFixed(2)}s`);
        item.style.setProperty('--trust-icon-delay', `${(-4.6 * Math.random()).toFixed(2)}s`);
        item.style.setProperty('--trust-icon-drift-duration', `${(6.6 + wave * 1.5 + jitter()).toFixed(2)}s`);
        item.style.setProperty('--trust-icon-drift-delay', `${(-5.8 * Math.random()).toFixed(2)}s`);
        item.style.setProperty('--trust-copy-duration', `${(8.1 + wave * 1.8 + jitter()).toFixed(2)}s`);
        item.style.setProperty('--trust-copy-delay', `${(-7.1 * Math.random()).toFixed(2)}s`);
        item.style.setProperty('--trust-sparkle-duration', `${(5.2 + wave * 1.6 + jitter()).toFixed(2)}s`);
        item.style.setProperty('--trust-sparkle-delay', `${(-5.4 * Math.random()).toFixed(2)}s`);
        item.dataset.motionVarianceApplied = 'true';
    });
};

const applyTrustStripAmbientVariance = (viewport) => {
    const strip = viewport.closest('.ys-trust-strip');

    if (!strip || strip.dataset.ambientVarianceApplied === 'true') {
        return;
    }

    strip.style.setProperty('--trust-strip-sweep-duration', `${(16.8 + Math.random() * 4.2).toFixed(2)}s`);
    strip.style.setProperty('--trust-strip-sweep-delay', `${(-5.2 * Math.random()).toFixed(2)}s`);
    strip.style.setProperty('--trust-strip-glow-duration', `${(20.5 + Math.random() * 5.4).toFixed(2)}s`);
    strip.style.setProperty('--trust-strip-glow-delay', `${(-7.8 * Math.random()).toFixed(2)}s`);
    strip.dataset.ambientVarianceApplied = 'true';
};

export const initTrustMarquees = () => {
    const viewports = document.querySelectorAll('.ys-trust-marquee-viewport');

    if (!viewports.length) {
        return;
    }

    viewports.forEach((viewport) => {
        let frame = null;

        const sync = () => {
            if (frame) {
                cancelAnimationFrame(frame);
            }

            frame = requestAnimationFrame(() => {
                const exactWidth = resolveTrustMarqueeWidth(viewport);
                applyTrustMarqueePhaseOffset(viewport, exactWidth);
                syncTrustMarqueeTicker(viewport, exactWidth);
                frame = null;
            });
        };

        applyTrustItemMotionVariance(viewport);
        applyTrustStripAmbientVariance(viewport);
        sync();

        if (document.fonts?.ready) {
            document.fonts.ready.then(sync).catch(() => {});
        }

        const resizeObserver = new ResizeObserver(sync);
        resizeObserver.observe(viewport);

        reducedMotionQuery.addEventListener('change', () => {
            const exactWidth = trustMarqueeStates.get(viewport)?.groupWidth;

            if (!Number.isFinite(exactWidth) || exactWidth <= 0) {
                sync();
                return;
            }

            syncTrustMarqueeTicker(viewport, exactWidth);
        });
    });
};
