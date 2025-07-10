const handleAdminMenu = adminMenu => {
    if (adminMenu) {
        const elementsWithSvgIcon = Array.from(adminMenu.querySelectorAll('.wp-menu-image.svg'));

        elementsWithSvgIcon.forEach(eltWithSvgIcon => {
            if (eltWithSvgIcon.style.backgroundImage) {
                if (eltWithSvgIcon.style.backgroundImage.startsWith('url("')) {
                    createIconElement(eltWithSvgIcon, 20);
                }
            }
        });
    }
};

const handleAdminBar = adminBar => {
    if (adminBar) {
        const elementsWithSvgIcon = Array.from(document.querySelectorAll('.ab-item.svg'));

        if (elementsWithSvgIcon) {
            elementsWithSvgIcon.forEach(eltWithSvgIcon => {
                createIconElement(eltWithSvgIcon, 18, 'left');
            });
        }
    }
};

const createIconElement = (source, size, justify = 'center', align = 'center') => {
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

document.addEventListener('DOMContentLoaded', () => {
    const adminMenu = document.querySelector('#adminmenu');
    const adminBar = document.querySelector('#wpadminbar');

    handleAdminMenu(adminMenu);
    handleAdminBar(adminBar);
});
