(() => {
    console.log('[AEGIS SCAN] loaded');
    const DETECT_INTERVAL = 260;
    const COOLDOWN_MS = 1200;
    const FORMATS = ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_39'];
    let activeSession = null;

    const stopSession = () => {
        if (!activeSession) {
            return;
        }
        const { overlay, stream, intervalId, video } = activeSession;
        if (intervalId) {
            clearInterval(intervalId);
        }
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
        }
        if (video) {
            video.pause();
            video.srcObject = null;
        }
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.hidden = true;
        }
        activeSession = null;
    };

    const setStatus = (overlay, message) => {
        const status = overlay.querySelector('.aegis-scan-status');
        if (status) {
            status.textContent = message;
            status.hidden = false;
        }
    };

    const openScanner = (trigger) => {
        console.log('[AEGIS SCAN] open');
        const container = trigger.closest('.aegis-inbound-page, .aegis-shipments-page') || document.body;
        const overlay = container.querySelector('.aegis-scan-overlay');
        if (!overlay) {
            return;
        }
        stopSession();
        overlay.hidden = false;
        overlay.classList.add('is-open');
        activeSession = {
            overlay,
            stream: null,
            intervalId: null,
            video: overlay.querySelector('.aegis-scan-video'),
        };
        setStatus(overlay, '正在启动相机...');

        if (!('BarcodeDetector' in window)) {
            setStatus(overlay, '当前浏览器不支持相机扫码，请手动输入。');
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus(overlay, '当前浏览器不支持相机扫码，请手动输入。');
            return;
        }

        let detector;
        try {
            detector = new BarcodeDetector({ formats: FORMATS });
        } catch (error) {
            setStatus(overlay, '当前浏览器不支持相机扫码，请手动输入。');
            return;
        }

        const video = activeSession.video;
        const inputSelector = trigger.getAttribute('data-target-input');
        const submitSelector = trigger.getAttribute('data-target-submit');
        const input = inputSelector ? document.querySelector(inputSelector) : null;
        const submit = submitSelector ? document.querySelector(submitSelector) : null;

        navigator.mediaDevices
            .getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            })
            .then((stream) => {
                if (!video) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }
                video.srcObject = stream;
                video.play();
                setStatus(overlay, '对准条码，自动识别。');

                let lastValue = '';
                let lastTime = 0;
                let detecting = false;

                const intervalId = setInterval(() => {
                    if (!video || video.readyState < 2 || detecting) {
                        return;
                    }
                    detecting = true;
                    detector
                        .detect(video)
                        .then((codes) => {
                            if (!codes || codes.length === 0) {
                                return;
                            }
                            const rawValue = codes[0].rawValue || '';
                            if (!rawValue) {
                                return;
                            }
                            const now = Date.now();
                            if (rawValue === lastValue && now - lastTime < COOLDOWN_MS) {
                                return;
                            }
                            lastValue = rawValue;
                            lastTime = now;

                            if (input) {
                                input.value = rawValue;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                            }

                            if (submit) {
                                submit.click();
                                setStatus(overlay, `已识别：${rawValue}`);
                            } else {
                                setStatus(overlay, `已识别：${rawValue}，请点击加入。`);
                            }

                            if (navigator.vibrate) {
                                navigator.vibrate(50);
                            }
                        })
                        .catch(() => {})
                        .finally(() => {
                            detecting = false;
                        });
                }, DETECT_INTERVAL);

                activeSession = {
                    overlay,
                    stream,
                    intervalId,
                    video,
                };
            })
            .catch(() => {
                setStatus(overlay, '相机权限被拒绝，请手动输入。');
            });
    };

    const init = () => {
        const triggers = document.querySelectorAll('[data-aegis-scan="1"]');
        if (!triggers.length) {
            return;
        }
        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => openScanner(trigger));
        });

        document.querySelectorAll('.aegis-scan-overlay').forEach((overlay) => {
            const closeButton = overlay.querySelector('.aegis-scan-close');
            if (closeButton) {
                closeButton.addEventListener('click', stopSession);
            }
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    stopSession();
                }
            });
        });

        window.addEventListener('beforeunload', stopSession);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
