document.querySelectorAll('[data-confirm]').forEach((form) =>
    form.addEventListener('submit', (event) => {
        if (!confirm(form.dataset.confirm)) event.preventDefault();
    })
);
document.querySelectorAll('[data-file-preview]').forEach((input) =>
    input.addEventListener('change', () => {
        const file = input.files[0];
        const image = document.getElementById(input.dataset.filePreview);
        if (file && image) {
            if (image.dataset.blob) URL.revokeObjectURL(image.dataset.blob);
            image.src = URL.createObjectURL(file);
            image.dataset.blob = image.src;
        }
    })
);
