(function () {
    const form = document.getElementById('webcutForm');
    if (!form) {
        return;
    }

    const longUrlInput = document.getElementById('longUrl');
    const customUrlInput = document.getElementById('customUrl');
    const resultDiv = document.getElementById('result');
    const submitButton = form.querySelector('[type="submit"]');
    const originalButtonText = submitButton ? submitButton.textContent : '';

    const messages = {
        missingFields: (window.webcutData && window.webcutData.missingFields) || 'Both fields are required.',
        defaultError: (window.webcutData && window.webcutData.defaultError) || 'Unable to create WebCut. Please try again.',
        successHeading: (window.webcutData && window.webcutData.successHeading) || 'Your WebCut URL:',
        processing: (window.webcutData && window.webcutData.processingMessage) || 'Processing your WebCut...',
        creating: (window.webcutData && window.webcutData.creatingLabel) || 'Creating...',
    };

    const ajaxUrl = (window.webcutData && window.webcutData.ajaxUrl) || '';
    const nonce = (window.webcutData && window.webcutData.nonce) || '';

    if (!ajaxUrl) {
        return;
    }

    const renderAlert = (type, message) => {
        if (!resultDiv) {
            return;
        }
        resultDiv.innerHTML = '\n            <div class="alert alert-' + type + '" role="status">' + message + '</div>\n        ';
    };

    const setLoading = (isLoading) => {
        if (!submitButton) {
            return;
        }
        submitButton.disabled = isLoading;
        submitButton.textContent = isLoading ? messages.creating : originalButtonText;
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const longUrl = longUrlInput ? longUrlInput.value.trim() : '';
        const customUrl = customUrlInput ? customUrlInput.value.trim() : '';

        if (!longUrl || !customUrl) {
            renderAlert('danger', messages.missingFields);
            return;
        }

        const params = new URLSearchParams({
            action: 'create_webcut',
            long_url: longUrl,
            custom_url: customUrl,
            nonce: nonce,
        });

        setLoading(true);
        renderAlert('info', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + messages.processing);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString(),
        })
            .then((response) => response.json())
            .then((data) => {
                const payload = data && typeof data === 'object' ? data.data || {} : {};
                if (data && data.success && payload.short_url) {
                    const shortUrl = payload.short_url;
                    const heading = '<strong>' + messages.successHeading + '</strong>';
                    renderAlert('success', heading + ' <a href="' + shortUrl + '" target="_blank" rel="noopener noreferrer" class="alert-link">' + shortUrl + '</a>');
                    form.reset();
                } else {
                    const message = (payload && payload.message) || messages.defaultError;
                    renderAlert('danger', message);
                }
            })
            .catch(() => {
                renderAlert('danger', messages.defaultError);
            })
            .finally(() => {
                setLoading(false);
            });
    });
})();
