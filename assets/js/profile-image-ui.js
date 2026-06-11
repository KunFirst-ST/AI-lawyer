(function () {
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    }

    function setMeta(card, message, isInvalid) {
        var meta = card.querySelector('[data-profile-meta]');
        if (meta) {
            meta.textContent = message;
        }
        if (card.classList) {
            card.classList.toggle('is-invalid', Boolean(isInvalid));
        }
    }

    function showPreview(preview, file) {
        if (!window.FileReader || !file) {
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            preview.innerHTML = '';
            var image = document.createElement('img');
            image.src = String(event.target.result || '');
            image.alt = 'Profile image preview';
            preview.appendChild(image);
            if (preview.classList) {
                preview.classList.add('has-image');
            }
        };
        reader.readAsDataURL(file);
    }

    function handleFile(card, input, preview, file) {
        if (!file) {
            return;
        }

        if (file.type && file.type.indexOf('image/') !== 0) {
            input.value = '';
            setMeta(card, 'กรุณาเลือกไฟล์รูปภาพ', true);
            return;
        }

        setMeta(card, file.name || 'เลือกรูปใหม่แล้ว', false);
        showPreview(preview, file);
    }

    ready(function () {
        var uploaders = document.querySelectorAll('[data-profile-uploader]');
        for (var i = 0; i < uploaders.length; i += 1) {
            (function (card) {
                var input = card.querySelector('[data-profile-input]');
                var preview = card.querySelector('[data-profile-preview]');
                if (!input || !preview) {
                    return;
                }

                input.addEventListener('change', function () {
                    handleFile(card, input, preview, input.files && input.files[0]);
                });

                card.addEventListener('dragenter', function (event) {
                    event.preventDefault();
                    if (card.classList) {
                        card.classList.add('is-dragover');
                    }
                });

                card.addEventListener('dragover', function (event) {
                    event.preventDefault();
                });

                card.addEventListener('dragleave', function () {
                    if (card.classList) {
                        card.classList.remove('is-dragover');
                    }
                });

                card.addEventListener('drop', function (event) {
                    event.preventDefault();
                    if (card.classList) {
                        card.classList.remove('is-dragover');
                    }
                    if (!event.dataTransfer || !event.dataTransfer.files || !event.dataTransfer.files.length) {
                        return;
                    }

                    try {
                        input.files = event.dataTransfer.files;
                    } catch (error) {
                        setMeta(card, 'เลือกรูปผ่านปุ่มเลือกรูป', false);
                    }
                    handleFile(card, input, preview, event.dataTransfer.files[0]);
                });
            })(uploaders[i]);
        }
    });
})();
