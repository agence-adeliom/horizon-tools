document.addEventListener('DOMContentLoaded', () => {
    const adminMenu = document.querySelector('#adminmenu');

    if (adminMenu) {
        const elementsWithSvgIcon = Array.from(adminMenu.querySelectorAll('.wp-menu-image.svg'));

        elementsWithSvgIcon.forEach(eltWithSvgIcon => {
            if (eltWithSvgIcon.style.backgroundImage) {
                if (eltWithSvgIcon.style.backgroundImage.startsWith('url("')) {
                    const icon = document.createElement('i');

                    icon.style.maskImage = eltWithSvgIcon.style.backgroundImage;
                    icon.style.webkitMaskImage = eltWithSvgIcon.style.backgroundImage;
                    icon.style.maskRepeat = 'no-repeat';
                    icon.style.maskPosition = 'center';
                    icon.style.maskSize = 'contain';
                    icon.style.width = '20px';
                    icon.style.height = '20px';
                    icon.style.display = 'inline-block';

                    eltWithSvgIcon.style.backgroundImage = 'none';
                    eltWithSvgIcon.style.display = 'flex';
                    eltWithSvgIcon.style.alignItems = 'center';
                    eltWithSvgIcon.style.justifyContent = 'center';

                    eltWithSvgIcon.appendChild(icon);
                }
            }
        });
    }
});
