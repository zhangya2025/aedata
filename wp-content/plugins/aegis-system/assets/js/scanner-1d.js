(() => {
    const DETECT_INTERVAL = 260;
    const COOLDOWN_MS = 1200;
    const FORMATS = ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_39'];
    const QUAGGA_READERS = [
        'code_128_reader',
        'ean_reader',
        'ean_8_reader',
        'upc_reader',
        'upc_e_reader',
        'code_39_reader',
    ];
    let activeSession = null;

    const lockBody = () => document.body.classList.add('aegis-scan-open');
    const unlockBody = () => document.body.classList.remove('aegis-scan-open');

    const ensureVideoAttributes = (video) => {
        if (!video) {
            return;
        }
        video.muted = true;
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        video.setAttribute('autoplay', '');
    };

    const moveOverlayToBody = (overlay) => {
        if (!overlay) {
            return { parent: null, nextSibling: null, moved: false };
        }
        const parent = overlay.parentElement;
        const nextSibling = overlay.nextSibling;
        if (parent && parent !== document.body) {
            document.body.appendChild(overlay);
            return { parent, nextSibling, moved: true };
        }
        return { parent, nextSibling, moved: false };
    };

    const restoreOverlay = (overlay, placement) => {
        if (!overlay || !placement || !placement.parent || !placement.moved) {
            return;
        }
        if (placement.nextSibling && placement.nextSibling.parentNode === placement.parent) {
            placement.parent.insertBefore(overlay, placement.nextSibling);
        } else {
            placement.parent.appendChild(overlay);
        }
    };

    const setStatus = (overlay, message, isSuccess = false) => {
        const status = overlay.querySelector('.aegis-scan-status');
        if (status) {
            status.textContent = message;
            status.hidden = false;
            status.classList.toggle('is-success', isSuccess);
        }
    };

    const stopSession = () => {
        if (!activeSession) {
            return;
        }
        const { overlay, stream, intervalId, video, quaggaActive, quaggaHandler, overlayPlacement } = activeSession;
        if (intervalId) {
            clearInterval(intervalId);
        }
        if (quaggaActive && window.Quagga) {
            if (quaggaHandler) {
                window.Quagga.offDetected(quaggaHandler);
            }
            window.Quagga.stop();
        }
        if (overlay) {
            const wrap = overlay.querySelector('.aegis-scan-video-wrap');
            if (wrap) {
                wrap.querySelectorAll('video, canvas').forEach((node) => {
                    if (node.classList.contains('aegis-scan-video')) {
                        return;
                    }
                    node.remove();
                });
            }
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
        restoreOverlay(overlay, overlayPlacement);
        unlockBody();
        activeSession = null;
    };

    const getCameraErrorMessage = (error) => {
        if (!window.isSecureContext) {
            return '仅 HTTPS 支持相机扫码';
        }
        const name = error && error.name;
        if (name === 'NotAllowedError' || name === 'SecurityError') {
            return '相机权限被拒绝，请在浏览器设置中允许相机权限';
        }
        if (name === 'NotFoundError') {
            return '未检测到摄像头设备';
        }
        return '相机启动失败，请检查权限/浏览器兼容性';
    };

    const openScanner = (trigger) => {
        const container = trigger.closest('.aegis-inbound-page, .aegis-shipments-page') || document.body;
        const overlay = container.querySelector('.aegis-scan-overlay') || document.querySelector('.aegis-scan-overlay');
        if (!overlay) {
            return;
        }
        stopSession();
        const overlayPlacement = moveOverlayToBody(overlay);
        overlay.hidden = false;
        overlay.classList.add('is-open');
        lockBody();

        const video = overlay.querySelector('.aegis-scan-video');
        if (video) {
            video.hidden = false;
        }
        ensureVideoAttributes(video);
        setStatus(overlay, '正在启动相机...');

        const inputSelector = trigger.getAttribute('data-target-input');
        const submitSelector = trigger.getAttribute('data-target-submit');
        const input = inputSelector ? document.querySelector(inputSelector) : null;
        const submit = submitSelector ? document.querySelector(submitSelector) : null;
        let lastValue = '';
        let lastTime = 0;

        const handleDetected = (rawValue) => {
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
                setStatus(overlay, `已识别：${rawValue}`, true);
            } else {
                setStatus(overlay, `已识别：${rawValue}，请点击加入。`, true);
            }

            if (navigator.vibrate) {
                navigator.vibrate(50);
            }

            window.setTimeout(() => {
                stopSession();
            }, 400);
        };

        activeSession = {
            overlay,
            stream: null,
            intervalId: null,
            video,
            overlayPlacement,
            quaggaActive: false,
            quaggaHandler: null,
        };

        const startQuagga = () => {
            if (!window.Quagga) {
                setStatus(overlay, '当前浏览器不支持相机扫码，请手动输入。');
                return;
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setStatus(overlay, '当前浏览器不支持相机扫码，请手动输入。');
                return;
            }
            if (video) {
                video.hidden = true;
            }
            const target = overlay.querySelector('.aegis-scan-video-wrap');
            if (!target) {
                setStatus(overlay, '相机容器加载失败，请手动输入。');
                return;
            }
            const preservedVideo = target.querySelector('.aegis-scan-video');
            target.innerHTML = '';
            if (preservedVideo) {
                target.appendChild(preservedVideo);
            }

            const quaggaHandler = (result) => {
                const code = result && result.codeResult ? result.codeResult.code : '';
                handleDetected(code);
            };

            window.Quagga.init(
                {
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target,
                        constraints: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                    },
                    decoder: {
                        readers: QUAGGA_READERS,
                    },
                    locate: true,
                },
                (err) => {
                    if (err) {
                        setStatus(overlay, getCameraErrorMessage(err));
                        return;
                    }
                    window.Quagga.onDetected(quaggaHandler);
                    window.Quagga.start();
                    const quaggaVideo = target.querySelector('video');
                    ensureVideoAttributes(quaggaVideo);
                    setStatus(overlay, '对准条码，自动识别。');

                    activeSession = {
                        ...activeSession,
                        quaggaActive: true,
                        quaggaHandler,
                    };
                }
            );
        };

        if (!('BarcodeDetector' in window)) {
            startQuagga();
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
            startQuagga();
            return;
        }

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
                ensureVideoAttributes(video);
                video.play().catch(() => {});
                setStatus(overlay, '对准条码，自动识别。');

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
                            handleDetected(rawValue);
                        })
                        .catch((error) => {
                            setStatus(overlay, getCameraErrorMessage(error));
                        })
                        .finally(() => {
                            detecting = false;
                        });
                }, DETECT_INTERVAL);

                activeSession = {
                    ...activeSession,
                    stream,
                    intervalId,
                };
            })
            .catch((error) => {
                setStatus(overlay, getCameraErrorMessage(error));
            });
    };

    const init = () => {
        const triggers = document.querySelectorAll('[data-aegis-scan="1"]');
        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => openScanner(trigger));
        });
        document.addEventListener('click', (event) => {
            const target = event.target.closest('[data-aegis-scan="1"]');
            if (!target) {
                return;
            }
            event.preventDefault();
            openScanner(target);
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
