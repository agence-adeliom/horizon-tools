// Method defined in global.js and admin-bar.js
window.createIconElement = (source, size, justify = 'center', align = 'center') => {
    const icon = document.createElement('i');

    icon.style.maskImage = source.style.backgroundImage;
    icon.style.webkitMaskImage = source.style.backgroundImage;
    icon.style.maskRepeat = 'no-repeat';
    icon.style.maskPosition = 'center';
    icon.style.maskSize = 'contain';
    icon.style.width = `${size}px`;
    icon.style.height = `${size}px`;
    icon.style.display = 'inline-block';

    source.style.backgroundImage = 'none';
    source.style.display = 'flex';
    source.style.alignItems = align;
    source.style.justifyContent = justify;

    source.appendChild(icon);
};

const handleAdminBar = adminBar => {
    if (adminBar) {
        const elementsWithSvgIcon = Array.from(document.querySelectorAll('.ab-item.svg'));

        if (elementsWithSvgIcon) {
            elementsWithSvgIcon.forEach(eltWithSvgIcon => {
                window.createIconElement(eltWithSvgIcon, 18, 'left');
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const adminBar = document.querySelector('#wpadminbar');

    handleAdminBar(adminBar);
});
